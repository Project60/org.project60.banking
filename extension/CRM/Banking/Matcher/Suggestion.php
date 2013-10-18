<?php

class CRM_Banking_Matcher_Suggestion {

    private $_btx = null;
    private $_plugin = null;
    
    private $_blob = array();
    
    public function __construct($plugin, $btx, $blob = null) {
        $this->_btx = $btx;
        $this->_plugin = $plugin;

        if ($blob != null) {
            // we are loading this from a blob
            $this->_blob = $blob;

        } else {
            // this is newly generated
            $this->setProbability(0.0);
            $this->setEvidence(array());
            $this->setTitle($plugin->getTitle());
        }

        if ($this->_btx) {
            $this->setParameter('btx_id', $this->_btx->id);            
        }

        if ($this->_plugin) {
            $this->setParameter('plugin_id', $this->_plugin->_plugin_id); 
        }
    }
    
    public function getParameter($key) {
        if (isset($this->_blob[$key])) {
            return $this->_blob[$key];
        } else {
            return null;
        }
    }

    public function setParameter($key, $value) {
        $this->_blob[$key] = $value;
    }

    public function getId() {
        return $this->getParameter('id');
    }

    public function setId($identification) {
        return $this->setParameter('id', $identification);
    }

    public function getHash() {
        $hash = $this->getParameter('hash');
        if (!$hash) {
            // this is the HASH function for the keys
            $hash = 'S-' . $this->_plugin->_plugin_id. '-' . $this->_btx->id . '-' . $this->getParameter('id');
            $this->setParameter('hash', $hash);
        }
        return $hash;
    }

    public function getTitle() {
        return $this->getParameter('title');
    }

    public function setTitle($name) {
        return $this->setParameter('title', $name);
    }

    public function getProbability() {
        return $this->getParameter('probability');
    }

    public function setProbability($probability) {
        return $this->setParameter('probability', $probability);
    }

    public function getActions() {
      return $this->_plugin->getActions($this->_btx);
    }

    public function getEvidence() {
        return $this->getParameter('reasons');
    }

    public function setEvidence($reasons) {
        return $this->setParameter('reasons', $reasons);
    }

    public function setExecuted() {
        $this->setParameter('executed', date('YmdHis'));
    }

    public function isExecuted() {
        return $this->getParameter('executed');
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

    /**
     * addEvidence computes the Bayesian combined evidence
     */
    public function addEvidence($factor, $reason = '') {
        if (($factor < 0) or ($factor > 1)) {
            CRM_Core_Session::setStatus(ts('Cannot add evidence outside [0,1] range, assuming 1'), ts('Warning: bad matcher evidence'), 'alert');
            $factor = 1;
        }

        $probability = $this->getProbability();
        $new_probability = $probability + (1 - probability) * $factor;
        $this->setProbability($new_probability);
        if ($reason) {
            $this->setEvidence(array($reason));
        }
    }

    /**
     * If the user has modified the input fields provided by the "visualize" html code,
     * the new values will be passed here BEFORE execution
     *
     * this will be passed on to the plugin that generated the suggestion
     */
    public function update_parameters($parameters) {
        return $this->_plugin->update_parameters($this, $parameters);
    }

    public function execute(CRM_Banking_BAO_BankTransaction $btx = null, CRM_Banking_PluginModel_Matcher $plugin = null) {
        // if btx/plugin is not supplied (by the matcher engine), recreate it
        //$this->_updateObjects($btx, $plugin);

        // perform execute
        $continue = $this->_plugin->execute($this, $btx);
        $this->setExecuted();
        return $continue;
    }

    public function visualize(CRM_Banking_BAO_BankTransaction $btx = null, CRM_Banking_PluginModel_Matcher $plugin = null) {
        // if btx/plugin is not supplied (by the matcher engine), recreate it
        $this->_updateObjects($btx, $plugin);
        return $this->_plugin->visualize_match($this, $this->_plugin);
    }
    
    public function prepForJson() {
        return $this->_blob;
    }

}