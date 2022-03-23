<?php
namespace Civi\Banking\Actions;

use \Civi\ActionProvider\Action\AbstractAction;
use Civi\ActionProvider\Exception\ExecutionException;
use \Civi\ActionProvider\Exception\InvalidParameterException;
use Civi\ActionProvider\Parameter\OptionGroupByNameSpecification;
use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\SpecificationBag;
use \Civi\ActionProvider\Parameter\Specification;

use Civi\Core\Lock\NullLock;
use CRM_Banking_ExtensionUtil as E;

/**
 * Class to add an IBAN to a contact
 *
 * @package Civi\Banking\Actions
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
class AddIban extends AbstractAction {

  /**
   * @return SpecificationBag
   */
  public function getParameterSpecification() {
    $specs = new SpecificationBag();
    $specs->addSpecification(new Specification('contact_id', 'Integer', E::ts('Contact ID'), TRUE, NULL));
    $specs->addSpecification(new Specification('iban', 'String', E::ts('IBAN'), TRUE, NULL));
    $specs->addSpecification(new Specification('bic', 'String', E::ts("BIC"), FALSE, NULL));
    $specs->addSpecification(new Specification('account_name', 'String', E::ts("Account Name"), FALSE, NULL));
    $specs->addSpecification(new Specification('country_iso', 'String', E::ts('Country ISO Code'), FALSE, NULL));
    return $specs;
  }

  /**
   * @return SpecificationBag
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag();
  }

  /**
   * Do the actual action - add IBAN to contact
   *
   * @param ParameterBagInterface $parameters
   * @param ParameterBagInterface $output
   * @throws ExecutionException
   */
  public function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $contactId = (int) $parameters->getParameter('contact_id');
    $iban = $parameters->getParameter('iban');
    $ibanAccountReference = $this->getIbanAccountReference();
    if (!$this->exists($contactId, $iban) && $ibanAccountReference) {
      $parsedData = $this->parseBankAccountData($parameters);
      try {
        $ba = civicrm_api3('BankingAccount', 'create', [
          'contact_id' => $contactId,
          'description' => '',
          'created_date' => date('YmdHis'),
          'data_parsed' => $parsedData,
        ]);
        // add a reference
        civicrm_api3('BankingAccountReference', 'create', [
          'reference' => $iban,
          'reference_type_id' => $ibanAccountReference,
          'ba_id' => $ba['id'],
        ]);
      } catch (\CiviCRM_API3_Exception $ex) {
        throw new ExecutionException(E::ts('Could not add bank account') . $iban . E::ts(' to contact ID ') . $contactId
          . E::ts(', error message from API3 BankingAccount or BankingAccountReference create: ') . $ex->getMessage());
      }
    }
  }

  /**
   * Method to check if bank account exists
   *
   * @param int $contactId
   * @param string $iban
   * @return bool
   */
  private function exists(int $contactId, string $iban) {
    $query = "SELECT COUNT(*)
        FROM civicrm_bank_account cba JOIN civicrm_bank_account_reference cbar ON cba.id = cbar.ba_id
        WHERE cba.contact_id = %1 AND cbar.reference = %2";
    $count = \CRM_Core_DAO::singleValueQuery($query,[
      1 => [$contactId, "Integer"],
      2 => [$iban, "String"],
    ]);
    if ($count > 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Method to get the iban account reference type using api4 or api3
   *
   * @return array|false|mixed
   */
  private function getIbanAccountReference() {
    if (function_exists('civicrm_api4')) {
      try {
        $optionValues = \Civi\Api4\OptionValue::get()
          ->addSelect('value')
          ->addWhere('option_group_id:name', '=', 'civicrm_banking.reference_types')
          ->addWhere('name', '=', 'IBAN')
          ->execute();
        $optionValue = $optionValues->first();
        if ($optionValue['value']) {
          return $optionValue['value'];
        }
      }
      catch (\API_Exception $ex) {
      }
    }
    else {
      try {
        $accRef = civicrm_api3('OptionValue', 'getvalue', [
          'return' => "value",
          'option_group_id' => "civicrm_banking.reference_types",
          'name' => "IBAN",
        ]);
        if ($accRef) {
          return $accRef;
        }
      }
      catch (\CiviCRM_API3_Exception $ex) {
      }
    }
    return FALSE;
  }

  /**
   * Method to parse the data for the bank account
   *
   * @param ParameterBagInterface $parameters
   * @return false|int|string
   */
  private function parseBankAccountData(ParameterBagInterface $parameters) {
    $data = [];
    $bic = $parameters->getParameter('bic');
    if ($bic) {
      $data["BIC"] = $bic;
    }
    $accountName = $parameters->getParameter('account_name');
    if ($accountName) {
      $data["name"] = $accountName;
    }
    $country = $parameters->getParameter('country_iso');
    if ($country) {
      $data['country'] = $country;
    }
    return json_encode($data);
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overriden by child classes.
   *
   * @return SpecificationBag
   */
  public function getOutputSpecification() {
    return new SpecificationBag();
  }

}
