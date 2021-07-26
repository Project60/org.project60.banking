<?php
/*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2018 SYSTOPIA                            |
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

/**
 * This PostProcessor will connect the generated/matched contribution
 * with a membership
 */
class CRM_Banking_PluginImpl_PostProcessor_MembershipExtension extends CRM_Banking_PluginModel_PostProcessor {

  /** cache for getCurrentStatusIDs */
  protected static $_current_status_ids = NULL;

  /** cache for getMembershipType */
  protected static $_membership_types = NULL;


  /**
   * class constructor
   */
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;

    // preconditions:
    if (!isset($config->financial_type_ids))             $config->financial_type_ids              = [2]; // Membership Dues
    if (!isset($config->contribution_status_ids))        $config->contribution_status_ids         = [1]; // Completed
    if (!isset($config->payment_instrument_ids))         $config->payment_instrument_ids          = NULL;
    if (!isset($config->payment_instrument_ids_exclude)) $config->payment_instrument_ids_exclude  = NULL;

    // how to identify the memberships:
    if (!isset($config->find_via_contact))               $config->find_via_contact               = TRUE;            // consider all memberships with the same contact
    if (!isset($config->find_via_payment))               $config->find_via_payment               = TRUE;            // consider all memberships linked by membership_payment
    if (!isset($config->find_via_btxfield))              $config->find_via_btxfield              = 'btx.membership_id'; // consider all membership IDs in the content of this btx field

    // how to filter the memberships
    if (!isset($config->filter_current))                 $config->filter_current                 = TRUE;  // current memberships only
    if (!isset($config->filter_status))                  $config->filter_status                  = FALSE; // list of status_ids
    if (!isset($config->filter_minimum_amount))          $config->filter_minimum_amount          = TRUE;  // could also be monetary amount
    if (!isset($config->filter_membership_types))        $config->filter_membership_types        = [];    // membership type IDs, empty means all
    if (!isset($config->filter_max_end_date))            $config->filter_max_end_date            = "3 months";  // end date should not be [this time] after the contribution's receive_date

    // how to extend the membership
    if (!isset($config->extend_by))                      $config->extend_by                      = 'period';    // could also be strtotime offset like "+1 month"
    if (!isset($config->extend_from))                    $config->extend_from                    = 'min';       // could also be 'payment_date' or 'end_date'. 'min' means the minimum of the two
    if (!isset($config->align_end_date))                 $config->align_end_date                 = NULL;        // should the new end date be adjusted to the 'next_last' of the month? Could also be 'last_last'
    if (!isset($config->status_override))                $config->status_override                = NULL;        // Whether the membership status is to be overridden or explicitly set to not be overridden anymore.
    if (!isset($config->set_status))                     $config->set_status                     = NULL;        // A membership status ID the membership is to be explicitly set to - might only be useful when setting status_override to "1".
    if (!isset($config->skip_extend_status))             $config->skip_extend_status             = [];          // A list of membership status IDs for which no extension, but status updates should be done.

    // create of not found
    if (!isset($config->create_if_not_found))            $config->create_if_not_found            = FALSE;  // do we want to create a membership, if none is found?
    if (!isset($config->create_type_id))                 $config->create_type_id                 = 1;      // membership_type_id to create
    if (!isset($config->create_start_date))              $config->create_start_date              = 'receive_date';  // could also be 'next_first', 'last_first', or anything the DateTime parser understands.
    if (!isset($config->create_start_date_reference))    $config->create_start_date_reference    = 'receive_date';  // could also be anything the DateTime parser understands.
    if (!isset($config->create_source))                  $config->create_source                  = 'CiviBanking';

