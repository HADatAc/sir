(function ($) {
  Drupal.behaviors.myModule = {
    attach: function (context, settings) {
      // Get the base URL from drupalSettings
      //var rootUrl = settings.mymodule.base_url;
      var rootUrl = settings.sir_select_form.base_url;


      $(document).ready(function() {
          $('#searchinstrumentbytypebtn').hide();
          $('#questionnarieblock').hide();
          $('#scaleblock').hide();
          $('#symptomblock').hide();
          $('#searchinstrumenttype').change(function(){
              $('#searchinstrumentbytypebtn').show();
              $('#questionnarieblock').hide();
              $('#scaleblock').hide();
              $('#symptomblock').hide();
              switch($('#searchinstrumenttype').val()){
                  case"questionnaries":$('#questionnarieblock').show();
                  break;
                  case"scales":$('#scaleblock').show();
                  break;
                  case"symptoms":$('#symptomblock').show();
                  break;
              }
          });
      });

      $("#searchinstrumentbytypebtn" ).click(function() {
          updateinstruments();
      });

      function updateinstruments() {
        let typeofsearch = $('#searchinstrumenttype').val();
        let questionnariename = $('#questionnariename').val();

        var data = {
            'typeofsearch': typeofsearch,
            'questionnariename': questionnariename
          };

          // Send the AJAX request.
          jQuery.ajax({
            type: 'POST',
            url: rootUrl+'/sir/ajax/searchinstruments',
            data: JSON.stringify(data),
            contentType: "application/json",
            success: function (response) {
                $('#searchinstrumentscontent').html(response);
            },
            error: function () {
              console.log('An error occurred while processing the request.');
            },
            dataType: 'json'
          });
      }

    }
  };
})(jQuery);

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.sirSelectForm = {
    attach: function (context, settings) {

      const updateButtons = () => {
        // States
        const draftChecked = document.querySelectorAll('.checkbox-status-draft:checked').length;
        const currentChecked = document.querySelectorAll('.checkbox-status-current:checked').length;
        const deprecatedChecked = document.querySelectorAll('.checkbox-status-deprecated:checked').length;
        const underReviewChecked = document.querySelectorAll('.checkbox-status-underreview:checked').length;

        // Buttons
        const reviewButton = document.getElementById('review-selected-button');
        const deleteButton = document.getElementById('edit-delete-selected-element');
        const editButton = document.getElementById('edit-edit-selected-element');
        const manageCodeBookSlotsButton = document.getElementById('manage-codebookslots-button');
        const manageStructureButton = document.getElementById('edit-manage-slotelements');

        if (reviewButton) {
          reviewButton.disabled = !(draftChecked > 0 && currentChecked === 0 && deprecatedChecked === 0);
        }

        if (deleteButton) {
          deleteButton.disabled = deprecatedChecked > 0;
        }

        if (editButton) {
          editButton.disabled = deprecatedChecked > 0 || underReviewChecked > 0;
        }

        if (manageCodeBookSlotsButton) {
          manageCodeBookSlotsButton.disabled = !(draftChecked > 0 && underReviewChecked === 0);
        }

        if (manageStructureButton) {
          manageStructureButton.disabled = !(draftChecked > 0 && underReviewChecked === 0);
        }
      };

      updateButtons();

      once('sirSelectForm', '.checkbox-status-draft, .checkbox-status-current, .checkbox-status-deprecated, .checkbox-status-underreview', context)
        .forEach(element => {
          element.addEventListener('change', updateButtons);
        });
    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.removeCheckboxMargin = {
    attach: function (context, settings) {
      $('.js-form-type-checkbox').removeClass('mb-3');
      $('.table.table-striped.responsive-enabled').addClass('align-middle');
    }
  };
})(jQuery, Drupal);
