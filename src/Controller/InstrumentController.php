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
      $endpoint = $config->get("api_url")."/sir/query";
      $this->listInstruments($endpoint,"");
      $content = [];
      $content['instruments'] = $this->createInstrumentCard($endpoint,"");
        return[
            '#theme' => 'instruments-list',
            '#content' => $content,
            ];
    }

    public function listInstruments($endpoint,$filter){
      /** @var \FusekiAPI$fusekiAPIservice */
      $fusekiAPIservice = \Drupal::service('sir.api_connector');

      $instrument_list = $fusekiAPIservice->instrumentsList($endpoint,$filter);
      if(!empty($instrument_list)){
        return $instrument_list;
      }
      return [];
    }

    public function createInstrumentCard($endpoint,$filter) {
      $instrumentCards = [];

      $instruments = $this->listInstruments($endpoint,$filter);

      if(!empty($instruments)){
         foreach($instruments as $instrument){
         
              $content = [
            'iname' => $instrument['iname'],
            'ilabel' => $instrument['ilabel'],
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

      /* print_r("aaaaabbb");
       print_r($obj->typeofsearch);
       print("teste");
       print_r($data);*/

       if($obj->typeofsearch == 'questionnaries')
        {
          if(strlen($obj->questionnariename) > 0) {
            $filter.= 'FILTER (REGEX(?iname, "'.$obj->questionnariename.'", "i"))';
          }
        }

       #print_r($request->query->get('typeofsearch')."a");
      # print($filter);
      # exit();
        #$json = json_decode($data, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
       # print_r($json);
       /* if(){
          FILTER (REGEX(?name, "John", "i"))
        }*/

        // Return a JSON response.
       # return new JsonResponse($result);

       $config = $this->config(static::CONFIGNAME);           
       $endpoint = $config->get("api_url")."/sir/query";
       
       $instruments = $this->listInstruments($endpoint,$filter);
       $content = "";
       foreach($instruments as $instrument){
        $content.='<tr><td scope="row">'.$instrument['iname'].'</td><td>'.$instrument['ilabel'].'</td></tr>';
       }
       #print_r($content); 
       
       // Process the data as needed.
     # $result = $this->processData($content);

      // Return a JSON response.
      return new JsonResponse($content);

       /*
       $markup = Markup::create($content);

       return [
        '#type' => 'markup',
        '#markup' => $markup,
      ];

      */

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