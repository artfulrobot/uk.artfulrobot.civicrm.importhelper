(function(angular, $, _) {

  angular.module('CsvImportHelper').config(function($routeProvider) {
      $routeProvider.when('/csv-import-helper', {
        controller: 'CsvImportHelperMain',
        templateUrl: '~/CsvImportHelper/Main.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          csvRecords: function(crmApi) {
            var v = crmApi('CsvHelper', 'get', {}).then(function(result) {
              return result.values;
            });
            return v;
          },
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
  angular.module('CsvImportHelper').controller('CsvImportHelperMain', function($scope, crmApi, crmStatus, crmUiHelp, myContact, csvRecords) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('importhelper');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/CsvImportHelper/Main'}); // See: templates/CRM/CsvImportHelper/Main.hlp

    $scope.csvRecords = csvRecords;

    $scope.uploadFile = function(event) {
      console.log("uploadFile running", event);
      var files = event.target.files;
      if (files.length == 1) {
        // $scope.$apply('showUploadForm = false');
        var files = event.target.files;
        console.log(files, $scope);
        var r = new FileReader();
        // Create closure so we can reference the file
        r.onload = (function(file) {

          // Start reading the file.
          r.readAsDataURL(file);

          // We're returning an event handler for r.onload
          return function(e) {
            var d = r.result;
            console.log("file loaded", file, d);
            // Send file to API.
            return crmStatus(
              // Status messages.
              {start: ts('Uploading...'), success: ts('Uploaded')},
              // The save action. Note that crmApi() returns a promise.
              crmApi('CsvHelper', 'upload', { data: d })
            )
            .then(function() { return crmApi('CsvHelper', 'get', {});} )
            .then(function(result) {
              console.log("updating ui...", result);
              //$scope.$apply(function() {
              $scope.csvRecords = result.values;
              console.log($scope.csvRecords);});
            //});

          };
        })(files[0]);
      }
    };

    // We have myContact available in JS. We also want to reference it in HTML.
    $scope.myContact = myContact;
    $scope.showUploadForm = true;
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
  })
  // This approach from http://stackoverflow.com/a/19647381/623519
  .directive('csvChange', function (){
    return {
      restrict: 'A',// only matches Attributes
      link: function (scope,  element, attrs) {
        console.log("directive fires", element, attrs);
        var onChangeHandler = scope.$eval(attrs.csvChange);
        console.log("handler", onChangeHandler);
        element.bind('change', onChangeHandler);
      }
    };
  });
  ;

})(angular, CRM.$, CRM._);
