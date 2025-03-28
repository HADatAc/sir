(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.openModalBehavior = {
    attach: function (context, settings) {

      once('openModal', 'a.open-modal', context).forEach(function(element) {
        $(element).on('click', function(e) {
          e.preventDefault();
          var downloadUrl = $(this).data('download-url');
          if (downloadUrl) {
            $('#docIframe', context).attr('src', downloadUrl);
            $('#docModal', context).dialog({
              modal: true,
              width: 800,
              height: 600,
              title: 'View Document'
            });
          }
        });
      });
    }
  };
})(jQuery, Drupal);
