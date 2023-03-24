(function ($) {
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
           url: 'sir/ajax/searchinstruments',
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

  
      $(".delInstrument").click(function(){
        if (confirm("Do you want to delete")){
          let instrument = $(this).attr("data-item-url");
          var data = {
            'instrument': instrument
          };
          // Send the AJAX request.
         jQuery.ajax({
          type: 'POST',
          url: 'sir/ajax/delinstrument',
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
 
  })(jQuery)