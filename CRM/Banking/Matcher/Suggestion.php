<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2018 P. Delbar                      |
| Author: P. Delbar                                      |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Banking_ExtensionUtil as E;


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
        $this->setParameter('id', $identification);
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

    public function getPlugin() {
        return $this->_plugin;
    }

    public function getPluginID() {
        return $this->_plugin->_plugin_id;
    }

    /**
     * The user confirmation will prompt the user to
     *  separately confirm whatever question (string) is posted
     *  here.
     *
     * @return string|null a human readable yes/no question, that will be presented verbatim to the user.
     *                     NULL or empty string are to be ignored
     */
    public function getUserConfirmation() {
      return $this->getParameter('user_confirmation');
    }

    /**
     * The user confirmation will prompt the user to
     *  separately confirm whatever question (string) is posted
     *  here.
     *
     *  CAUTION: setting this string will also prevent automatic execution
     *
     * @param $question string  a human readable yes/no question, that will be presented verbatim to the user
     */
    public function setUserConfirmation($question) {
      $this->setParameter('user_confirmation', $question);
    }

    public function getTitle() {
        return $this->getParameter('title');
    }

    public function setTitle($name) {
        $this->setParameter('title', $name);
    }

    public function getProbability() {
        return $this->getParameter('probability');
    }

    public function setProbability($probability) {
        $this->setParameter('probability', $probability);
    }

    public function getEvidence() {
        return $this->getParameter('reasons');
    }

    public function setEvidence($reasons) {
        $this->setParameter('reasons', $reasons);
    }

    public function setExecuted() {
        $this->setParameter('executed', date('YmdHis'));
        $user_id = CRM_Core_Session::singleton()->get('userID');
        $this->setParameter('executed_by', $user_id);
    }

  /**
   * @param \CRM_Banking_PluginModel_PostProcessor $postprocessor
   * @param $result
   */
    public function setExecutedPostprocessor($postprocessor, $result) {
      if (!is_array($postprocessors = $this->getParameter('executed_postprocessors'))) {
        $postprocessors = [];
      }
      $postprocessors[$postprocessor->getPluginID()] = $result;
      $this->setParameter('executed_postprocessors', $postprocessors);
    }

    public function getExecutedPostprocessors() {
      return $this->getParameter('executed_postprocessors');
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
                    CRM_Core_Session::setStatus(E::ts('Matcher tried to override BTX object with different entity'), E::ts('Matcher Failure'), 'alert');
                }
            }
            $this->_btx = $btx;
        } elseif ($this->_btx == null) {
            // load BTX
            if ($this->_blob != null && isset($this->_blob['btx_id'])) {
                $this->_btx = new CRM_Banking_BAO_BankTransaction();
                $this->_btx->get('id', $this->_blob['btx_id']);
            } else {
                CRM_Core_Session::setStatus(E::ts('Could not load BTX object, no id stored.'), E::ts('Matcher Failure'), 'alert');
            }
        }

        // provide plugin
        if ($plugin != null) {
            // see if we can use this...
            if ($this->_plugin != null) {
                if ($this->_plugin->id != $plugin->id) {
                    CRM_Core_Session::setStatus(E::ts('Matcher tried to override plugin object with different entity'), E::ts('Matcher Failure'), 'alert');
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
                CRM_Core_Session::setStatus(E::ts('Could not load plugin object, no id stored.'), E::ts('Matcher Failure'), 'alert');
            }
        }
    }

    /**
     * addEvidence computes the Bayesian combined evidence
     */
    public function addEvidence($factor, $reason = '') {
        if (($factor < 0) or ($factor > 1)) {
            CRM_Core_Session::setStatus(E::ts('Cannot add evidence outside [0,1] range, assuming 1'), E::ts('Warning: bad matcher evidence'), 'alert');
            $factor = 1;
        }

        // FIXME: I'm not sure, if this is the correct implementation of Bayes' rule...
        $probability = $this->getProbability();
        $new_probability = $probability + (1 - $probability) * $factor;
        $this->setProbability($new_probability);
        if ($reason) {
            $evidence = $this->getEvidence();
            array_push($evidence, $reason);
            $this->setEvidence($evidence);
        }
    }

    /**
     * If the user has modified the input fields provided by the "visualize" html code,
     * the new values will be passed here BEFORE execution
     *
     * this will be passed on to the plugin that generated the suggestion
     *
     * @return bool TRUE if there was changes (already saved)
     */
    public function update_parameters($parameters) {
      // check if there's anything to update
      if (empty($parameters)) {
        return FALSE;
      }

      // only update if transaction still open (see BANKING-232)
      if (!banking_helper_tx_status_closed($this->_btx->status_id)) {
        $this->_plugin->update_parameters($this, $parameters);
        $this->_btx->saveSuggestions();
        return TRUE;
      }

      return FALSE;
    }

    /**
     * Execute this suggestion on the given transaction
     *
     * @param $btx CRM_Banking_BAO_BankTransaction
     *
     * @return boolean
     */
    public function execute($btx) {
        // only execute if not completed yet
        if (!banking_helper_tx_status_closed($btx->status_id)) {
            // perform execute
            $result = $this->_plugin->execute($this, $btx);
            if ($result && ($result !== 're-run')) {
                $engine = CRM_Banking_Matcher_Engine::getInstance();
                $engine->runPostProcessors($this, $btx, $this->_plugin);
            }
            return $result;
        } else {
            return TRUE;
        }
    }

    /**
     * Visualize this suggestion
     */
    public function visualize(CRM_Banking_BAO_BankTransaction $btx = null, CRM_Banking_PluginModel_Matcher $plugin = null) {
        // if btx/plugin is not supplied (by the matcher engine), recreate it
        $this->_updateObjects($btx, $plugin);
        $visualisation = $this->_plugin->visualize_match($this, $btx);

        // Visualize post processors.
        if (!empty($post_processor_previews = $this->getParameter(
            'post_processor_previews'
        ))) {
          $count = count($post_processor_previews);
          $visualisation .= '<div class="banking--postprocessor-preview crm-accordion-wrapper collapsed">'
            . '<div class="crm-accordion-header">'
            . ($count == 1
              ? E::ts('1 Post Processor')
              : E::ts('%1 Post Processors', [1 => $count]))
            . '</div>'
            . '<div class="crm-accordion-body">';

            $visualisation .= '<p>' . E::ts('The following post processors may be executed after processing this suggestion:') . '</p>';
            $visualisation .= '<ol>';
            foreach ($post_processor_previews as $post_processor_title => $post_processor_preview) {
                $visualisation .= '<li>' . $post_processor_title . $post_processor_preview . '</li>';
            }
            $visualisation .= '</ol>';
            $visualisation .= '</div></div>';
        }

        return $visualisation;
    }

    /**
     * Visualize this execution
     */
    public function visualize_execution(CRM_Banking_BAO_BankTransaction $btx = null, CRM_Banking_PluginModel_Matcher $plugin = null) {
        // if btx/plugin is not supplied (by the matcher engine), recreate it
        $this->_updateObjects($btx, $plugin);
        $visualisation = $this->_plugin->visualize_execution_info($this, $btx);

        $engine = CRM_Banking_Matcher_Engine::getInstance();

        // Visualize post processors.
        if (!empty($post_processor_results = $engine->visualizePostProcessorResults($this, $btx, $this->_plugin))) {
            $visualisation .= '<p>' . E::ts('The following post processors have been executed after processing this suggestion:') . '</p>';
            $visualisation .= '<ol>';
            foreach ($post_processor_results as $post_processor_result) {
                $visualisation .= '<li>' . $post_processor_result . '</li>';
            }
            $visualisation .= '</ol>';
        }

        return $visualisation;
    }

    public function prepForJson() {
        return $this->_blob;
    }

}