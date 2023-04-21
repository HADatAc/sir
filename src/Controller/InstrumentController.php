<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use BorderCloud\SPARQL\SparqlClient;
use Drupal\block\Entity\Block;
use Drupal\Core\Block\BlockBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\Markup;
use Drupal\sir\Exception\SirExceptions;
use Drupal\sir\Controller\UtilsController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;


class InstrumentController extends ControllerBase{

   /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";
  
  public function index() {

    //verify if SIR is configured
    $utils_controller = new UtilsController();
    $response = $utils_controller->siripconfigured();
    if ($response instanceof RedirectResponse) {
      return $response;
    }

    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");     
    $endpoint = "/sirapi/api/instrument/all";

    $content = [];
    $root_url = \Drupal::request()->getBaseUrl();
    $content['description'] = $config->get('repository_description');
    $content['instruments'] = $this->createInstrumentCard($api_url,$endpoint);

    return[
      '#theme' => 'instruments-list',
      '#content' => $content,
      '#attached' => [
        'drupalSettings' => [
          'mymodule' => [
            'base_url' => $root_url,
          ],
        ],
      ],
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

    public function deleteInstrument($api_url,$endpoint,$data){
       /** @var \FusekiAPI$fusekiAPIservice */
    $fusekiAPIservice = \Drupal::service('sir.api_connector');

    $newInstrument = $fusekiAPIservice->instrumentDel($api_url,$endpoint,$data);
    return [];
    }

    public function createInstrumentCard($api_url,$endpoint) {
      $instrumentCards = [];
      $instruments = $this->listInstruments($api_url,$endpoint);

      // Decode the JSON data into a PHP object
      $obj = json_decode($instruments);

      if(!empty($obj)) {
        foreach($obj->body as $instrument) {
          $content = [
            'iname' => $instrument->label,
            'uri' => $instrument->uri,
            'iuriencoded' => rawurlencode($instrument->uri),            
            'ilabel' => $instrument->hasShortName,
            'ilanguage' => $instrument->hasLanguage,
            'iversion' => $instrument->hasVersion,
          ];
          $instrumentCards[] = [
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

       $content = "";
       // Decode the JSON data into a PHP object
       $obj = json_decode($instruments);

      if(!empty($obj)){
        foreach($obj->body as $instrument){
        $content.='<tr>'.
          '<td scope="row">'.$instrument->hasShortName.'</td>'.
          '<td>'.$instrument->label.'</td>'.
          '<td>'.$instrument->hasLanguage.'</td>'.
          '<td>'.$instrument->hasVersion.'</td>'.
          '<td>TXT HTML PDF</td>'.
          '<td>RDF FHIR REDCAP</td>'.
          '</tr>';
        }
      }

      // Return a JSON response.
      return new JsonResponse($content);

      }
  
      // Return an empty response if the request is not an AJAX POST request.
      return new JsonResponse([]);
    }

    public function delintrumentAjax(Request $request) {
      if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
        // Retrieve the data from the AJAX request.
        $data = $request->getContent();
  
        // Process the data as needed.
        $obj = json_decode($data);
        $filter = "";

        if(strlen($obj->instrument) > 0)
        {
          $endpoint =  "/sirapi/api/instrument/delete/".rawurlencode($obj->instrument);
        }else{
          return "0";
        }
        

       $config = $this->config(static::CONFIGNAME);           
       $api_url = $config->get("api_url");
       $pdata = [];
       
       $instruments = $this->deleteInstrument($api_url,$endpoint,$pdata);

     
      return new JsonResponse($content);

      }
  
      // Return an empty response if the request is not an AJAX POST request.
      return new JsonResponse([]);
    }
  
  
    public function download($type,$instrument) {

      $config = $this->config(static::CONFIGNAME);           
      $api_url = $config->get("api_url");     
      $endpoint = "/sirapi/api/instrument/totext/".$type."/".rawurlencode($instrument);

      $fusekiAPIservice = \Drupal::service('sir.api_connector');

      $instrument_list = $fusekiAPIservice->instrumentsList($api_url, $endpoint);
      if (!empty($instrument_list)) {
        $content = (string) $instrument_list;
        $response = new Response($content);
        return $response;
      }
    
      return new Response();
      
    }


    private function processData($data) {
      // Process the data as needed and return a result array.
      return [
        'message' => 'Data received successfully!',
        'data' => $data,
      ];
    }
    
}