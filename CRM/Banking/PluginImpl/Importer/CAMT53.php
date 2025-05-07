<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2025 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
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

class CRM_Banking_PluginImpl_Importer_CAMT53 extends CRM_Banking_PluginModel_Importer
{

  /**
   * @var ?string $file_path
   *
   * path of the (temporary) source
   */
  protected $file_path = null;

  /**
   * @var SimpleXMLElement|null $statement_data
   *    the parsed CAMT import
   */
  protected $statement_data = null;

  /**
   * the plugin's user readable name
   *
   * @return string
   */
  static function displayName()
  {
    return E::ts('CAMT.053 XML Importer');
  }

  /**
   * class constructor
   */
  function __construct($config_name)
  {
    parent::__construct($config_name);
  }

  /**
   * This importer supports file imports
   */
  static function does_import_files() {
    return true;
  }

  /**
   * Set a new file path. This will also clear the current values, unless it's the same file path
   *
   * @param string $file_path
   * @return void
   */
  protected function setFilePath(string $file_path)
  {
    $file_path = realpath($file_path);
    if ($this->file_path != $file_path) {
      $this->file_path = $file_path;
      $this->statement_data = null;
      $this->logMessage(E::ts("Looking at file '%1'", [1 => $file_path]), 'info');
    }
  }

  protected function getCamtModel() : \SimpleXMLElement
  {
    if (!$this->statement_data) {
      if (empty($this->file_path))
        throw new Exception("Workflow error, no input file provided.");

      $this->logger->log(E::ts("Reading file '%1'...", [1 => $this->file_path]));
      $this->logger->setTimer('xml-reader');
      $this->statement_data = simplexml_load_file($this->file_path);
      $this->logger->logTime('xml-reader', 'reading xml data');
    }
    return $this->statement_data;
  }

  function probe_file($file_path, $params)
  {
    // check if this would be kicked by the upload limit
    //$upload_max_size = ini_get('upload_max_filesize');
    $current_file_size = filesize($file_path);
    if (empty($current_file_size)) {
      throw new Exception("Couldn't receive uploaded file, please check permissoions and/or increase PHP's upload_max_filesize setting!");
    }
    try {
      $this->setFilePath($file_path);
      $model = $this->getCamtModel();
      $stmt_count = count($model->BkToCstmrStmt->Stmt);
      if ($stmt_count != 1) {
        throw new Exception("Expected 1 'BkToCstmrStmt/Stmt' structure, found {$stmt_count}!");
      }
    } catch (Error $e) {
      $this->logger->logError("Error while opening '{$this->file_path}: " . $e->getMessage());
      return false;
    }

    // seems fine
    return true;
  }


  /**
   * @return void
   */
  public function resetImporter()
  {
    // read config, set defaults
    $this->statement_data = null;
    $this->file_path = null;
    parent::resetImporter();
  }

  /**
   * Import the given file as a bank statment
   *
   * @param string $file_path
   * @param array $params
   * @return false|void
   * @throws Exception
   */
  function import_file($file_path, $params)
  {
    try {
      $this->setFilePath($file_path);
      $model = $this->getCamtModel();
      foreach ($model->BkToCstmrStmt->Stmt as $stmt) {
        // create new transaction batch in CiviBanking
        $this->openTransactionBatch();

        // todo: add statement metadata

        // now iterate through all Ntry nodes and add transactions (and sub-batches)
        foreach ($stmt->Ntry as $entry) {
          // detect and process sub-batches, e.g. SEPA collections
          if (isset($entry->NtryDtls->Btch)) {
            // this is a transaction batch
            $this->importTransactionBatch($entry);

          } else {
            $this->importTransaction($entry);
          }
        }
      }
      $this->closeTransactionBatch(true);
    } catch (Error $e) {
      $this->logger->logError("Failed to process document tree: " . $e->getMessage());
    }
 }

