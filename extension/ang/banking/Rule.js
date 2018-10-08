(function(angular, $, _) {
  angular.module('banking').controller('BankingRule', function($scope, crmApi, crmStatus, crmUiHelp, rule_data) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('banking');
    var table_fields = ['party_ba_ref', 'ba_ref', 'party_name', 'tx_reference', 'tx_purpose'];

    // Enhance the rule data
    CRM._.map(table_fields, function (field) {
      rule_data[field + '_enabled'] = rule_data[field] !== null;
    });
    rule_data.amount_operator      = (rule_data.amount_min == rule_data.amount_max) ? 'equals' : 'between';
    rule_data.amount_enabled       = (rule_data.amount_min !== null);
    // Actions.
    // For the checkboxes we need to know which are set.
    var execution = [];
    // Execution is an array of objects, each with a set_param_name and set_param_value key.
    // We need to know for each of the configured fields_to_set which ones are used.
    // Create an index in an object so we can look up whether a field is in the rule.
    var execution_index = {};
    CRM._.map(rule_data.execution, function(v, k) {
      execution_index[v.set_param_name] = v.set_param_value;
    });
    CRM._.map(rule_data.plugin_config.fields_to_set || {}, function (v, k) {
      execution.push({field: k, label: v.label, enabled: (k in execution_index), value: execution_index[k] || v.default || '', options: v.options || false, default: v.default || '' });
    });
    // Replace the original structure with our new one.
    rule_data.execution = execution;

    // Custom conditions are placed in an array.
    rule_data.custom_conditions = CRM._.map((rule_data.conditions || {}), function(v, k) {
      return { name: k, full_match: v.full_match, error: '' };
    });

    $scope.addCustomCondition = function() {
      rule_data.custom_conditions.push( {name: '', full_match: '', error: '' });
    };

    $scope.rule_data = rule_data;

    $scope.resetUsage = function () {
      rule_data.match_counter = 0;
      rule_data.last_match = null;
      $scope.save();
    };
    $scope.setEnabled = function (enabled) {
      rule_data.is_enabled = enabled;
      $scope.save();
    };
    $scope.save = function save() {
      // The save action. Note that crmApi() returns a promise.

      // Create the update API call.
      var params = { id: rule_data.id };

      // Main table text fields.
      CRM._.map(table_fields, function (field) {
        params[field] = rule_data[field + '_enabled'] ? (rule_data[field] || '') : null;
      });

      // Name
      params.name = rule_data.name || '';
      params.is_enabled = rule_data.is_enabled;
      params.last_match = rule_data.last_match;
      params.match_counter = rule_data.match_counter;

      // Amount.
      params.amount_min = rule_data.amount_enabled ? rule_data.amount_min : null;
      params.amount_max = rule_data.amount_enabled
        ? ((rule_data.amount_operator == 'between') ? rule_data.amount_max : rule_data.amount_min)
        : null;

      // Custom conditions.
      // These are sent as a JSON string.
      params.conditions = {};
      var errors = [];
      CRM._.map(rule_data.custom_conditions, function(cond, i) {
        if (cond.error) {
          errors.push("Error on custom condition " + (i+1) + ": " + cond.error);
        }
        else if (cond.name === '') {
          errors.push("Custom condition " + (i+1) + ": is missing a fieldname");
        }
        params.conditions[cond.name] = { full_match: cond.full_match };
      });

      if (errors.length > 0) {
        CRM.alert(errors.join("; "), 'Errors are preventing saving the rule', 'error');
        return;
      }

      // Execution.
      params.execution = CRM._.map(CRM._.filter(rule_data.execution, 'enabled'), function(item) {
        if (item.enabled) {
          return { set_param_name: item.field, set_param_value: item.value };
        }
      });

      return crmStatus(
        {start: ts('Saving...'), success: ts('Saved')}, crmApi('BankingRule', 'update', params)
      );
    };
    $scope.$watch(
      function() { return rule_data.custom_conditions.map(function(i) { return i.name; }); },
      function(newValue, oldValue, scope) {

        var counts = {};
        var n, i;
        for (i in rule_data.custom_conditions) {
          n = rule_data.custom_conditions[i].name;
          if (n in counts) {
            counts[n]++;
          }
          else {
            counts[n] = 1;
          }
        }

        for (i in rule_data.custom_conditions) {
          n = rule_data.custom_conditions[i].name;
          rule_data.custom_conditions[i].error = (counts[n] > 1) ? 'Duplicate condition name' : '';
        }
      },
      true);
  });

})(angular, CRM.$, CRM._);

