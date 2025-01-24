// (function($, Drupal) {
//   Drupal.behaviors.addProcessForm = {
//     attach: function (context, settings) {
//       // Seletor: todos os campos com .form-autocomplete, no contexto do Drupal.
//       $('input.form-autocomplete', context)
//         // 1) remove handlers anteriores para evitar duplicar
//         .off('autocompleteselect.addProcessForm')

//         // 2) registra o evento jQuery UI "autocompleteselect"
//         .on('autocompleteselect.addProcessForm', function(event, ui) {
//           // "ui.item" contém { label, value } ou algo similar
//           const valor = ui.item.value;
//           console.log('Valor selecionado no jQuery UI Autocomplete:', valor);

//           // Se quiser um alert:
//           const URI = /\[(.*?)\]/;
//           const resultado = valor.match(URI);
//           // alert('Você escolheu a opção: ' + resultado[1]);

//           // Se quiser pegar o ID do campo dinamicamente
//           console.log('Campo ID:', this.id);
//           console.log('Valor:', resultado[1]);
//         });
//     }
//   };
// })(jQuery, Drupal);

(function($, Drupal) {
  Drupal.behaviors.addProcessForm = {
    attach: function (context, settings) {

      var rootUrl = settings.sir_process_form.base_url;



      $('input.form-autocomplete', context)
        .off('autocompleteselect.addProcessForm')
        .on('autocompleteselect.addProcessForm', function(event, ui) {
          const valor = ui.item.value;
          console.log('Valor selecionado no jQuery UI Autocomplete:', valor);

          // Extraia a URI dentro de [ ... ] usando regex
          const match = valor.match(/\[(.*?)\]/);
          const instrumentUri = match ? match[1] : null;

          // Pega o ID do campo. Ex: "instrument_selected_0"
          const campoId = this.id;

          // Envia requisição AJAX para a rota custom
          $.ajax({
            url: rootUrl + '/sir/load-detectors', // mesma rota que definimos
            type: 'GET', // ou 'POST'
            data: {
              instrument_id: encodeURIComponent(instrumentUri)
            },
            success: function(response) {
              // Aqui você recebe o JSON que retornou do Controller
              if (response.status === 'success') {
                console.log('Detectors:', response.detectors);
                // Exemplo de atualizar algo no DOM
                const index = campoId.replace('instrument_selected_', '');
                const wrapperId = 'instrument_detector_wrapper_' + index;

                let html = '<ul>';
                for (const [label, value] of Object.entries(response.detectors)) {
                  html += `<li><input type="checkbox" name="detector_${index}" value="${value}">${label}</li>`;
                }
                html += '</ul>';

                $('#' + wrapperId).html(html);
              }
            },
            error: function(xhr, status, error) {
              console.error('Erro na requisição AJAX:', error);
            }
          });
        });
    }
  };
})(jQuery, Drupal);
