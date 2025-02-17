(function ($, Drupal) {
  Drupal.behaviors.floatingLabels = {
    attach: function (context, settings) {
      $('.floating-label-input', context).each(function () {
        var $input = $(this);
        var $container = $input.closest('.floating-container');
        var $label = $container.find('label'); // Find the generated label

        // Move the label inside the container (if not already inside)
        if ($label.length && !$label.hasClass('floating-label-text')) {
          $label.addClass('floating-label-text').prependTo($container);
        }

        // Check on load: Keep label above if there's already a value
        if ($input.val().trim() !== '') {
          $label.addClass('active').css({'opacity': 1, 'top': '-1px'});
        }

        // On focus: Hide placeholder, move label above
        $input.on('focus', function () {
          $input.attr('placeholder', ''); // Hide placeholder
          $label.addClass('active').css({'opacity': 1, 'top': '-1px', 'font-size': '16px', 'color': '#333'});
        });

        // On blur: Restore placeholder if empty, hide label
        $input.on('blur', function () {
          if ($input.val().trim() === '') {
            $input.attr('placeholder', $label.text()); // Restore placeholder
            $label.removeClass('active').css({'opacity': 0, 'top': '50%'});
          }
        });
      });
    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.removeCheckboxMargin = {
    attach: function (context, settings) {
      $('.js-form-wrapper').removeClass('mb-3');
      $('.table.table-striped.responsive-enabled').addClass('align-middle');
    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.collapseHeader = {
    attach: function (context, settings) {
      $('.collapsible-header', context).on('click', function () {
        var $header = $(this);
        var $content = $header.next('.collapsible-content');

        $content.toggleClass('d-none'); // Toggle visibility
        $header.toggleClass('collapsed'); // Change arrow direction
      });

      $('.collapsible-footer', context).on('click', function () {
        var $footer = $(this);
        var $content = $footer.next('.collapsible-content');

        $content.toggleClass('d-none'); // Toggle visibility
        $footer.toggleClass('collapsed'); // Change arrow direction
      });
    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  Drupal.behaviors.autoSaveForm = {
    attach: function (context, settings) {
      $('input[type="text"]', context)
        .off('change.autoSaveForm')
        .on('change.autoSaveForm', function () {
          // Capture the ID or, if not present, the name of the element that triggered the event.
          var triggeringElement = $(this).attr('id') || $(this).attr('name');
          console.log("Triggered by element: ", triggeringElement);

          // Find the hidden field.
          var $hidden = $('#auto-save-trigger');
          if ($hidden.length === 0) {
            console.warn("Hidden field #auto-save-trigger not found!");
          } else {
            // Set the value in the hidden field.
            $hidden.val(triggeringElement);
            console.log("Hidden field value set to: ", $hidden.val());
          }

          // Trigger the auto-save button.
          $('#auto-save-button').click();
        });
    }
  };
})(jQuery, Drupal);




