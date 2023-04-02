(function ($) {
    Drupal.behaviors.myModule = {
      attach: function (context, settings) {
        // Get the base URL from drupalSettings
        var rootUrl = settings.mymodule.base_url;
        var api_url = settings.mymodule.api_url;
        var instrument = settings.mymodule.instrument;

        console.log(api_url);
  
        $(document).ready(function() {
          $('#itensblock').hide();  
          $('#searchinstrumenttype').change(function(){
              $('#itensblock').show(); 
              switch($('#searchinstrumenttype').val()){
                  case"text":$('#block-text').show(); 
                  break;
                  case"textarea":$('#block-textarea').show(); 
                  break;
                  case"checkbox":$('#block-checkbox').show(); 
                  break;
              }
          });
      });
  
      $("#itemexperience").autocomplete({
        source: function (request, response) {
          $.ajax({
            url: rootUrl + "sir/ajax/searchexperiencesforitem/" + request.term,
            type: "GET",
            dataType: "json",
            success: function (data) {
              console.log("Data received:", data); // Log the received data
              response($.map(data, function (el) { // Wrap the data in an array
                console.log("Current element:", el); // Log the current element
                return {
                  label: el.name,
                  value: el.uri
                };
              }));
            }
          });
        },
        select: function (event, ui) {
          this.value = ui.item.label;
          $("#itemexperienceuri").val(ui.item.value);
          event.preventDefault();
        }
      });
  
      $( "#searchinstrumentbytypebtn" ).click(function() {
        updateinstruments();
      });

      function savequestionnarieitem()
      {
        let searchinstrumenttype = $('#searchinstrumenttype').val();
        let itemlabel = $('#itemlabel').val();
        let itemexperience = $('#itemexperience').val();

        var data = {
           'searchinstrumenttype': searchinstrumenttype,
           'itemlabel': itemlabel,
           'itemexperience': itemexperience,
           'instrument': instrument
         };
       
         // Send the AJAX request.
         jQuery.ajax({
           type: 'POST',
           url: rootUrl+'/sir/ajax/saveinstrumentitem_ajax',
           data: JSON.stringify(data),
           contentType: "application/json",
           success: function (response) {
              alert("Item added successfully!")  
              location.reload();
              window.location.replace(rootUrl+'sir/manage/itensofinstrument/'+instrument);
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