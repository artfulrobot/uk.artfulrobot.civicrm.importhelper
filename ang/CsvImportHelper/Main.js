(function(angular, $, _) {

  angular.module('CsvImportHelper').config(function($routeProvider) {
      $routeProvider.when('/csv-import-helper', {
        controller: 'CsvImportHelperMain',
        templateUrl: '~/CsvImportHelper/Main.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          csvRecords: function(crmApi) {
            var v = crmApi('CsvHelper', 'get').then(function(result) {
              console.log("LOAD", result.values);
              return result.values;
            });
            return v;
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('CsvImportHelper').controller('CsvImportHelperMain', function($scope, crmApi, crmStatus, crmUiHelp, csvRecords) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('importhelper');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/CsvImportHelper/Main'}); // See: templates/CRM/CsvImportHelper/Main.hlp

    $scope.csvRecords = csvRecords;
    $scope.CRM = CRM;
    $scope.selectedContact = function(row) {
      // find the contact id in the resolution.
      console.log("LOOKING for ", {id: row.contact_id}, "in ", row);
      var contact = _.find(row.resolution, { contact_id: row.contact_id });
      if (contact) {
        return "(" + contact.contact_id + ") " + contact.name;
      }
      else {
        return "failed to find contact for " + row.contact_id;
      }
    };

    $scope.uploadFile = function(event) {
      console.log("uploadFile running", event);
      var files = event.target.files;
      if (files.length == 1) {
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
              $scope.csvRecords = result.values;
              // @todo be really nice if we could send them to the 2nd tab now. Can anyone do a PR for that? @todo
            });
          };
        })(files[0]);
      }
    };
    $scope.recheck = function() {
      // e.g. after you've tweaked some data in CiviCRM.
      crmStatus( {start: ts('Re-scanning...'), success: ts('Finished')},
        crmApi('CsvHelper', 'rescan', {}))
      .then(function(result) {
        $scope.csvRecords = result.values;
        console.log("RESCAN", result.values);
      });
    };
    $scope.createContacts = function() {
      // Create contacts for those marked 'impossible'.
      crmStatus( {start: ts('Creating new contacts...'), success: ts('Finished')},
        crmApi('CsvHelper', 'createMissingContacts', {}))
      .then(function(result) {
        $scope.csvRecords = result.values;
      });
    };

    function updateContact(params, rowIndex) {
      // Update.
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Updating...'), success: ts('OK')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('CsvHelper', 'create', params)
      ).then(function (result) {
        // Update our data. This parent parent stuff is a little odd...
        $scope.csvRecords[rowIndex] = result.values;
      });
    }
    $scope.chooseContact = function(event) {
      return updateContact({
          id: this.$parent.$parent.row.id,
          contact_id: this.contact.contact_id,
          state: 'chosen',
      }, this.$parent.$parent.rowIndex);
    };
    $scope.unChooseContact = function(event) {
      return updateContact({
          id: this.$parent.$parent.row.id,
          contact_id: 0,
          state: 'multiple', // ??
      }, this.$parent.$parent.rowIndex);
    };
    $scope.dropData = function() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Clearing uploaded CSV data...'), success: ts('Clean!')},
        crmApi('CsvHelper', 'drop', {})
      ).then(function (result) {
        // Update our data.
        $scope.csvRecords = [];
      });
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
  })
  .directive('csvChoose', function (){
    return {
      restrict: 'A',// only matches Attributes
      link: function (scope,  element, attrs) {
        element.bind('change', onChangeHandler);
      }
    };
  })
  ;

})(angular, CRM.$, CRM._);
