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
        { field: 'tx_purpose', label: 'Purpose'}
      ],
      plugin_config: rule_config.plugin_config
    };
    var criteria = {
      is_enabled: 'all',
      last_match_min: '',
      last_match_max: '',
      match_counter_min: '',
      match_counter_max: '',
      custom_conditions: []
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
      order: 'match_count DESC',
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
    // Submit search request.
    //
    $scope.doSearch = function() {

      // Sanitise the offset.
      console.log($scope.pages.offset);
      $scope.pages.offset = Math.max(0, $scope.pages.offset);
      console.log($scope.pages.offset);
      $scope.pages.offset = Math.min(parseInt($scope.pages.total / $scope.pages.limit)*$scope.pages.limit, $scope.pages.offset);
      console.log("final", $scope.pages.offset);

      // Collect parameters.
      var params = {
        options: {
          offset: $scope.pages.offset,
          limit: $scope.pages.limit,
          order: $scope.pages.order,
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

      console.log("Searching with ", params);

      crmApi('BankingRule', 'getSearchResults', params)
      .then(function(results) {
        $scope.pages.offset = results.offset;
        $scope.pages.limit = results.limit;
        $scope.pages.total = results.total_count;
        $scope.results = results.rules;
      });
    };

    $scope.quicksearch = '';

  });

})(angular, CRM.$, CRM._);
