(function ($) {
  Drupal.behaviors.myModule = {
    attach: function (context, settings) {
      // Get the base URL from drupalSettings
      //var rootUrl = settings.mymodule.base_url;
      var rootUrl = settings.sir_select_form.base_url;


      $(document).ready(function() {
          $('#searchinstrumentbytypebtn').hide();
          $('#questionnarieblock').hide();
          $('#scaleblock').hide();
          $('#symptomblock').hide();
          $('#searchinstrumenttype').change(function(){
              $('#searchinstrumentbytypebtn').show();
              $('#questionnarieblock').hide();
              $('#scaleblock').hide();
              $('#symptomblock').hide();
              switch($('#searchinstrumenttype').val()){
                  case"questionnaries":$('#questionnarieblock').show();
                  break;
                  case"scales":$('#scaleblock').show();
                  break;
                  case"symptoms":$('#symptomblock').show();
                  break;
              }
          });
      });

      $("#searchinstrumentbytypebtn" ).click(function() {
          updateinstruments();
      });

      function updateinstruments() {
        let typeofsearch = $('#searchinstrumenttype').val();
        let questionnariename = $('#questionnariename').val();

        var data = {
            'typeofsearch': typeofsearch,
            'questionnariename': questionnariename
          };

          // Send the AJAX request.
          jQuery.ajax({
            type: 'POST',
            url: rootUrl+'/sir/ajax/searchinstruments',
            data: JSON.stringify(data),
            contentType: "application/json",
            success: function (response) {
                $('#searchinstrumentscontent').html(response);
            },
            error: function () {
              console.log('An error occurred while processing the request.');
            },
            dataType: 'json'
          });
      }

    }
  };
})(jQuery);

/*Infinite Scroll*/
(function ($, Drupal) {
  Drupal.behaviors.sirInfiniteScroll = {
    attach: function (context, settings) {
      // Verificar se o comportamento já está anexado
      if (window.infiniteScrollInitialized) {
        return;
      }
      window.infiniteScrollInitialized = true; // Define a flag como true para indicar que o comportamento já foi anexado

      let isLoading = false;
      let pageSize = 9; // Valor inicial para a primeira página

      function debounce(func, wait) {
        let timeout;
        return function () {
          const context = this, args = arguments;
          clearTimeout(timeout);
          timeout = setTimeout(() => func.apply(context, args), wait);
        };
      }

      function onScroll() {
        const scrollThreshold = 20;
        var getLoadState= $("#list_state").val();

        //If all list items are loaded do not ask for more
        if (getLoadState == 1) {
          if ($(window).scrollTop() + $(window).height() >= $(document).height() - scrollThreshold && !isLoading) {
            isLoading = true; // Definir que estamos carregando

            // Mostrar indicador de carregamento (overlay)
            $('#loading-overlay').show();

            // Incrementar o tamanho da página para carregar mais elementos
            pageSize += 9;

            // Atualizar a URL do botão "Load More" com o novo pageSize
            const loadMoreButton = $('#load-more-button');

            // Verificar se `formaction` está definido
            if (loadMoreButton.length && loadMoreButton.attr('formaction')) {
              const currentUrl = loadMoreButton.attr('formaction');
              const newUrl = updateUrlParameter(currentUrl, 'pagesize', pageSize);
              loadMoreButton.attr('formaction', newUrl);
            }

            // Dispara o clique no botão "Load More"
            loadMoreButton.trigger('click');
          }
        }
      }

      // Função auxiliar para atualizar um parâmetro da URL
      function updateUrlParameter(url, param, paramValue) {
        if (!url) return ''; // Verificação de segurança para não tentar acessar uma URL indefinida
        let newUrl = url;
        const regex = new RegExp('([?&])' + param + '=.*?(&|$)', 'i');
        const separator = url.indexOf('?') !== -1 ? '&' : '?';

        if (url.match(regex)) {
          newUrl = url.replace(regex, '$1' + param + '=' + paramValue + '$2');
        } else {
          newUrl = url + separator + param + '=' + paramValue;
        }

        return newUrl;
      }

      // Quando o botão "Load More" é clicado
      $('#load-more-button').on('click', function () {
        // Mostrar o overlay de carregamento
        $('#loading-overlay').show();
      });

      // Quando o carregamento é concluído
      $(document).ajaxComplete(function () {
        // Esconder o indicador de carregamento após o carregamento ser concluído
        $('#loading-overlay').hide();
        isLoading = false; // Liberar para o próximo carregamento
      });

      // Bind debounce to scroll
      $(window).on('scroll', debounce(onScroll, 50));
    }
  };
})(jQuery, Drupal);

(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.sirSelectForm = {
    attach: function (context, settings) {
      // Update the review button state
      function updateReviewButton() {
        const checkedBoxes = document.querySelectorAll('.checkbox-status-draft:checked').length;
        const reviewButton = document.getElementById('review-selected-button');

        const currentChecked = document.querySelectorAll('.checkbox-status-current:checked').length;
        const deprecatedChecked = document.querySelectorAll('.checkbox-status-deprecated:checked').length;

        if (reviewButton) {
          if (checkedBoxes > 0 && (currentChecked == 0 && deprecatedChecked == 0)) {
            reviewButton.removeAttribute('disabled');
          } else {
            reviewButton.setAttribute('disabled', 'disabled');
          }
        }

      }

      function updateDeleteButton() {
        const checkedBoxes = document.querySelectorAll('.checkbox-status-deprecated:checked').length;
        const deleteButton = document.getElementById('edit-delete-selected-element');

        if (deleteButton) {
          if (checkedBoxes > 0 ) {
            deleteButton.setAttribute('disabled', 'disabled');
          } else {
            deleteButton.removeAttribute('disabled');
          }
        }

      }

      function updateEditButton() {
        const deprecatedChecked = document.querySelectorAll('.checkbox-status-deprecated:checked').length;
        const underreviewChecked = document.querySelectorAll('.checkbox-status-underreview:checked').length;
        const editButton = document.getElementById('edit-edit-selected-element');

        if (editButton) {
          if (deprecatedChecked === 0 && underreviewChecked === 0) {
            editButton.removeAttribute('disabled');
          } else {
            editButton.setAttribute('disabled', 'disabled');
          }
        }

      }

      // Update the review button state when the page loads
      updateReviewButton();
      updateEditButton();
      updateDeleteButton();

      // Update the review button state when a checkbox is changed
      once('sirSelectForm', '.checkbox-status-draft', context).forEach(function (element) {
        element.addEventListener('change', function() {
          updateReviewButton();
          updateDeleteButton();
          updateEditButton();
        });
      });

      // Update the review button state when a checkbox is changed
      once('sirSelectForm', '.checkbox-status-current', context).forEach(function (element) {
        element.addEventListener('change', function() {
          updateReviewButton();
          updateDeleteButton();
          updateEditButton();
        });
      });

      // Update the review button state when a checkbox is changed
      once('sirSelectForm', '.checkbox-status-deprecated', context).forEach(function (element) {
        element.addEventListener('change', function() {
          updateReviewButton();
          updateDeleteButton();
          updateEditButton();
        });
      });

      // Update the review button state when a checkbox is changed
      once('sirSelectForm', '.checkbox-status-underreview', context).forEach(function (element) {
        element.addEventListener('change', function() {
          updateReviewButton();
          updateDeleteButton();
          updateEditButton();
        });
      });
    }
  };
})(jQuery, Drupal);

