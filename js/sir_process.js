// (function ($, Drupal) {
//   'use strict';

//   Drupal.behaviors.addProcessForm = {
//     attach: function (context, settings) {
//       // Seleciona todos os elementos que tenham ID iniciando com instrument_selected_,
//       // mas apenas dentro do contexto atual (ou seja, onde foi recém-inserido).
//       // E evita duplicar eventos nos mesmos elementos
//       once('addProcessForm', '[id^="instrument_selected_"]', context).forEach((element) => {
//         //alert('AddProcessForm Behavior rodou!');

//         // Adiciona o listener de foco
//         element.addEventListener('focus', function() {
//           const detectorWrapperId = this.id.replace('instrument_selected_', 'instrument_detector_wrapper_');

//           // Chama sua função
//           Drupal.behaviors.addProcessForm.loadDetectors(element, detectorWrapperId, 'formState');
//         });
//       });
//     },

//     loadDetectors: function(instrument, detectorWrapperId, formState) {
//       console.log('Instrument ID:', instrument.id);
//       console.log('Detector Wrapper ID:', detectorWrapperId);
//       console.log('Form State:', formState);
//       console.log('Element Value:', instrument.value);
//     }
//   };

// })(jQuery, Drupal);

(function($, Drupal) {
  Drupal.behaviors.addProcessForm = {
    attach: function (context, settings) {
      // Seletor: todos os campos com .form-autocomplete, no contexto do Drupal.
      $('input.form-autocomplete', context)
        // 1) remove handlers anteriores para evitar duplicar
        .off('autocompleteselect.addProcessForm')

        // 2) registra o evento jQuery UI "autocompleteselect"
        .on('autocompleteselect.addProcessForm', function(event, ui) {
          // "ui.item" contém { label, value } ou algo similar
          const valor = ui.item.value;
          console.log('Valor selecionado no jQuery UI Autocomplete:', valor);

          // Se quiser um alert:
          alert('Você escolheu a opção: ' + valor);

          // Se quiser pegar o ID do campo dinamicamente
          console.log('Campo ID:', this.id);
        });
    }
  };
})(jQuery, Drupal);

