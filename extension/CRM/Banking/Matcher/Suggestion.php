<?php

class CRM_Banking_Matcher_Suggestion {

    private $_btx = null;
    private $_plugin = null;
    private $_blob = array();
    public $_probability;
    private $_title = "Title not set";
    public $_reasons;
    public $hash;

    public function __construct($plugin, $btx, $blob = null) {
        if ($blob != null) {
            // we are loading this from a blob
            $this->_blob = $blob;

            // TODO: parse probability & reasons
        } else {
            // this is newly generated
            $this->_probability = 0;
            $this->_reasons = array();
            $this->_plugin = $plugin;
            $this->_btx = $btx;
            $this->_title = $plugin->getTitle();
        }
    }

    public function exportToStruct() {
        // TODO:
        return array(
            'btx_id' => $this->_btx->id,
            'plugin_id' => $this->_plugin->id,
        );
    }
    
    public function setKey($key = '') {
      if ($key == '') {
        $key = 'default';
      }
      $this->hash = 'S-' . $this->_plugin->_plugin_id. '-' . $this->_btx->id . '-' . $key;
    }

    /**
     * This method makes sure, that $this->_plugin and $this->_btx are available after the call.
     */
    private function _updateObjects(CRM_Banking_BAO_BankTransaction $btx = null, CRM_Banking_PluginModel_Matcher $plugin = null) {
        // provide BTX
        if ($btx != null) {
            // see if we can use this...
            if ($this->_btx != null) {
                if ($this->_btx->id != $btx->id) {
                    CRM_Core_Session::setStatus(ts('Matcher tried to override BTX object with different entity'), ts('Matcher Failure'), 'alert');
                }
            }
            $this->_btx = $btx;
        } elseif ($this->_btx == null) {
            // load BTX
            if ($this->_blob != null && isset($this->_blob['btx_id'])) {
                $this->_btx = new CRM_Banking_BAO_BankTransaction();
                $this->_btx->get('id', $this->_blob['btx_id']);
            } else {
                CRM_Core_Session::setStatus(ts('Could not load BTX object, no id stored.'), ts('Matcher Failure'), 'alert');
            }
        }

        // provide plugin
        if ($plugin != null) {
            // see if we can use this...
            if ($this->_plugin != null) {
                if ($this->_plugin->id != $plugin->id) {
                    CRM_Core_Session::setStatus(ts('Matcher tried to override plugin object with different entity'), ts('Matcher Failure'), 'alert');
                }
            }
            $this->_plugin = $plugin;
        } elseif ($this->_plugin == null) {
            // load BTX
            if ($this->_blob != null && isset($this->_blob['matcher_id'])) {
                $plugin_instance = CRM_Banking_BAO_PluginInstance();
                $plugin_instance->get('id', $this->_blob['matcher_id']);
                $this->_plugin = $plugin_instance->getInstance();
            } else {
                CRM_Core_Session::setStatus(ts('Could not load plugin object, no id stored.'), ts('Matcher Failure'), 'alert');
            }
        }
    }

    public function getProbability() {
        return $this->_probability;
    }

    public function getTitle() {
        return $this->_title;
    }

    public function getActions() {
      return $this->_plugin->getActions($this->_btx);
    }

    /**
     * addEvidence computes the Bayesian combined evidence
     */
    public function addEvidence($factor, $reason = '') {
        if (($factor < 0) or ($factor > 1)) {
            CRM_Core_Session::setStatus(ts('Cannot add evidence outside [0,1] range, assuming 1'), ts('Warning: bad matcher evidence'), 'alert');
            $factor = 1;
        }

        $this->_probability = $this->_probability + (1 - $this->_probability) * $factor;
        if ($reason)
            $this->_reasons[] = $reason;
    }

    public function getEvidence() {
        return $this->_reasons;
    }

    public function execute(CRM_Banking_BAO_BankTransaction $btx = null, CRM_Banking_PluginModel_Matcher $plugin = null) {
        // if btx/plugin is not supplied (by the matcher engine), recreate it
        //$this->_updateObjects($btx, $plugin);

        // perform execute
        $continue = $this->_plugin->execute($this, $btx);
        return $continue;
    }

    public function visualize(CRM_Banking_BAO_BankTransaction $btx = null, CRM_Banking_PluginModel_Matcher $plugin = null) {
        // if btx/plugin is not supplied (by the matcher engine), recreate it
        $this->_updateObjects($btx, $plugin);
        return $this->_plugin->visualize_match($this, $this->_plugin);
    }
    
    public function prepForJson() {
      $prep = array();
      $prep['probability'] = $this->_probability;
      $prep['btx_id'] = $this->_btx->id;
      $prep['plugin_id'] = $this->_plugin->_plugin_id;
      $prep['reasons'] = $this->_reasons;
      $prep['hash'] = $this->hash;

      return $prep;
    }

}