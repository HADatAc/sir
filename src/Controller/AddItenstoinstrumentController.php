<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\block\Entity\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\sir\Exception\SirExceptions;
use Drupal\sir\Controller\UtilsController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


class AddItenstoinstrumentController extends ControllerBase{

  /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";
  

    public function index($instrument)
    {

      //verify if SIR is configured
      $utils_controller = new UtilsController();
      $response = $utils_controller->siripconfigured();
      if ($response instanceof RedirectResponse) {
        return $response;
      }

      $config = $this->config(static::CONFIGNAME);           
      $api_url = $config->get("api_url");
      $uemail = \Drupal::currentUser()->getEmail();
      $content = [];
      $root_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

        return[
            '#theme' => 'itensofinstrument-add',
            '#content' => $content,
            '#attached' => [
              'drupalSettings' => [
                'mymodule' => [
                  'base_url' => $root_url,
                  'api_url' => $api_url,
                ],
              ],
            ],
          ];
    }

    public function searchExperiencesforitemAjax($experience) {
      $experiences = "";
        if(strlen($experience) > 0) 
        {
          $endpoint =  "/sirapi/api/experience/keyword/".$experience;
          $config = $this->config(static::CONFIGNAME);           
          $api_url = $config->get("api_url");
          $apiReturn = $this->listExperiences($api_url,$endpoint);
          $content = [];

           // Decode the JSON data into a PHP object 
          $obj = json_decode($apiReturn);

         
          if(!empty($obj))
          {
            if($obj->isSuccessful)
            {
              foreach($obj->body as $instrument)
              {
                $item = [
              'name' => $instrument->label,
              'uri' => $instrument->uri,
              'uriencoded' => rawurlencode($instrument->uri),
               ];
               array_push($content, $item);

              }
            }
          }
      }

        return new JsonResponse($content);
    }

    public function listExperiences($api_url,$endpoint){
      /** @var \FusekiAPI$fusekiAPIservice */
      $fusekiAPIservice = \Drupal::service('sir.api_connector');

      $experiences_list = $fusekiAPIservice->instrumentsList
      ($api_url,$endpoint);

  
      if(!empty($experiences_list)){
        return $experiences_list;
      }
      return [];
    }

    public function saveinstrumentitemAjax(Request $request) {
      if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
        // Retrieve the data from the AJAX request.
        $data = $request->getContent();
  
        // Process the data as needed.
        $obj = json_decode($data);
        $filter = "";

        $uid = \Drupal::currentUser()->id();
      $uemail = \Drupal::currentUser()->getEmail();

      $iid = time().rand(10000,99999).$uid;

      $data = [];
      
       $datap = '{"uri":"'.$namespace_url.'kb/'.$namespace_abbreviation.'/Detector'.$iid.'","typeUri":"http://hadatac.org/ont/vstoi#Detector","hascoTypeUri":"http://hadatac.org/ont/vstoi#Detector","label":"'.$form_state->getValue('itemlabel').'","isInstrumentAttachment":"'.$instrument.'","hasExperience":"'.$instrument.'"}';
  
      $dataE = rawurlencode($datap);

      $newInstrument = $this->addItem($api_url,"/sirapi/api/detector/create/".$dataE,$data);

     
      return new JsonResponse($content);

      }
  
      // Return an empty response if the request is not an AJAX POST request.
      return new JsonResponse([]);
    }
    
    public function addItem($api_url,$endpoint,$data){
      /** @var \FusekiAPI$fusekiAPIservice */
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
  
      $newInstrument = $fusekiAPIservice->instrumentAdd($api_url,$endpoint,$data);
      if(!empty($newInstrument)){
        return $newInstrument;
      }
      return [];
    }

}