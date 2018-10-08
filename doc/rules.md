# CiviBanking Rules

This runs as both analyser and matcher, each being a different instance of the Matcher plugin.

1. Transactions are analysed

2. The rules analyser takes each transaction and executes rules that match, resulting in enriched btx data.

    1. SQL is used to quickly narrow down the possible matches of rules based on some common fields.
    2. Further `conditions` are optionally applied to further filter the matched rules.
    3. Those rules are then executed.

3. Unlike other analysers which do not create suggestions and therefore run without any UI, this analyser can, if configured to do so, create a suggestion.

4. The suggestion's UI panel does two things:
    1. It shows the user which rules have been executed. (when the suggestion is created a list of rule ids that matched is stored with it)
    2. It offers the user the option to create a new rule.

5. Users make use the UI to create a new rule.
    1. The fields are pre-filled for the particular transaction being reconciled and the user can edit these or remove them from the rule.
    2. A 'test' button provides the user creating the rule to check whether their new rule matches the current transaction or not. (Usually the rule should match, but it is possible that the user does need to create one that does not match.)

6. When the user clicks **Confirm and Continue** the rule is created and either the analysers are re-run, or the user is informed that they should re-run the analysers using the **Analyse (again)** button.

## Configuration

Example:

    {
      "suggest_create_new": true,
      "show_matched_rules": true,
      "create_new_confidence": 0.8,
      "fields_to_set": {
        "contact_id": {
          "label": "Contact"
        },
        "membership_id": {
          "label": "Membership ID"
        }
      }
    }

- `suggest_create_new` (bool) Should this plugin offer the Create New Rule UI?

- `show_matched_rules` (bool) Should this plugin show any matched rules?

- `create_new_confidence` (float) FIXME

- `fields_to_set` (obj) Which (contribution) fields should be set on execution
  of this rule. This is keyed by the field name and the value is a structure
  with keys:

   - `label` (string) The untranslated name in the UI.

   - (others not yet implemented)
