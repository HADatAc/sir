(function ($, Drupal, once) {
  Drupal.behaviors.addProcessForm = {
    attach: function (context, settings) {
      once('instrumentDetectorsAjaxTrigger', 'input.instrument-detector-ajax', context)
        .forEach(function (element) {
          $(element).on('change', function () {
            var $checkbox = $(element);
            var ajaxInstance = $checkbox.data('ajax');

            if (ajaxInstance) {
              ajaxInstance.execute();
            } else {
              if (!$checkbox.attr('id')) {
                $checkbox.attr('id', 'instrument-detector-' + (new Date()).getTime());
              }

              var containerId = $checkbox.closest('[id]').attr('id');

              var config = {
                base: $checkbox.attr('id'),
                element: $checkbox[0],
                event: 'change',
                url: Drupal.url('sir/manage/addprocess/instrument'),
                wrapper: containerId,
                method: 'replaceWith',
                effect: 'fade',
                progress: { type: 'none' }
              };

              var newAjaxInstance = new Drupal.ajax(config);
              $checkbox.data('ajax', newAjaxInstance);
              newAjaxInstance.execute();
            }
          });
        });
    }
  };
})(jQuery, Drupal, window.once);
