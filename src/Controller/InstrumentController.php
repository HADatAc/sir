<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use BorderCloud\SPARQL\SparqlClient;
use Drupal\block\Entity\Block;
use Drupal\Core\Block\BlockBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\Markup;


class InstrumentController extends ControllerBase{

   /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";
  

    public function index()
    {

      $config = $this->config(static::CONFIGNAME);           
      $api_url = $config->get("api_url");
      $endpoint = "/sirapi/api/instrument/all";
      $this->listInstruments($api_url,$endpoint);
      $content = [];
      $content['instruments'] = $this->createInstrumentCard($api_url,$endpoint);
        return[
            '#theme' => 'instruments-list',
            '#content' => $content,
            ];
    }

    public function listInstruments($api_url,$endpoint){
      /** @var \FusekiAPI$fusekiAPIservice */
      $fusekiAPIservice = \Drupal::service('sir.api_connector');

      $instrument_list = $fusekiAPIservice->instrumentsList($api_url,$endpoint);
      if(!empty($instrument_list)){
        return $instrument_list;
      }
      return [];
    }

    public function createInstrumentCard($api_url,$endpoint) {
      $instrumentCards = [];
      $instruments = $this->listInstruments($api_url,$endpoint);

      // Decode the JSON data into a PHP object
      $obj = json_decode($instruments);

        if(!empty($obj)){
         foreach($obj->body as $instrument){
         
              $content = [
            'iname' => $instrument->label,
            'ilabel' => $instrument->hasShortName,
          ];

         
          $instrumentCards[] = 
          [
            '#theme' => 'instrument-card',
            '#content' =>  $content,
          ];

         }
      }

      return $instrumentCards;

    }

   


    public function searchinstrumentsAjax(Request $request) {
      if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
        // Retrieve the data from the AJAX request.
        $data = $request->getContent();
  
        // Process the data as needed.
        //$result = $this->processData($data);
        $obj = json_decode($data);
        $filter = "";

        if($obj->typeofsearch == 'questionnaries')
        {
          if(strlen($obj->questionnariename) > 0) {
            $endpoint =  "/sirapi/api/instrument/keyword/".$obj->questionnariename;
          }
        }

       $config = $this->config(static::CONFIGNAME);           
       $api_url = $config->get("api_url");
       
       $instruments = $this->listInstruments($api_url,$endpoint);

       # print("<hr>"); 
        #print_r($endpoint); 
       # print($instruments); 
      #  exit();


       $content = "";
      // Decode the JSON data into a PHP object
      $obj = json_decode($instruments);

      if(!empty($obj)){
        foreach($obj->body as $instrument){
        $content.='<tr><td scope="row">'.$instrument->label.'</td><td>'.$instrument->hasShortName.'</td></tr>';
        }
      }

      // Return a JSON response.
      return new JsonResponse($content);

      }
  
      // Return an empty response if the request is not an AJAX POST request.
      return new JsonResponse([]);
    }
  
  
    private function processData($data) {
      // Process the data as needed and return a result array.
      return [
        'message' => 'Data received successfully!',
        'data' => $data,
      ];
    }
    
}