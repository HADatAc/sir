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
      // Seleciona todos os campos que tenham a classe 'form-autocomplete' no contexto atual
      $('input.form-autocomplete', context)
        // Remover quaisquer event handlers anteriores no namespace .myAutocomplete
        .off('autocompleteSelect.addProcessForm')

        // Registra novamente o evento autocompleteSelect no namespace .myAutocomplete
        .on('autocompleteSelect.addProcessForm', function (event, selectedItem) {
          // 'this' é o próprio <input> onde o usuário está digitando
          // 'selectedItem' é o <li> do autocomplete que foi clicado / selecionado

          const valorSelecionado = $(this).val();
          console.log('Campo ID:', this.id, ' – Valor selecionado:', valorSelecionado);

          // Se quiser ver o texto exato que está no <li> da lista de sugestões:
          console.log('Texto do <li> selecionado:', $(selectedItem).text());

          // Ação que você quer disparar:
          alert('Você escolheu a opção do autocomplete: ' + valorSelecionado);

          // Chama sua função
          Drupal.behaviors.addProcessForm.loadDetectors(this, detectorWrapperId, 'formState');
        });
    }
  };
})(jQuery, Drupal);