    // membership_payment link
    if (!isset($config->link_as_payment))                $config->link_as_payment                = TRUE;   // link the contribution as a membership_payment
  }

  /**
   * @inheritDoc
   */
  protected function shouldExecute(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context,
    $preview = FALSE
  ) {
    if (!$preview) {
      $contributions = $this->getEligibleContributions($context);
      if (empty($contributions)) {
        $this->logMessage("No eligible contributions found.", "debug");
        return FALSE;
      }
    }
    elseif (
      empty($match->getParameter('contact_id'))
      && empty($match->getParameter('contact_ids'))
    ) {
      return FALSE;
    }

    // pass on to parent to check generic reasons
    return parent::shouldExecute($match, $matcher, $context, $preview);
  }

  public function previewMatch(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context
  ) {
    $preview = NULL;
    $config = $this->_plugin_config;
    if ($this->shouldExecute($match, $matcher, $context, TRUE)) {
      // Filter estimated contribution properties.
      $propagated_values = $match->getPlugin()->getPropagationSet(
        $context->btx,
        $match,
        'contribution'
      );
      if (
        (
          !array_key_exists('financial_type_id', $propagated_values)
          || in_array($propagated_values['financial_type_id'], $config->financial_type_ids)
        )
        && (
          !array_key_exists('payment_instrument_id', $propagated_values)
          || (
            (
              !is_array($config->payment_instrument_ids)
              || in_array($propagated_values['payment_instrument_id'], $config->payment_instrument_ids)
            )
            && (
              !is_array($config->payment_instrument_ids_exclude)
              || !in_array($propagated_values['payment_instrument_id'], $config->payment_instrument_ids_exclude)
            )
          )
        )
      ) {
        $contribution_dummy = [
          'contact_id' => $match->getParameter('contact_id'),
          'receive_date' => $context->btx->value_date,
          'total_amount' => $context->btx->amount,
        ];
        if ($contribution_dummy['contact_id']) {
          $memberships = $this->getEligibleMemberships(
            $contribution_dummy,
            $match,
            $context
          );
          if (!empty($memberships)) {
            $membership = reset($memberships);
            $preview = ' &ndash; '
              . E::ts(
                'A <a href="%1">membership</a> with status <em>%2</em> was found.',
                [
                  1 => CRM_Utils_System::url(
                    "civicrm/contact/view/membership?action=view&reset=1&cid={$membership['contact_id']}&id={$membership['id']}"
                  ),
                  2 => civicrm_api3(
                    'MembershipStatus',
                    'getvalue',
                    [
                      'id' => $membership['status_id'],
                      'return' => 'label',
                    ]
                  ),
                ]);

            // Denote the status the membership will be set to (set_status).
            if (!empty($config->set_status)) {
              $preview .= ' '
                . E::ts(
                  'Its status will be set to <em>%1</em>.',
                  [
                    1 => civicrm_api3(
                      'MembershipStatus',
                      'getvalue',
                      [
                        'id' => $config->set_status,
                        'return' => 'label',
                      ]
                    ),
                  ]
                );
            }

            if (!in_array($membership['status_id'], $config->skip_extend_status)) {
              $extend_from = $this->getMembershipExtensionAttribute(
                'extend_from',
                [
                  'extend_from' => $config->extend_from,
                  'end_date' => strtotime($membership['end_date']),
                  'receive_date' => strtotime($contribution_dummy['receive_date']),
                ]
              );
              $extend_by = $this->getMembershipExtensionAttribute(
                'extend_by',
                [
                  'extend_by' => $config->extend_by,
                  'membership_type_id' => $membership['membership_type_id'],
                ]
              );
              $extend_to = strtotime($extend_by, $extend_from);
              $preview .= ' '
                . E::ts(
                  'It will be extended from <em>%1</em> by <em>%2</em> until <em>%3</em>.',
                  [
                    1 => CRM_Utils_Date::customFormat(
                      date_create()->setTimestamp($extend_from)->format('Ymd'),
                      CRM_Core_Config::singleton()->dateformatFull
                    ),
                    2 => trim($extend_by, '+-'),
                    3 => CRM_Utils_Date::customFormat(
                      date_create()->setTimestamp($extend_to)->format('Ymd'),
                      CRM_Core_Config::singleton()->dateformatFull
                    ),
                  ]
                );
            }

            if ($config->align_end_date) {
              $preview .= ' '
                . E::ts(
                  'The end date will be aligned to the <em>%1</em>',
                  [1 => $config->align_end_date]
                );
            }

            if (count($memberships) > 1) {
              $preview .= '<div class="messages status no-popup">'
                . '<div class="icon inform-icon"></div>'
                . '<span class="msg-text">'
                . E::ts('More than one membership was found, only the first will be processed.')
                . '</span>'
                . '</div>';
            }
          }
          elseif ($config->create_if_not_found) {
            $create_start_date = $this->getMembershipExtensionAttribute(
              'create_start_date',
              [
                'create_start_date' => $config->create_start_date,
                'create_start_date_reference' => $config->create_start_date_reference,
                'receive_date' => $contribution_dummy['receive_date'],
              ]
            );
            $create_type_id = $this->getMembershipExtensionAttribute(
              'create_type_id',
              [
                'create_type_id' => $config->create_type_id,
                'btx' => $context->btx,
                'match' => $match,
              ]
            );
            $preview = ' &ndash; '
              . E::ts(
                'A new membership of the type <em>%1</em> will be created for the contact, starting from <em>%2</em>.',
                [
                  1 => self::getMembershipType($create_type_id)['name'],
                  2 => CRM_Utils_Date::customFormat(
                    date_create_from_format('Y-m-d', $create_start_date)->format('Ymd'),
                    CRM_Core_Config::singleton()->dateformatFull
                  )
                ]
              );
          }
        }
        elseif (!empty($match->getParameter('contact_ids'))) {
          $preview = ' &ndash; '
            . E::ts(
              'A membership for the selected contact might be extended'
            );
          if (
            $config->create_if_not_found
            && !empty($create_type_id = $this->getMembershipExtensionAttribute(
              'create_type_id',
              [
                'create_type_id' => $config->create_type_id,
                'btx' => $context->btx,
                'match' => $match,
              ]
            ))
          ) {
            $create_start_date = $this->getMembershipExtensionAttribute(
              'create_start_date',
              [
                'create_start_date' => $config->create_start_date,
                'create_start_date_reference' => $config->create_start_date_reference,
                'receive_date' => $contribution_dummy['receive_date'],
              ]
            );
            $preview .= ' '
              . E::ts(
                'or a new membership of the type <em>%1</em> might be created for the contact, starting from <em>%2</em>',
                [
                  1 => self::getMembershipType($create_type_id)['name'],
                  2 => CRM_Utils_Date::customFormat(
                    date_create_from_format(
                      'Y-m-d',
                      $create_start_date
                    )->format('Ymd'),
                    CRM_Core_Config::singleton()->dateformatFull
                  ),
                ]
              );
          }
          $preview .= '.';
        }
      }
    }
    return $preview;
  }

  /**
   * Postprocess the (already executed) match
   *
   * @param $match    CRM_Banking_Matcher_Suggestion  the executed match
   * @param $matcher  CRM_Banking_PluginModel_Matcher the related transaction
   * @param $context  CRM_Banking_Matcher_Context     the matcher context contains cache data and context information
   *
   * @throws Exception if anything goes wrong
   */
  public function processExecutedMatch(CRM_Banking_Matcher_Suggestion $match, CRM_Banking_PluginModel_Matcher $matcher, CRM_Banking_Matcher_Context $context) {
    $result = NULL;
    $config = $this->_plugin_config;

    // this is pretty straightforward
    if ($this->shouldExecute($match, $matcher, $context)) {
      $contributions = $this->getEligibleContributions($context);
      foreach ($contributions as $contribution) {
        // get memberships
        $memberships = $this->getEligibleMemberships($contribution, $match, $context);
        $membership = NULL;
        if (empty($memberships)) {
          // no membership found
          if (
            $config->create_if_not_found
            && !empty($create_type_id = $this->getMembershipExtensionAttribute(
              'create_type_id',
              [
                'create_type_id' => $config->create_type_id,
                'btx' => $context->btx,
                'match' => $match,
              ]
            ))
          ) {
            $this->logMessage("No membership identified for contribution [{$contribution['id']}]. Creating one...", 'debug');
            $membership = $this->createMembership($contribution, $create_type_id);
          } else {
            $this->logMessage("No membership identified for contribution [{$contribution['id']}].", 'debug');
            $result = FALSE;
          }

        } else {
          // memberships found
          if (count($memberships) > 1) {
            $this->logMessage("More than one membership identified for contribution [{$contribution['id']}]. Processing first!", 'debug');
            $result = FALSE;
          }

          // extend membership
          $membership = reset($memberships);
          $this->extendMembership($membership, $contribution);
        }
        if (!is_null($membership)) {
          // TODO: Add more information on what exactly has been done with the
          //   membership, depending on configuration and actual results.
          $result['memberships'][] = $membership;
        }
      }
    }
    else {
      $result = FALSE;
    }
    return $result;
  }

  public function visualizeExecutedMatch(
    CRM_Banking_Matcher_Suggestion $match,
    CRM_Banking_PluginModel_Matcher $matcher,
    CRM_Banking_Matcher_Context $context,
    $result
  ) {
    $return = $this->getName() . '<ul>';
    foreach ($result['memberships'] as $membership) {
      $url = CRM_Utils_System::url(
        'civicrm/contact/view/membership',
        [
          'action' => 'view',
          'id' => $membership['id']
        ]
      );
      // TODO: Elaborate on what exactly has been done, depending on $result.
      $return .= '<li>'
        . E::ts('The membership %1 has been extended or created.', [
          1 => '<a href="' . $url . '">#' . $membership['id'] . '</a>'
        ])
        . '</li>';
    }
    $return .= '</ul>';
    return $return;
  }


  /**
   * Create a new membership
   *
   * @param $contribution array contribution data
   * @throws CiviCRM_API3_Exception
   */
  protected function createMembership($contribution, $membership_type_id = NULL) {
    $config = $this->_plugin_config;
    // Keep backwards compatibility without the $membership_type_id parameter.
    $create_type_id = $membership_type_id ?: $config->create_type_id;

    $membership_data = [
        'contact_id'         => $contribution['contact_id'],
        'membership_type_id' => $create_type_id,
        'source'             => $config->create_source,
    ];

    // set start date
    $membership_data['start_date'] = $this->getMembershipExtensionAttribute(
      'create_start_date',
      [
        'create_start_date' => $config->create_start_date,
        'create_start_date_reference' => $config->create_start_date_reference,
        'receive_date' => $contribution['receive_date'],
      ]
    );

    // create the membership
    $this->logMessage("Creating membership: " . json_encode($membership_data), 'debug');
    $membership = civicrm_api3('Membership', 'create', $membership_data);

    // and link
    if ($config->link_as_payment) {
      $this->link($contribution['id'], $membership['id']);
    }

    return $membership;
  }

  /**
   * Extend an existing membership
   *
   * @param $membership   array membership data
   * @param $contribution array contribution data
   */
  protected function extendMembership($membership, $contribution) {
    $config = $this->_plugin_config;
    $params = [];

    if (!in_array($membership['status_id'], $config->skip_extend_status)) {
      // find out from which point it should be extended
      $new_end_date = $this->getMembershipExtensionAttribute(
        'extend_from',
        [
          'extend_from' => $config->extend_from,
          'end_date' => strtotime($membership['end_date']),
          'receive_date' => strtotime($contribution['receive_date'])
        ]
      );

      // now extend by the date
      $extend_by = $this->getMembershipExtensionAttribute(
        'extend_by',
        [
          'extend_by' => $config->extend_by,
          'membership_type_id' => $membership['membership_type_id'],
        ]
      );
      $this->logMessage("Extending membership [{$membership['id']}] by {$extend_by}", 'debug');
      $new_end_date = strtotime($extend_by, $new_end_date);

      // finally align the result
      if ($config->align_end_date == 'next_last') {
        $last_first = strtotime(date('Y-m-01', $new_end_date));
        $next_first = strtotime("+1 month", $last_first);
        $next_last  = strtotime("-1 day", $next_first);
        $new_end_date = $next_last;
        $this->logMessage("Aligned new end date to " . date('Y-m-d', $new_end_date), 'debug');
      } elseif ($config->align_end_date == 'last_last') {
        $last_first = strtotime(date('Y-m-01', $new_end_date));
        $last_last  = strtotime("-1 day", $last_first);
        $new_end_date = $last_last;
        $this->logMessage("Aligned new end date to " . date('Y-m-d', $new_end_date), 'debug');
      }

      $params['end_date'] = date('Y-m-d', $new_end_date);
    }

    try {
      // Adjust membership status parameters.
      $this->adjustStatus($params);

      // Finally, update the membership.
      if (!empty($params)) {
        civicrm_api3(
          'Membership',
          'create',
          $params + [
            'id' => $membership['id'],
          ]
        );
      }

      // Link payment with memebership.
      if ($config->link_as_payment) {
        $this->link($contribution['id'], $membership['id']);
      }
    } catch (Exception $ex) {
      $this->logMessage("Unexpected exception extending membership: " . $ex->getMessage(), 'warn');
    }
  }


  /**
   * Link the contribution to the membership, if they're not already linked
   *
   * @param $contribution_id int contribution ID
   * @param $membership_id   int membership ID
   */
  protected function link($contribution_id, $membership_id) {
    $contribution_id = (int) $contribution_id;
    $membership_id   = (int) $membership_id;
    if ($contribution_id && $membership_id)
      $already_linked = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_membership_payment WHERE contribution_id = %1 AND membership_id = %2 LIMIT 1;", [
          1 => [$contribution_id, 'Integer'],
          2 => [$membership_id,   'Integer']]);

      if ($already_linked) {
        $this->logMessage("Membership [{$membership_id}] and/or contribution [{$contribution_id}] already linked.", 'debug');
      } else {
        CRM_Core_DAO::executeQuery("INSERT INTO civicrm_membership_payment (contribution_id, membership_id) VALUES (%1, %2);", [
              1 => [$contribution_id, 'Integer'],
              2 => [$membership_id,   'Integer']]);
        $this->logMessage("Contribution [{$contribution_id}] linked to membership [{$membership_id}].", 'debug');
    }
  }

  /**
   * Adjust the status and the override behavior of the membership.
   *
   * @param array $params
   *   The parameters to extend with status params.
   */
  protected function adjustStatus(&$params) {
    $config = $this->_plugin_config;
    if (isset($config->status_override)) {
      $params['is_override'] = (int) $config->status_override;
    }
    if ($config->set_status) {
      $params['status_id'] = $config->set_status;
    }
  }

  /**
   * Get all memberships eligible for extension
   *
   * @param array                          $contribution  contribution data
   * @param CRM_Banking_Matcher_Suggestion $match         the executed match
   * @param CRM_Banking_Matcher_Context    $context       context
   * @return array memberships
   * @throws CiviCRM_API3_Exception
   */
  protected function getEligibleMemberships($contribution, $match, $context) {
    $config = $this->_plugin_config;

    // first: collect potential IDs
    $membership_ids = [];
    $memberships    = [];

    // OPTION 1: FIND VIA CONTRIBUTION CONTACT
    if ($config->find_via_contact) {
      $contact_id = (int) $contribution['contact_id'];
      if ($contact_id) {
        try {
          $contacts_memberships = civicrm_api3('Membership', 'get', [
              'contact_id'   => $contact_id,
              'return'       => ['id', 'status_id'],
              'option.limit' => 0]);
          foreach ($contacts_memberships['values'] as $contacts_membership) {
            $membership_ids[] = $contacts_membership['id'];
          }
        } catch(Exception $ex) {
          $this->logMessage("Find memberships by contact failed: " . $ex->getMessage(), 'warn');
        }
      }
    }

    // OPTION 2: FIND VIA MEMBERSHIP PAYMENT LINK
    if ($config->find_via_payment) {
      if (!empty($contribution['id'])) {
        try {
          $contribution_memberships = civicrm_api3('MembershipPayment', 'get', [
              'contribution_id'   => (int) $contribution['id'],
              'return'            => 'membership_id',
              'option.limit'      => 0]);
          foreach ($contribution_memberships['values'] as $contacts_membership) {
            $membership_ids[] = $contacts_membership['membership_id'];
          }
        } catch(Exception $ex) {
          $this->logMessage("Find memberships by payment link failed: " . $ex->getMessage(), 'warn');
        }
      }
    }

    // OPTION 2: FIND VIA TRANSACTION FIELD VALUE
    if ($config->find_via_btxfield) {
      $membership_id = (int) $this->getPropagationValue($context->btx, $match, $config->find_via_btxfield);
      if ($membership_id) {
        $membership_ids[] = $membership_id;
      }
    }


    // if we haven't found any IDs, we're done
    if (empty($membership_ids)) {
      return $memberships;
    }

    // NOW: compile membership query
    $membership_query['id'] = ['IN' => $membership_ids];
    $membership_query['option.limit'] = 0;

    if ($config->filter_current) {
      $membership_query['status_id'] = ['IN' => self::getCurrentStatusIDs()];
    }

    if ($config->filter_max_end_date) {
      if (empty($contribution['receive_date'])) {
        $this->logMessage("Contribution [{$contribution['id']}] has no receive_date, date filtering disabled.", 'warn');
      } else {
        $maximum_date = strtotime($config->filter_max_end_date, strtotime($contribution['receive_date']));
        $membership_query['end_date'] = ['<=' => date('Y-m-d', $maximum_date)];
      }
    }


    if (!empty($config->filter_status)) {
      if (is_array($config->filter_status)) {
        $membership_query['status_id'] = ['IN' => $config->filter_status];
      } else {
        $this->logMessage("Configuration option 'filter_status' is not an array! Ignored", 'warn');
      }
    }

    if (!empty($config->filter_membership_types)) {
      if (is_array($config->filter_membership_types)) {
        $membership_query['membership_type_id'] = ['IN' => $config->filter_membership_types];
      } else {
        $this->logMessage("Configuration option 'filter_membership_types' is not an array! Ignored", 'warn');
      }
    }

    // FINALLY: LOAD THE MEMBERSHIPS AND FILTER THEM SOME MORE
    $this->logMessage("Finding eligible memberships: " . json_encode($membership_query), 'debug');
    $memberships_found = civicrm_api3('Membership', 'get', $membership_query)['values'];
    foreach ($memberships_found as $membership_found) {
      if ($config->filter_minimum_amount === TRUE) {
        // compare with the membership type's minimum amount
        $membership_type = self::getMembershipType($membership_found['membership_type_id']);
        $minimum_fee = empty($membership_type['minimum_fee']) ? 0.00 : $membership_type['minimum_fee'];
        if ($contribution['total_amount'] < $minimum_fee) {
          $this->logMessage("Contribution [{$contribution['id']}] less than minimal fee", 'debug');
          continue;
        }

      } elseif ($config->filter_minimum_amount > 0) {
        // compare with the given amount
        if ($contribution['total_amount'] < $config->filter_minimum_amount) {
          $this->logMessage("Contribution [{$contribution['id']}] amount too low.", 'debug');
          continue;
        }
      }

      $memberships[] = $membership_found;
    }

    // finally: return our findings
    return $memberships;
  }


  /**
   * Get all eligible contributions wrt the provided filter criteria
   *
   * @param CRM_Banking_Matcher_Context $context
   * @return array contributions
   * @throws CiviCRM_API3_Exception
   */
  protected function getEligibleContributions($context) {
    $cache_key = "{$this->_plugin_id}_eligiblecontributions_{$context->btx->id}";
    $cached_result = $context->getCachedEntry($cache_key);
    if ($cached_result !== NULL) return $cached_result;

    $connected_contribution_ids = $this->getContributionIDs($context);
    if (empty($connected_contribution_ids)) {
      return array();
    }

    // compile a query
    $config = $this->_plugin_config;
    $contribution_query = array(
      'id'           => array('IN' => $connected_contribution_ids),
      'option.limit' => 0,
      'sequential'   => 1);

    // add financial types
    if (!empty($config->financial_type_ids && is_array($config->financial_type_ids))) {
      $contribution_query['financial_type_id'] = array('IN' => $config->financial_type_ids);
    }

    // add status ids
    if (!empty($config->contribution_status_ids && is_array($config->contribution_status_ids))) {
      $contribution_query['contribution_status_id'] = array('IN' => $config->contribution_status_ids);
    }

    // add status ids
    if (!empty($config->payment_instrument_ids && is_array($config->payment_instrument_ids))) {
      $contribution_query['payment_instrument_id'] = array('IN' => $config->payment_instrument_id);
    }

    // query DB
    $this->logMessage("Finding eligible contributions: " . json_encode($contribution_query), 'debug');
    $result = civicrm_api3('Contribution', 'get', $contribution_query);
    $contributions = array();

    foreach ($result['values'] as $contribution) {
      if (!empty($config->payment_instrument_ids_exclude && is_array($config->payment_instrument_ids_exclude))) {
        // check if we need to exclude it because of the payment instrument ID
        if (in_array($contribution['payment_instrument_id'], $config->payment_instrument_ids_exclude)) {
          $this->logMessage("Exclude contribution [{$contribution['id']}] for the payment instrument.", 'debug');
          continue;
        }
      }
      $contributions[] = $contribution;
    }

    // cache result
    $context->setCachedEntry($cache_key, $contributions);
    return $contributions;
  }


  /**
   * Get the list of status IDs that are considered 'current members'
   *
   * @return array
   */
  protected static function getCurrentStatusIDs() {
    if (self::$_current_status_ids === NULL) {
      self::$_current_status_ids = [];
      try {
        $result = civicrm_api3('MembershipStatus', 'get', [
            'is_current_member' => 1,
            'option.limit'      => 0,
            'return'            => 'id']);
        foreach ($result['values'] as $status) {
          self::$_current_status_ids[] = $status['id'];
        }
      } catch (Exception $ex) {
        CRM_Core_Error::debug_log_message("Unexpected error: " . $ex->getMessage());
      }
    }

    return self::$_current_status_ids;
  }


  /**
   * Get the membership type object
   *
   * @param $membership_type_id int membership type ID
   * @return array membership type
   */
  protected static function getMembershipType($membership_type_id) {
    if (self::$_membership_types === NULL) {
      try {
        self::$_membership_types = civicrm_api3('MembershipType', 'get', [
            'option.limit' => 0,
            'sequential'   => 0
        ])['values'];
      } catch (Exception $ex) {
        CRM_Core_Error::debug_log_message("Unexpected error: " . $ex->getMessage());
      }
    }

    return CRM_Utils_Array::value($membership_type_id, self::$_membership_types, NULL);
  }

  protected function getMembershipExtensionAttribute($attribute, $params) {
    $value = NULL;
    switch ($attribute) {
      case 'create_start_date':
        switch ($params['create_start_date_reference'])  {
          case 'receive_date':
            $start_date_reference = strtotime($params['receive_date']);
            break;
          default:
            $start_date_reference = strtotime($params['create_start_date_reference']);
            break;
        }
        switch ($params['create_start_date']) {
          case 'next_first':
            // Set the start_date to the first day of the next month.
            if (date('j') != 1) {
              $start_date_reference = strtotime("+1 month", $start_date_reference);
            }
            $value = date('Y-m-01', $start_date_reference);
            break;
          case 'last_first':
            // Set the start_date to the first day of the current month.
            $value = date('Y-m-01', $start_date_reference);
            break;
          case 'receive_date':
            // Set the start_date to receive_date.
            $value = date(
              'Y-m-d',
              strtotime($params['receive_date'])
            );
            break;
          default:
            // Use DateTime parser for creating the start_date.
            $value = date(
              'Y-m-d',
              strtotime($params['create_start_date'], $start_date_reference)
            );
            break;
        }
        break;
      case 'extend_from':
        switch ($params['extend_from']) {
          case 'min':
            $value = min($params['end_date'], $params['receive_date']);
            break;

          case 'end_date':
            $value = $params['end_date'];
            break;

          default:
          case 'payment_date':
          $value = $params['receive_date'];
            break;
        }
        break;
      case 'extend_by':
        if ($params['extend_by'] == 'period') {
          $membership_type = self::getMembershipType($params['membership_type_id']);
          if ($membership_type['duration_unit'] == 'lifetime') {
            $value = '+25 years';
          }
          else {
            $value = "+{$membership_type['duration_interval']} {$membership_type['duration_unit']}";
          }
        }
        else {
          $value = $params['extend_by'];
        }
        break;
      case 'create_type_id':
        if (is_numeric($params['create_type_id'])) {
          $value = $params['create_type_id'];
        }
        else {
          $value = (int) $this->getPropagationValue(
            $params['btx'],
            $params['match'],
            $params['create_type_id']
          );
        }
        break;
      default:
        throw new Exception('Unknown attribute name.');
    }

    return $value;
  }

}
