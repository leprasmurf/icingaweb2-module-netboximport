<?php

namespace Icinga\Module\Netboximport;

use Icinga\Module\Director\Objects\IcingaObject;

class Api
{
    public function __construct($baseurl, $apitoken)
    {
        $this->baseurl = rtrim($baseurl, '/') . '/';
        $this->apitoken = $apitoken;
        // $this->cache = [];
        $this->log_file = '/tmp/netbox_api.log';
    }

    private function log_msg($msg)
    {
        fwrite($this->log_file, $msg);
    }

    // stolen from https://stackoverflow.com/a/9546235/2486196
    // adapted to also flatten nested stdClass objects
    public function flattenNestedArray($prefix, $array, $delimiter="__")
    {
        // Initialize empty array
        $result = [];

        // Cycle through input array
        foreach ($array as $key => $value) {
            // Element is an object instead of a value
            if (is_object($value)) {
                // Convert value to an associative array of public object properties
                $value = get_object_vars($value);
            }

            // Recursion
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenNestedArray($prefix . $key . $delimiter, $value, $delimiter));
            // no Recursion
            } else {
                $result[$prefix . $key] = $value;
            }
        }

        return $result;
    }

    // returns json parsed object from GET request
    private function apiGet($url_path, $active_only, $get_params = [])
    {
        $ch = curl_init();

        // Configure curl
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // curl_exec returns response as a string
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirect requests
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // limit number of redirects to follow

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Token ' . $this->apitoken,
        ));
        // Strip '/api' since it's included in $this->baseurl
        $url_path = preg_replace("#^/api/#", "/", $url_path);

        // Convert parameters to URL-encoded query string
        $query = http_build_query($get_params);

        // Tie it all together
        $uri = $this->baseurl . $url_path . '/?' . $query;

        // get rid of duplicate slashes
        $uri = preg_replace("#//#", "/", $uri);

        $this->log_msg("\tFinal URI: $uri\n");

        curl_setopt($ch, CURLOPT_URL, $uri);

        $response = curl_exec($ch); // CURLOPT_RETURNTRANSFER makes this a string
        $curl_error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($curl_error === '' && $status === 200) {
            $response = json_decode($response);

            return $response;
        } else {
            throw new \Exception("Netbox API request failed: uri=$uri; status=$status; error=$curl_error");
        }
    }

    // $get_params
    private function parseGetParams($get_params = []) {
        $return_params = [
            // "limit" => "1000"
            "limit" => "10"
        ];

        // No get parameters set yet
        if($get_params === []) {
          return $return_params;
        } else if(is_string($get_params)) {
          // get parameters is currently in string format from `parse_url`
          // should be in the form of key=value&key2=value&key3=value
          $get_params = explode('&', $get_params);

          foreach ($get_params as $elements) {
              // Break "key=value" into array
              $tmp_array = explode('=', $elements);

              // Save to the return array
              $return_params[$tmp_array[0]] = $tmp_array[1];
          }
        } else {
            $return_params = array_merge($return_params, $get_params);
        }

        return $return_params;
    }

    // Query API for resource passed
    public function getResource($resource, $active_only = 0, $pagination = true)
    {
        $results = [];
        $loop_protection = 5;

        $this->log_file = fopen($this->log_file, "a");

        do {
            $loop_protection--;

            // Parse URL and assign query if set
            $resource = parse_url($resource);

            $query = $this->parseGetParams($resource['query'] ?? []);

            // Add the "active only" preference to the query
            $query["status"] = "$active_only";

            // $this->log_msg("API:  GET $resource['path'] (active: $active_only)\n\tQuery: $query\n");
            $this->log_msg("API:  GET -- Path: " . $resource['path'] . " -- Query: " . json_encode($query) . "\n");

            $working_list = $this->apiGet($resource['path'], $active_only, $query);

            // Grab the next URL if it exists
            $resource = $working_list->next ?? null;

            if ($resource !== null) {
                $this->log_msg("\tAPI: Pagination found: $resource\n");
            }

            // Set the working list to results if multiple objects returned
            $working_list = $working_list->results ?? $working_list;

            // Filter object missing the key column
            // TODO:  switch hard-coded `id` field to current setting
            // $working_list = array_filter($working_list, function($obj) {
            //     if($obj === null) {
            //         return false;
            //     }
            //
            //     if(isset($obj['id']) && $obj['id'] !== '') {
            //         return true;
            //     } else {
            //         return false;
            //     }
            // });

            // Work the objects into the results array keyed to the object ID
            foreach($working_list as $obj) {
                $flat_object = $this->flattenNestedArray('', $obj);
                // $this->log_msg("\tAdding " . $flat_object['id'] . "\n");
                // TODO:  Change the check to the key column in lieu of hard-coded 'id' field
                if(isset($flat_object['id']) && $flat_object['id'] !== '') {
                    // $this->log_msg("Adding flat object to " . count($results) . ".\n");
                    $results[] = $flat_object;
                }
            }

            $this->log_msg("Results count: " . count($results) . "\n");
        } while ($resource !== null && $loop_protection > 0);

        // Debug
        // $this->log_msg("Returning results: " . json_encode($results) . "\n");
        $this->log_msg("Returning " . count($results) . " results.\n");

        // $this->log_msg("First record id: " . $results[0]['id'] . "\n");
        // $this->log_msg("Results: " . json_encode($results) . "\n");
        fclose($this->log_file);
        return $results;
    }
}
