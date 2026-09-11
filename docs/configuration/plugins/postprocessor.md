Active post processors are being checked for whether they are likely to be
executed (skipping contribution-related checks, since there aren't any
contributions at preview time). The contribution-related checks are being
skipped (since there aren't any contributions at preview time). A preview for
each to-be-executed post processor is shown in each suggestion inside a
collapsed accordion element in the top-right corner of each suggestion. When
collapsed, only the number of to-be-executed post processors is shown (e.g. "2
Post processors"). Expanding the accordion will place it below the suggestion
markup and display whatever markup post processors are providing, or only their
name, if they don't have a specific implementation for previewing.

After confirming a suggestion, the active post processors will be executed
depending on the context. Post processors that have been executed will be
displayed. Post processors may show the actual result, otherwise a generic
message will be shown. If a post processor was executed, it doesn't necessarily
mean that data was changed. This depends on the behavior of each post processor.

!!! note
    This section is yet to be completed.

    TODO: Describe generic configuration properties for required values, value
    propagation, automatic execution, etc.

## Bank Accounts PostProcessor

!!! note
    This section is yet to be completed.

## Custom Actions PostProcessor

The Custom Actions PostProcessor can be used to execute custom actions.

The configuration of this post processor basically looks like this:

```JSON
{
  "actions": [
    {
      "type": "example",
      ...
    },
    ...
  ]
}
```

At the property `actions` is a list of action definitions. Each action
definition has a `type` and additional properties specific to the type of
action. The actions are executed in the specified order.

Currently, there's one supported action type `api4` to execute custom APIv4
calls.

### `api4` Action

The `api4` action allows to execute any
[CiviCRM APIv4 action](https://docs.civicrm.org/dev/en/latest/api/v4/usage/)
and map the result to the transaction data.

You might want to use the APIv4 explorer at URL path `/civicrm/api4` to build
and test the action and then transfer it to the configuration.

#### Parameters

Actions of type `api4` have the following parameters:
* `"entity"` - The APIv4 entity, e.g. `"Contact"`.
* `"action"` - The entity action, e.g. `"get"`.
* `"params"` - The APIv4 action parameters. It's possible to access values
  from the transaction and related data e.g. `@=btx.id` A string starting
  with `@=` is interpreted as expression. Expressions are explained below.
* `"result_map"` - Allows to map APIv4 result data into transaction data. The
  keys are the target field (e.g. `"btx.info"`) and the values are APIv4 result
  fields (e.g. `"id"`) or expressions to retrieve the required value from the
  result object (e.g. `"@=result.first()['contact_sub_type'][0] ?? NULL"`).
  Expressions are explained below.
* `"result_map_options"` - Options for the `result_map`:
  * `"use_all_results"` - `true` to retrieve all values of a field as array,
    otherwise only the value of the first result is used. (Default: `false`)
  * `"index_by"` - An APIv4 field name the resulting array is indexed by when
    `use_all_results` is enabled or the `column()` method is used in an
    expression (e.g. `"@=result.column('some_field')"`).
  * `"skip_empty_result"` - `false` to set `NULL` or the expression result to
    the transaction data, if the APIv4 call returned an empty result. By
    default, the transaction data is unchanged in that case.

If the `result_map` contains no expression and the called action is `get` the
APIv4 parameters `select` and `limit` will be determined automatically, if not
specified.

Apart from `btx` data can be accessed from fields of `match` as well as `ba` and
`party_ba`, if a bank accounts were determined or created. Additionally, `tmp`
can be used to pass a value from one action to another without persisting it,
e.g. `tmp.my_id`.

#### Example
<a id="postprocessor-custom-actions-api4-example"></a>

```json
{
  "_comment": "Look for previous contributions with matching bank name and amount",
  "type": "api4",
  "entity": "Contribution",
  "action": "get",
  "params": {
    "select": ["id", "financial_type_id"],
    "orderBy": {
      "receive_date": "DESC"
    },
    "where": [
      [
        "Donor_Information.Bank_Name",
        "=",
        "@=btx.bank_name"
      ],
      [
        "total_amount",
        "=",
        "@=btx.amount"
      ],
      [
        "id",
        "!=",
        "@=match.contribution_id"
      ]
    ]
  },
  "result_map": {
    "btx.previous_contribution_ids": "@=implode(',', result.column()['id'])",
    "btx.previous_financial_type_id": "financial_type_id"
  },
  "result_map_options": {
    "skip_empty_result": false
  }
}
```

In this example all previous contributions where the custom field
`Donor_Information.Bank_Name` is equal to the `bank_name` field in the
transaction data and the `total_amount` is equal to the `amount` in the
transaction data are fetched in descendant order by `receive_date`.

The IDs of the fetched contribution will be set comma-separated in
`btx.previous_contribution_ids` and the `financial_type_id` of the first fetched
contribution (i.e. the matching contribution with the latest `receive_date`)
will be set in `btx.previous_financial_type_id`.

Because the result map option `skip_empty_result` is set to `false`, an empty
array will be set to `btx.previous_contribution_ids` and `NULL` will be set to
`btx.previous_financial_type_id`, if no matching contribution is found.

#### Expressions

Strings starting with `@=` in the APIv4 `params` and the `result_map` are
interpreted using the Symfony Expression Language. So there are many more
possibilities available than used in the
[example](#postprocessor-custom-actions-api4-example) above. Please have a look
at the [syntax
documentation](https://symfony.com/doc/current/reference/formats/expression_language.html)
for more details. Additionally, the function `implode()` is available (see the
[example](#postprocessor-custom-actions-api4-example)).

## Update Address PostProcessor

!!! note
    This section is yet to be completed.

## Membership Extension PostProcessor

You can extend a membership when a payment is recorded with CiviBanking or when
a payment is set to completed by creating a _Membership Extension post
processor_.

### Configuration options

Below a list of the possible configuration options.

| Option                           | Possible Values                          | Default         | Description                                                                                                                                                                                                                                                      |
|----------------------------------|------------------------------------------|-----------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `financial_type_ids`             | _list of ids_                            | 2 (Member Dues) | Only extend a membership when the payment has this financial type                                                                                                                                                                                                |
| `contribution_status_ids`        | _list of contribution status ids_        | 1 (Completed)   | Only extend a membership when the payment has this status                                                                                                                                                                                                        |
| `payment_instrument_ids`         | _list of payment instrument ids          | _empty_         | Only extend a membership when the payment is recorded with one of those payment instruments                                                                                                                                                                      |
| `payment_instrument_ids_exclude` | _list of payment instrument ids          | _empty_         | Only extend a membership when the payment is *not* recorded with one of those payment instruments                                                                                                                                                                |
| `find_via_contact`               | 1 / 0                                    | 1               | Find a membership based on the contact of the payment                                                                                                                                                                                                            |
| `find_via_payment`               | 1 / 0                                    | 1               | Find a membership through the link between the payment and the membership. Only useful when you are updating existing contributions                                                                                                                              |
| `find_via_btxfield`              | _empty_ or _field name of membership id_ | membership_id   | Find the membership by a field in the banking transaction. Only useful when other matchers/importers/post processors set a field in the banking transaction                                                                                                      |
| `filter_current`                 | 1 / 0                                    | 1               | Only update a membership when it has a current status. You can set the class of status under Administer -> CiviMember -> Membership Status Rules                                                                                                                 |
| `filter_minimum_amount`          | _empty_ or True or a monetary amount     | True            | Only extend a membership when the payment has the minimum amount of the membership type or when the payment has the minum amount specified here. You can also disable the check for minimum amount                                                               |
| `filter_membership_types`        | _list of ids_                            | _empty_         | If set only extend memberships of this type                                                                                                                                                                                                                      |
| `filter_max_end_date`            | _date_                                   | 3 months        | Membership end date should not be after 3 months of contribution receive date                                                                                                                                                                                    |
| `extend_by`                      | _period_ or strtotime offset             | period          | When set to period the membership is extend by the membership type period definition. If set to a strtotime value (e.g. +1 month) it is extended by this value                                                                                                   |
| `extend_from`                    | min or end_date or payment_date          | min             | When set to _payment_date_ the membership is extended from the contribution receive date. If set to _end_date_ the membership is extended by the end date of the membership. If set to _min_ then it is extended by the minmum value of end_date or payment_date |
| `align_end_date`                 | next_last or last_last                   | _empty_         | _Not sure how this option works_                                                                                                                                                                                                                                 |
| `create_if_not_found`            | 1 / 0                                    | 0               | Create a new membership when no membership is found                                                                                                                                                                                                              |
| `create_type_id`                 | _id_                                     | 1               | When a new membership is created give it this membership type                                                                                                                                                                                                    |
| `create_start_date`              | receive_date, next_first, last_first     | receive_date    | _Not sure how this option works_                                                                                                                                                                                                                                 |
| `create_source`                  | _any text_                               | CiviBanking     | This is the value set to the source of the membership when a new one is created                                                                                                                                                                                  |
| `link_as_payment`                | 1 / 0                                    | 1               | When set the contribution is linked to the membership                                                                                                                                                                                                            |

### Example configuration

Below is an example configuration for this post processor.

```json
{
  "financial_type_ids": [1],
  "contribution_status_ids": [2],
  "payment_instrument_ids": [],
  "payment_instrument_ids_exclude": [],
  "find_via_contact": 1,
  "find_via_payment": 1,
  "find_via_btxfield": "membership_id",
  "filter_current": 1,
  "filter_minimum_amount": 1,
  "filter_membership_types": [],
  "filter_max_end_date": "3 months",
  "extend_by": "period",
  "extend_from": "end_date",
  "link_as_payment": 0
}
```

## MembershipPayment PostProcessor

!!! note
    This section is yet to be completed.

## API PostProcessor

!!! note
    This section is yet to be completed.

## Contact Deceased PostProcessor

!!! note
    This section is yet to be completed.

## Recurring Contribution Fails PostProcessor

!!! note
    This section is yet to be completed.
