(function ($, Drupal) {
  Drupal.behaviors.processstemForm = {
    attach: function (context, settings) {
      setTimeout(function () {
        var previousValue = ''; // Store the previous value before any change

        $('#cancel_button').on('click', function () {
          var $languageField = $('#processstem_language');
          $languageField.prop('required', false);
        });

        $('#processstem_was_generated_by').on('change', function () {
          var selectedValue = $(this).val();
          var translation = 'http://hadatac.org/ont/vstoi#Translation';
          var $languageField = $('#processstem_language');
          var $errorMessage = $('#processstem_language_error');

          if (selectedValue === translation) {
            // Save the current value before resetting it
            previousValue = $languageField.val();

            // Set the field as required and clear the selection
            $languageField.prop('required', true).val('').trigger('change');

            // Disable the previously selected option
            $languageField.find('option').each(function () {
              if ($(this).val() === previousValue) {
                $(this).prop('disabled', true);
              }
            });

            // Display error message if it does not already exist
            if (!$errorMessage.length) {
              $languageField.after('<div id="processstem_language_error" style="color: red;">Must select a different Language.</div>');
            }

          } else {
            // Remove the required attribute
            $languageField.prop('required', false);

            // Re-enable all options in the select field
            $languageField.find('option').prop('disabled', false);

            // Restore the previously selected value if it exists
            if (previousValue) {
              $languageField.val(previousValue).trigger('change');
            }

            // Remove the error message
            $('#processstem_language_error').remove();
          }
        });
      }, 1000);
    }
  };
})(jQuery, Drupal);
