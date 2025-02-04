(function ($, Drupal) {
  Drupal.behaviors.detectorAjaxTrigger = {
    attach: function (context, settings) {
      $('.instrument-detector-ajax', context).off('change').on('change', function (e) {
        //console.log('Checkbox alterado:', $(this).val());

        const element = $(this);
        const ajaxSettings = {
          url: window.location.href,
          event: 'change',
          wrapper: element.attr('data-container-id'),
          progress: { type: 'throbber', message: null }
        };

        const ajaxInstance = new Drupal.Ajax(false, element[0], ajaxSettings);

        ajaxInstance.execute()
          .done(function () {
            console.log('AJAX concluído com sucesso.');
          })
          .fail(function (jqXHR, textStatus, errorThrown) {
            console.error('Erro no AJAX:', textStatus, errorThrown);
          });
      });
    }
  };
})(jQuery, Drupal);