  /**
   * Import regular Ntry DOM nodes
   *
   * @param DOMNode $entry
   * @return void
   */
 protected function importTransaction(SimpleXMLElement $entry)
 {
   /** @var bool $is_credit */
   $credit_or_debit = (string) $entry->CdtDbtInd;
   $is_credit = ('CRDT' == $credit_or_debit);

   $btx_data = [
     'type_id' => 0,  // not used
     'status_id' => $this->_default_btx_state_id,
     'booking_date' => (string) $entry->BookgDt->Dt,
     'value_date' =>  (string)  $entry->ValDt->Dt, // use booking for both?
     'bank_reference' =>  (string) $entry->NtryDtls->TxDtls->Refs->TxId,
     'currency' => ((string) $entry->Amt->attributes()['Ccy']) ?? 'EUR',
     'purpose' => (string) $entry->NtryDtls->TxDtls->RmtInf->Ustrd,
   ];

   // add raw XML if
   if (empty($this->getConfig()->dont_store_xml_tx_raw)) {
     $btx_data['data_raw'] = preg_replace('/\s+/', '', $entry->asXML());
   };

   if ($is_credit) {
     $btx_data['amount'] = (string) $entry->Amt;
     $btx_data['name'] = (string) $entry->NtryDtls->TxDtls->RltdPties->Dbtr->Pty->Nm ?? '';
     $btx_data['_IBAN'] = (string) $entry->NtryDtls->TxDtls->RltdPties->CdtrAcct->Id->IBAN ?? '';
     $btx_data['_party_IBAN'] = (string) $entry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Id->IBAN ?? '';
  } else {
     $btx_data['amount'] = '-' . $entry->Amt;
     $btx_data['name'] = (string) $entry->NtryDtls->TxDtls->RltdPties->Cdtr->Pty->Nm ?? '';
     $btx_data['_IBAN'] = (string) $entry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Id->IBAN ?? '';
     $btx_data['_party_IBAN'] = (string) $entry->NtryDtls->TxDtls->RltdPties->CdtrAcct->Id->IBAN ?? '';
   }
   $this->lookupBankAccounts($btx_data);
   $this->compile_data_parsed($btx_data);


   // and finally write it into the DB
   $this->checkAndStoreBTX($btx_data, 0, []); // todo: progress
 }

  /**
   * This function will separate the variable/secondary parameters from the btx data and
   *   move them into the data_parsed key
   *
   * @param array $btx_data the collected data on the transaction
   *
   * @todo move to (abstract) CRM_Banking_PluginModel_Importer
   */
 protected function compile_data_parsed(&$btx_data)
 {
   // make sure there is a data_parsed array
   if (empty($btx_data['data_parsed']) || $btx_data['data_parsed'] == '[]') {
     $btx_data['data_parsed'] = [];
   } elseif (!is_array($btx_data['data_parsed'])) {
     throw new Exception('Check you importer implementation: data_parsed must be an array if it exists.');
   }

   // prepare $btx: put all entries, that are not for the basic object, into parsed data
   $btx_parsed_data = [];
   foreach ($btx_data as $key => $value) {
     if (!in_array($key, $this->_primary_btx_fields)) {
       // this entry has to be moved to the $btx_parsed_data records
       $btx_parsed_data[$key] = $value;
       unset($btx_data[$key]);
     }
   }
   $btx_data['data_parsed'] = json_encode($btx_parsed_data);
 }

  /**
   * Import Btch/TxDtls batches
   *
   * @param DOMNode $entry
   * @return void
   */
  protected function importTransactionBatch(SimpleXMLElement $entry)
  {
    /** @var bool $is_credit */
    $is_credit = $entry->CdtDbtInd[0] == 'CRDT';

    $btx_template = [
      'type_id' => 0, // not used
      'status_id' => $this->_default_btx_state_id,
      'booking_date' => (string) $entry->BookgDt->Dt,
      'value_date' => (string) $entry->ValDt->Dt, // use booking for both?
      'currency' => (string) $entry->Amt->attributes()['Ccy'] ?? 'EUR',
    ];

    // now process each entry of the batch
    foreach ($entry->NtryDtls->TxDtls as $txDtl) {
      $btx = $btx_template;
      $btx['bank_reference'] = (string) $txDtl->Refs->TxId ?? '';
      $btx['amount'] = (string) $txDtl->Amt;
      $btx['purpose'] = (string) $txDtl->RmtInf->Ustrd;
      $btx['data_raw'] = preg_replace('/\s+/', '', $txDtl->asXML());

      if ($is_credit) {
        $btx['name'] = (string) $txDtl->RltdPties->Dbtr->Pty->Nm ?? '';
        $btx['_IBAN'] = (string) $txDtl->RltdPties->CdtrAcct->Id->IBAN ?? '';
      } else {
        $btx['name'] = (string) $txDtl->RltdPties->Cdtr->Pty->Nm ?? '';
        $btx['_IBAN'] = (string) $txDtl->RltdPties->DbtrAcct->Id->IBAN ?? '';
      }

      // postprocess and write to DB
      $this->lookupBankAccounts($btx);
      $this->compile_data_parsed($btx);
      $this->checkAndStoreBTX($btx, 0, []);
    }
  }






  function probe_stream($params)
  {
    // TODO: Implement probe_stream() method.
    return false;
  }

  function import_stream($params)
  {
    throw new Exception(E::ts("CAMT.053 importer doesn't support importing streams"));
  }
}

