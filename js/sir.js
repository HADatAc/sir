(function ($) {
  Drupal.behaviors.myModule = {
    attach: function (context, settings) {
      // Get the base URL from drupalSettings
      var rootUrl = settings.mymodule.base_url;

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

    $( "#searchinstrumentbytypebtn" ).click(function() {
        updateinstruments();
      });

      function updateinstruments()
      {
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
  
      $(".delInstrument").once('sir').click(function(){
        if (confirm("Do you want to delete?")){
          let instrument = $(this).attr("data-item-url");
          var data = {
            'instrument': instrument
          };
          // Send the AJAX request.
         jQuery.ajax({
          type: 'POST',
          url: rootUrl+'/sir/ajax/delinstrument',
          data: JSON.stringify(data),
          contentType: "application/json",
          success: function (response) {
            location.reload();
          },
          error: function () {
            console.log('An error occurred while processing the request.');
          },
          dataType: 'json'
        });
        } 
        return false;
      });

      $(".delExperience").once('sir').click(function(){
        if (confirm("Do you want to delete?")){
          let experience = $(this).attr("data-item-url");
          var data = {
            'experience': experience
          };
          // Send the AJAX request.
         jQuery.ajax({
          type: 'POST',
          url: rootUrl+'/sir/ajax/delexperience',
          data: JSON.stringify(data),
          contentType: "application/json",
          success: function (response) {
            location.reload();
          },
          error: function () {
            console.log('An error occurred while processing the request.');
          },
          dataType: 'json'
        });
        } 
        return false;
      });


    }
    };
  })(jQuery);