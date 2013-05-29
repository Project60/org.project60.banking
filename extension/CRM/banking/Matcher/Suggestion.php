<?php

class CRM_Banking_Matcher_Suggestion {
  
  
    public function execute( CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_PluginModel_Matcher $plugin = null) {
      // if plugin is not supplied (by the matcher engine), recreate it
      
      // perform execute
      $continue = $plugin->execute( $this, $btx );
      
      return $continue;
  }

}