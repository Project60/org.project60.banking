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
        $current_tx_batch = $this->getCurrentTransactionBatch(false);

        // todo: add statement metadata?

        // now iterate through all Ntry nodes and add transactions (and sub-batches)
        foreach ($stmt->Ntry as $entry) {
          // detect and process sub-batches, e.g. SEPA transaction
          if (isset($entry->NtryDtls->Btch)) {
            // this is a transaction batch
            $this->importTransactionBatch($entry);

          } else {
            $this->importTransaction($entry);
          }
        }
      }
    } catch (Error $e) {
      $this->logger->logError("Failed to iterate document tree: " . $e->getMessage());
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
   $is_credit = $entry->CdtDbtInd[0] == 'CRDT';

   $btx = [
     'type_id' => 0,                               // TODO: lookup type ?
     'status_id' => 0,                             // TODO: lookup status new
     'booking_date' => (string) $entry->BookgDt->Dt,
     'value_date' =>  (string)  $entry->ValDt->Dt, // use booking for both?
     'bank_reference' =>  (string) $entry->NtryDtls->TxDtls->Refs->TxId ?? '',
     'currency' => ((string) $entry->Amt->attributes()['Ccy']) ?? 'EUR',
   ];

   if ($is_credit) {
     $btx['amount'] = (string) $entry->Amt;
     $btx['IBAN'] = (string) $entry->NtryDtls->TxDtls->RltdPties->CdtrAcct->Id->IBAN ?? '';
     $btx['name'] = (string) $entry->NtryDtls->TxDtls->RltdPties->Dbtr->Pty->Nm ?? '';
  } else {
     $btx['amount'] = '-' . $entry->Amt;
     $btx['name'] = (string) $entry->NtryDtls->TxDtls->RltdPties->Cdtr->Pty->Nm ?? '';
     $btx['IBAN'] = (string) $entry->NtryDtls->TxDtls->RltdPties->DbtrAcct->Id->IBAN ?? '';
   }

   $this->lookupBankAccounts($btx);

//   $this->checkAndStoreBTX($btx, $progress, $params);
   $this->checkAndStoreBTX($btx, 0, []);

   return $btx;
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
      'type_id' => 0,                               // TODO: lookup type ?
      'status_id' => 0,                             // TODO: lookup status new
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


      if ($is_credit) {
        $btx['name'] = (string) $txDtl->RltdPties->Dbtr->Pty->Nm ?? '';
        $btx['IBAN'] = (string) $txDtl->RltdPties->CdtrAcct->Id->IBAN ?? '';
      } else {
        $btx['name'] = (string) $txDtl->RltdPties->Cdtr->Pty->Nm ?? '';
        $btx['IBAN'] = (string) $txDtl->RltdPties->DbtrAcct->Id->IBAN ?? '';
      }
      $this->lookupBankAccounts($btx);
    }

//   $this->checkAndStoreBTX($btx, $progress, $params);
    $this->checkAndStoreBTX($btx, 0, []);

    return $btx;
  }






  function probe_stream($params)
  {
    // TODO: Implement probe_stream() method.
    return true;
  }

  function import_stream($params)
  {
    throw new Exception(E::ts("CAMT.053 currently doesn't implement importing streams"));
  }
}

