<?php

namespace Icinga\Module\Netboximport\ProvidedHook\Director;

use Icinga\Application\Config;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Netboximport\Api;

error_reporting(E_ALL);
ini_set('max_execution_time', 600);
// ini_set('memory_limit', 536870912);

class ImportSource extends ImportSourceHook
{
    private $api;
    private $resolve_properties = [
        "cluster",
    ];
    private $log_file;

    private function log_msg($msg)
    {
        fwrite($this->log_file, $msg);
    }

    private function fetchObjects($resource, $active_only, $additionalKeysCallback = null)
    {
        $this->log_msg("Starting ImportSource:  fetchObjects for $resource\n");

        $results = $this->api->getResource($resource, $active_only);

        $this->log_msg("(ImportSource/fetchObjects) Results Count: " . count($results) . "\n");

        // $this->log_msg("\n" . json_encode($results) . "\n");

        return $results;
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'baseurl', array(
            'label'       => $form->translate('Base URL'),
            'required'    => true,
            'description' => $form->translate(
                'API url for your instance, e.g. https://netbox.example.com/api'
            )
        ));

        $form->addElement('text', 'apitoken', array(
            'label'       => $form->translate('API-Token'),
            'required'    => true,
            'description' => $form->translate(
                '(readonly) API token. See https://netbox.example.com/user/api-tokens/'
            )
        ));

        $form->addElement('YesNo', 'importdevices', array(
            'label'       => $form->translate('Import devices'),
            'description' => $form->translate('import physical devices (dcim/devices in netbox).'),
        ));

        $form->addElement('YesNo', 'importvirtualmachines', array(
            'label'       => $form->translate('Import virtual machines'),
            'description' => $form->translate('import virtual machines (virtualization/virtual-machines in netbox).'),
        ));

        $form->addElement('YesNo', 'activeonly', array(
            'label'       => $form->translate('Import active objects only'),
            'description' => $form->translate('only load objects with status "active" (as opposed to "planned" or "offline")'),
        ));
    }

    /**
     * Returns an array containing importable objects
     *
     * @return array
     */
    public function fetchData()
    {
        // Shortcut variables
        $baseurl = $this->getSetting('baseurl');
        $apitoken = $this->getSetting('apitoken');
        $active_only = $this->getSetting('activeonly') === 'y';
        $key_column = "id";
        // $key_column = $this->getSetting('key_column', 'id');
        // $key_column = $this->getSetting('source_name');

        // Create the API object
        $this->api = new Api($baseurl, $apitoken);

        $this->log_file = fopen("/tmp/netbox_api.log", "a") or die("Unable to open netbox log");

        // Initialize an empty array
        $objects = [];

        // Devices
        if ($this->getSetting('importdevices') === 'y') {
            $tmp_obj = $this->fetchObjects('dcim/devices', $active_only);

            $this->log_msg("DCIM Devices retrieved:  " . count($tmp_obj) . "\n");

            foreach($tmp_obj as $o) {
                $objects[] = $o;
            }
        }

        $this->log_msg("Final object to return:\n\tCount: " . count($objects) . "\n");

        //$this->log_msg(json_encode($objects) . "\n\n");
        $this->log_msg("Key column '$key_column' for return object:\n");
        foreach ($objects as $row) {
            $this->log_msg($row[$key_column] . " | ");
        }
        $this->log_msg("\n\n");

        for($i = 0; $i < count($objects); $i++) {
            $this->log_msg("Array keys for row #" . $i . ":\n");
            foreach(array_keys($objects[$i]) as $key) {
                $this->log_msg("\t$key\n");
            }
        }

        // $this->log_msg("\nArray Keys:  " . json_encode(array_keys($objects)) . "\n");

        $this->log_msg("\n---\n" . json_encode($objects) . "\n---\n");

        fclose($this->log_file);

        return $objects;
    }

    /**
    * Returns a list of all available columns
    *
    * @return array
    */
    public function listColumns() {
      $results = array_keys($this->fetchData()[0]);
      $this->log_file = fopen("/tmp/netbox_api.log", "a") or die("Unable to open netbox log");
      // $this->log_msg("Preview output:\n\t" . json_encode($results) . "\n\n");
      fclose($this->log_file);


      return $results;
      // return a list of all keys, which appeared in any of the objects
      // return array_keys(array_merge(...array_map('get_object_vars', $this->fetchData())));
      // return array_keys($this->fetchData());
    }

    // Override class function to specify the module name
    public function getName() {
        return 'Netbox';
    }
}
