(function(angular, $, _) {

  angular.module('banking').config(function($routeProvider) {
      $routeProvider.when('/banking/rules', {
        controller: 'BankingRules',
        templateUrl: '~/banking/Rules.html',
        resolve: {
          rule_config: function(crmApi, $route) {
            return crmApi('BankingRule', 'getruledata', {})
              .then(function(result) { return result.values; });
          }
        }
      });
      $routeProvider.when('/banking/rules/:rule_id', {
        controller: 'BankingRule',
        templateUrl: '~/banking/Rule.html',
        resolve: {
          rule_data: function(crmApi, $route) {
            return crmApi('BankingRule', 'getruledata', {
              id: $route.current.params.rule_id
            }).then(function(result) { return result.values; });
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  angular.module('banking').controller('BankingRules', function($scope, crmApi, crmStatus, crmUiHelp, rule_config) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('banking');
    var hs = $scope.hs = crmUiHelp({file: 'ang/banking/Rules'}); // See: templates/CRM/banking/Rules.hlp

    //
    // Initialise search criteria.
    //
    $scope.ui_data = {
      table_fields: [
        { field: 'ba_ref', label: 'Our IBAN '},
        { field: 'party_ba_ref', label: 'Party IBAN'},
        { field: 'party_name', label: 'Party Name'},
        { field: 'tx_reference', label: 'Reference'},
        { field: 'tx_purpose', label: 'Purpose'},
        { field: 'amount_min', label: 'Amount Min'},
        { field: 'amount_max', label: 'Amount Max'},
      ],
      plugin_config: rule_config.plugin_config
    };
    var criteria = {
      is_enabled: 'all',
      last_match_min: '',
      last_match_max: '',
      match_counter_min: '',
      match_counter_max: '',
      custom_conditions: [],
      conds_like: '',
      conds_like_enabled: false,
    };
    $scope.criteria = criteria;
    CRM._.each($scope.ui_data.table_fields, function (field) {
      criteria[field + '_enabled'] = false;
      criteria[field] = '';
    });
    criteria.execution = {};
    CRM._.each($scope.ui_data.plugin_config.fields_to_set, function (meta, fieldname) {
      criteria.execution[fieldname] = {
        enabled: false,
        field: fieldname,
        value:'',
        label: meta.label
      };
    });


    //
    // Initialise empty results.
    //
    $scope.pages = {
      total: null,
      limit: 10,
      sort: 'match_counter DESC',
      offset:0
    };
    $scope.results = [];

    //
    // Handle adding custom condition
    //
    $scope.addCustomCondition = function() {
      criteria.custom_conditions.push({ field:'', value:'' });
    };

    //
    // Common helper to assemble search criteria params.
    //
    function getSearchParams() {
      // Sanitise the offset.
      $scope.pages.offset = Math.max(0, $scope.pages.offset);
      $scope.pages.offset = Math.min(parseInt($scope.pages.total / $scope.pages.limit)*$scope.pages.limit, $scope.pages.offset);

      // Collect parameters.
      var params = {
        options: {
          offset: $scope.pages.offset,
          limit: $scope.pages.limit,
          sort: $scope.pages.sort
        }
      };

      // Table fields: if enabled, send parameter.
      CRM._.map($scope.ui_data.table_fields, function(field) {
        if (criteria[field.field + '_enabled']) {
          params[field.field] = criteria[field.field];
        }
      });

      // Build execution criteria.
      params.execution = {};
      CRM._.each(criteria.execution, function(execution) {
        if (execution.enabled) {
          params.execution[execution.field] = execution.value;
        }
      });

      // Add in custom conditions.
      params.conditions = {};
      CRM._.each(criteria.custom_conditions, function(cond) {
        if (cond.field) {
          params.conditions[cond.field] = cond.value;
        }
      });
      if (criteria.conds_like_enabled) {
        params.conds_like = criteria.conds_like;
      }

      // is_enabled
      if (criteria.is_enabled != 'all') {
        params.is_enabled = parseInt(criteria.is_enabled);
      }

      CRM._.each(['last_match_min', 'last_match_max', 'match_counter_min', 'match_counter_max'],
        function(fieldname) {
          if (criteria[fieldname] !== '') {
            params[fieldname] = criteria[fieldname];
          }
        });

      return params;
    }


    //
    // Shared code for dealing with incoming search results
    //
    var importSearchResults = function (results) {
      $scope.pages.offset = results.offset;
      $scope.pages.limit = results.limit;
      $scope.pages.total = results.total_count;
      $scope.results = results.rules;
    };

    //
    // Enter does search.
    //
    $scope.doSearchIfEnter = function(e) {
      if (e.key == 'Enter') {
        $scope.doSearch();
      }
    };

    //
    // Submit search request.
    //
    $scope.doSearch = function() {

      var params = getSearchParams();
      console.log("Searching with ", params);

      crmApi('BankingRule', 'getSearchResults', params)
      .then(importSearchResults);
    };

    //
    // Handle enable/disable on single rule.
    //
    $scope.setEnabled = function(rule) {
      if (!rule) {
        // This should not be possible.
        throw "No rule?!";
      }
      var params = {
        id: rule.id,
        is_enabled: rule.is_enabled ? 0 : 1,
      };

      return crmStatus(
        { start: ts('Saving...'), success: ts('Saved')},
        crmApi('BankingRule', 'update', params)
          .then(function(r) { rule.is_enabled = params.is_enabled; })
      );
    };

    //
    // Handle bulk enable/disable.
    //
    $scope.bulkSetEnabled = function(is_enabled) {
      if (!confirm(ts('Are you sure you want %s %n rules?', { 'n': $scope.pages.total, 's': is_enabled ? 'to ENABLE' : 'to DISABLE' }))) {
        return;
      }

      // OK to proceed.
      var params = getSearchParams();
      params.update = { is_enabled: is_enabled };

      return crmStatus(
        { start: ts('Updating Rules...'), success: ts('Complete')},
        crmApi('BankingRule', 'updatesearchresults', params)
        .then(importSearchResults)
      );
    };

    $scope.quicksearch = '';

  });

})(angular, CRM.$, CRM._);
