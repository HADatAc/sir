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

          let html = '';

          const index = campoId.replace('instrument_selected_', '');
          const wrapperId = 'instrument_detector_wrapper_' + index;

          // Envia requisição AJAX para a rota custom
          $.ajax({
            url: rootUrl + '/sir/load-detectors', // mesma rota que definimos
            type: 'GET', // ou 'POST'
            data: {
              instrument_id: encodeURI(instrumentUri),
            },
            success: function(response) {
              console.log('Response:', response);

              // Certifique-se de que `response` tem os dados esperados
              if (response && Array.isArray(response) && response.length > 0) {
                html += '<fieldset>';
                html += '<legend>Detectors on Instrument [' + instrumentUri + ']</legend>';

                // Inicia a tabela
                html += '<table border="1">';
                html += '<thead>';
                html += '<tr>';
                html += '<th>#</th>';
                html += '<th>Detector Label</th>';
                html += '<th>Detector Status</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';

                // Loop to create table rows
                response.forEach((item, index) => {
                  html += '<tr>';

                  // Selection column
                  html += `<td><input type="checkbox" name="detector_${index}" value="${item.uri}" ${item.status !== 'Draft' ? 'disabled' : ''}></td>`;

                  // Label column
                  html += `<td>${item.name || 'Unknown'}</td>`;

                  // Status column (assume 'Unknown' if null or not present)
                  html += `<td>${item.status || 'Unknown'}</td>`;
                  html += '</tr>';
                });

                html += '</tbody>';
                html += '</table>';

                html += '</fieldset>';

                // Add generated content to wrapper
                $('#' + wrapperId).html(html);
              } else {
                html += '<fieldset>';
                html += '<p style="font-weight:bold;">No detectors on this instrument [' + instrumentUri + ']</p>';
                html += '</fieldset>';
                $('#' + wrapperId).html(html);
                //console.error('Unexpected response format or no detectors on this instrument:', response);
              }
            },
            error: function(xhr, status, error) {
              console.error('Error retrieving detectors:', error);
            },
          });
        });
    }
  };
})(jQuery, Drupal);
