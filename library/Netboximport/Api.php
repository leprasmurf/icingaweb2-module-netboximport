<?php

namespace Icinga\Module\Netboximport;

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
    // private static function startsWith($haystack, $needle) {
    //      return (substr($haystack, 0, strlen($needle)) === $needle);
    // }
    //
    // private function setupCurl()
    // {
    //     $ch = curl_init();
    //
    //     // Configure curl
    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // curl_exec returns response as a string
    //     curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirect requests
    //     curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // limit number of redirects to follow
    //
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    //         'Authorization: Token ' . $this->apitoken,
    //     ));
    //
    //     return $ch;
    // }

    // private function apiRequest($method, $url, $get_params, $ch = null)
    // private function apiGet($url, $get_params = [], $ch = null)
    private function apiGet($url_path, $active_only, $get_params = [])
    {
        // if ($this->startsWith($url, $this->baseurl)) {
        //     $url = substr($url, strlen($this->baseurl));
        // } else if ($this->startsWith(preg_replace("/^http:/i", "https:", $url), $this->baseurl)) {
        //     $url = substr($url, strlen($this->baseurl)-1);
        // } else if ($this->startsWith(preg_replace("/^https:/i", "http:", $url), $this->baseurl)) {
        //     $url = substr($url, strlen($this->baseurl)+1);
        // }

        //  This module should only ever pull information from netbox, right?
        // if($method == 'POST') {
        //     curl_setopt($ch, CURLOPT_POST, 1);
        // } elseif ($method == 'PUT') {
        //     curl_setopt($ch, CURLOPT_PUT, 1);
        // } else {
        //     // defaults to GET
        // }


        // Create curl object if necessary
        // if (!isset($ch)) {
        //     $ch = $this->setupCurl();
        // }
        $ch = curl_init();

        // Configure curl
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // curl_exec returns response as a string
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirect requests
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // limit number of redirects to follow

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Token ' . $this->apitoken,
        ));
        // $url_path = parse_url($url, PHP_URL_PATH);
        //
        // // Return empty object if path is blank
        // if (trim($url_path, '/') === '') {
        //     return [];
        // }
        //
        // Strip '/api' since it's included in $this->baseurl
        $url_path = preg_replace("#^/api/#", "/", $url_path);

        // $get_params = parse_url($url, PHP_URL_QUERY);

        // This is limited by MAX_PAGE_SIZE (https://netbox.readthedocs.io/en/stable/configuration/optional-settings/#max_page_size)
        // $get_params['limit'] = 1000;
        // $get_params['status'] = $active_only;
        // $get_params['has_primary_ip'] = 'True';
        // $this->log_msg("Active Only: $active_only\n");

        // Convert parameters to URL-encoded query string
        $query = http_build_query($get_params);

        // Tie it all together
        $uri = $this->baseurl . $url_path . '/?' . $query;

        // get rid of duplicate slashes
        $uri = preg_replace("#//#", "/", $uri);

        $this->log_msg("Final URI: $uri\n");

        curl_setopt($ch, CURLOPT_URL, $uri);

        $response = curl_exec($ch); // CURLOPT_RETURNTRANSFER makes this a string
        $curl_error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($curl_error === '' && $status === 200) {
            $response = json_decode($response);

            return $response;
        // if (!isset($response->results)) { // single object
            //     return $response;
            // } elseif (isset($response->next)) { // paginated results
            //     // more results
            //     // array_merge($response->results, apiRequest($method, $url, $get_params, $ch)); // recursion
            //     $all_results = array_merge(
            //     $response->results,
            //     $this->apiGet($response->next, $get_params, $ch)
            //   );
            //     return $all_results;
            // } elseif (!isset($response->next)) { // end of pagination or single page
            //     return $response->results;
            // }

            // if(isset($response->results)) {
            //     return $response->results; // collection
            // } else {
            //     return $response; // single
            // }
        } else {
            throw new \Exception("Netbox API request failed: uri=$uri; status=$status; error=$curl_error");
        }
    }

    public function sanitizeUrl($url_in)
    {
        $url_out = parse_url($url_in);

        $url_out = preg_replace("#^/api/#", "/", $url_out['path']);

        return $url_out;
    }

    // Query API for resource passed
    //    $resource(String) - API Path
    // public function getResource($resource, $filter=array(), $cache=true)
    public function getResource($resource, $active_only = 0)
    {
        // $cache_key = sha1($resource . json_encode($filter));
        //
        // if (isset($this->cache[$cache_key])) {
        //     return $this->cache[$cache_key];
        // }

        // if ($follow_pagination === true) {
        //     // loop over resource until "next" field is null
        //     $data = $this->apiGet($resource);
        // } else {
        //     $data = $this->apiGet($resource);
        // }

        // // $this->cache[$cache_key] = $data;
        // $url_path = parse_url($resource, PHP_URL_PATH);
        //
        // // Return empty object if path is blank
        // // Reset the url path if the url parsing failed
        // if (trim($url_path, '/') === '') {
        //   $url_path = $resource;
        //     // return [];
        // }

        // // Strip '/api' since it's included in $this->baseurl
        // $url_path = preg_replace("#^/api/#", "/", $url_path);

        $results = [];
        $loop_protection = 10;
        $get_params = [
          "limit" => "1000",
          "status" => "$active_only"
        ];

        $this->log_file = fopen($this->log_file, "a");

        do {
            // Sanitize the input URL
            // $resource = $this->sanitizeUrl($resource);

            // Parse URL and assign query if set
            $resource = parse_url($resource);
            $query = $resource['query'] ?? [];

            $this->log_msg("Query: ". json_encode($query) . "\n");

            // If query is empty
            if ($query === []) {
                // copy default parameters
                $query = $get_params;
            } else {
                // break query string into array
                $working_query = explode('&', $query);
                $query = [];

                $this->log_msg("API: Working Query: " . json_encode($working_query) . "\n");

                // Cycle through the working query array
                foreach ($working_query as $elements) {
                    // break query elements into key => value pairs
                    $tmp_query = explode("=", $elements);

                    // Save to the query array
                    // array_push($query, $tmp_query);
                    $query[$tmp_query[0]] = $tmp_query[1];
                }
                // foreach(explode('=', $working_query) as $key => $value) {
                //   $query[$key] = $value;
                // }
                //
                // $query = explode('&', $query); // separate query elements
                // // Separate query key value pairs
                // $query = array_map(function($q) {
                //   return explode('=', $q);
                // }, $query);

                // Merge default parameters
                $query = array_merge($query, $get_params);
            }

            // $this->log_msg("API:  GET $resource['path'] (active: $active_only)\n\tQuery: $query\n");
            $this->log_msg("API:  GET shit\n\tPath: " . $resource['path'] . "\n\tQuery: " . json_encode($query) . "\n");


            // if(isset($resource['query'])) {
            //   $working_list = $this->apiGet($resource['path'], $active_only, $resource['query']);
            // } else {
            //   $working_list = $this->apiGet($resource['path'], $active_only);
            // }
            // Pull resource from API
            $working_list = $this->apiGet($resource['path'], $active_only, $query);

            // Grab the next URL if it exists
            $resource = $working_list->next ?? null;

            if ($resource !== null) {
                $this->log_msg("API: Pagination found: $resource\n");
            }

            // Drop all but ->results if key exists, otherwise noop.
            // if(isset($working_list->results)) {
            //   $working_list = $working_list->results
            // }

            $working_list = $working_list->results ?? $working_list;

            // Work the objects into the results array keyed to the object ID
            foreach($working_list as $obj) {
              array_push($results, $obj);
              // $results[$obj->id] = $obj;
            }
            // $working_list = $this->flattenNestedArray('', $working_list);
            // $this->log_msg("API: Working list results: " . count($working_list) . "\n");

            // Merge the current iteration's data into the results
            // array_merge($results, $working_list);
            $loop_protection--;
        } while ($resource !== null && $loop_protection > 0);

        // Debug
        // $this->log_msg("Returning results: " . json_encode($results) . "\n");
        $this->log_msg("Returning " . count($results) . " results.\n");

        fclose($this->log_file);
        return $results;
    }
}
