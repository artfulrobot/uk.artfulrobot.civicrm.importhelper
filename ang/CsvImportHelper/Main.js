(function(angular, $, _, console) {

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
  angular.module('CsvImportHelper').controller('CsvImportHelperMain', function($scope, crmApi, crmStatus, crmUiHelp, csvRecords, $timeout) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('importhelper');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/CsvImportHelper/Main'}); // See: templates/CRM/CsvImportHelper/Main.hlp

    $scope.csvRecords = csvRecords;
    $scope.CRM = CRM;
    $scope.selectedContact = function(row) {
      // find the contact id in the resolution.
      console.log("LOOKING for ", {id: row.contact_id}, "in ", row.resolution);
      var contact = _.find(row.resolution, { contact_id: row.contact_id.toString() });
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
              // Send them to the 2nd tab now.
              $('#csv-import-helper-tabset').children().tabs('option', 'active', 1);
            });
          };
        })(files[0]);
        // Re-set the file input.
        event.target.value = '';
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
        // Update our data.
        console.log("results of update:", result.values);
        $scope.csvRecords[rowIndex] = result.values;
      });
    }
    // This is trigged by the user selecting a contact with the crmEntityref widget.
    $scope.chooseContact2 = function() {
      return updateContact({
          id: this.$parent.row.id,
          contact_id: this.$parent.row.contact_id,
          state: 'chosen',
      }, this.$parent.rowIndex);
    };
    // User chose someone from the resolutions list.
    $scope.chooseContact = function(event) {
      return updateContact({
          id: this.$parent.$parent.row.id,
          contact_id: this.contact.contact_id,
          state: 'chosen',
      }, this.$parent.$parent.rowIndex);
    };
    $scope.unChooseContact = function(event) {
      console.log("unChooseContact", this);
      return updateContact({
          id: this.$parent.row.id,
          contact_id: 0,
      }, this.$parent.rowIndex);
    };
    $scope.createNewContact = function(event) {
      console.log("createNewContact", this);
      var rowIndex = this.$parent.rowIndex;
      var cacheId = this.$parent.row.id;

      crmStatus( {start: ts('Creating new contact...'), success: ts('Contact Created')},
        crmApi('CsvHelper', 'createMissingContacts', { id: cacheId }))
      .then(function(result) {
        console.log("CREATE NEW ", result);
        // The result contains the new row.
        $scope.csvRecords[rowIndex] = result.values;
      });
    };
    $scope.countNoMatch = function() {
      return $scope.csvRecords.reduce(function(a, record) {
        return (record.state == 'impossible') ? a + 1 : a;
      },0);
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

    if ($scope.csvRecords.length > 0) {
      // Allow a mo for it to settle.
      $timeout(function() {
        // There is data, jump to 2nd tab.
        console.log("TRYING TO JUMPO TABS", $('#csv-import-helper-tabset'));
        $('#csv-import-helper-tabset').children().tabs('option', 'active', 1);
      }, 500);
    }
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

// Debugging:
// })(angular, CRM.$, CRM._, console );
})(angular, CRM.$, CRM._, { log: function (){}, warn: function(){}, error: console.error } );
