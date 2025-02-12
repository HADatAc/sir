(function ($, Drupal) {
  Drupal.behaviors.detectorstemForm = {
    attach: function (context, settings) {
      setTimeout(function () {
        $('#detectorstem_was_generated_by').on('change', function () {
          var selectedValue = $(this).val();
          var translation = 'http://hadatac.org/ont/vstoi#Translation';
          if (selectedValue === translation) {
            $('#detectorstem_language').val('').trigger('change');
          }
        });
      }, 1000); // Espera 1 segundo antes de ligar o evento
    }
  };
})(jQuery, Drupal);
