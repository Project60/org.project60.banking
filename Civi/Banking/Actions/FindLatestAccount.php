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
 * Class to find the latest bank account for a contact
 *
 * @package Civi\Banking\Actions
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
class FindLatestAccount extends AbstractAction {

  /**
   * @return SpecificationBag
   */
  public function getParameterSpecification() {
    $specs = new SpecificationBag();
    $specs->addSpecification(new Specification('contact_id', 'Integer', E::ts('Contact ID'), TRUE, NULL));
    return $specs;
  }

  /**
   * @return SpecificationBag
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag();
  }

  /**
   * Do the actual action - find the latest bank account ID for contact and with that ID find account
   *
   * @param ParameterBagInterface $parameters
   * @param ParameterBagInterface $output
   * @throws ExecutionException
   */
  public function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $contactId = (int) $parameters->getParameter('contact_id');
    if (!empty($contactId)) {
      try {
        $baId = civicrm_api3('BankingAccount', 'getvalue', [
          'return' => "id",
          'contact_id' => $contactId,
          'options' => ['sort' => "id DESC", 'limit' => 1],
        ]);
        if ($baId) {
          $bankAccount = civicrm_api3('BankingAccountReference', 'getvalue', [
            'return' => "reference",
            'ba_id' => $baId,
          ]);
          if ($bankAccount) {
            $output->setParameter('bank_account', $bankAccount);
          }
        }
      } catch (\CiviCRM_API3_Exception $ex) {
        throw new ExecutionException(E::ts('Could not find a bank account for contact') . $contactId
          . E::ts(', error message from API3 BankingAccount or BankingAccountReference getvalue: ') . $ex->getMessage());
      }
    }
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overriden by child classes.
   *
   * @return SpecificationBag
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification("bank_account", "String", E::ts("Bank Account"), FALSE),
    ]);
  }

}
