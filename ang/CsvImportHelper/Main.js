(function(angular, $, _) {

  angular.module('CsvImportHelper').config(function($routeProvider) {
      $routeProvider.when('/csv-import-helper', {
        controller: 'CsvImportHelperMain',
        templateUrl: '~/CsvImportHelper/Main.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          myContact: function(crmApi) {
            return crmApi('Contact', 'getsingle', {
              id: 'user_contact_id',
              return: ['first_name', 'last_name']
            });
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('CsvImportHelper').controller('CsvImportHelperMain', function($scope, crmApi, crmStatus, crmUiHelp, myContact) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('importhelper');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/CsvImportHelper/Main'}); // See: templates/CRM/CsvImportHelper/Main.hlp

    // We have myContact available in JS. We also want to reference it in HTML.
    $scope.myContact = myContact;

    $scope.save = function save() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Contact', 'create', {
          id: myContact.id,
          first_name: myContact.first_name,
          last_name: myContact.last_name
        })
      );
    };
  });

})(angular, CRM.$, CRM._);
