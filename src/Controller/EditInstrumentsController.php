<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\block\Entity\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\sir\Exception\SirExceptions;
use Drupal\sir\Controller\UtilsController;
use Symfony\Component\HttpFoundation\RedirectResponse;



class EditInstrumentsController extends ControllerBase{

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
      $uemail = \Drupal::currentUser()->getEmail();
      $endpoint = "/sirapi/api/instrument/maintaineremail/".rawurlencode($uemail);

      $this->listInstruments($uemail);
      $content = [];
      $root_url = \Drupal::request()->getBaseUrl();
      $content['instruments'] = $this->createInstrumentCard($api_url,$endpoint);

      return[
        '#theme' => 'editinstruments-list',
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

    public function listInstruments($uemail){
      /** @var \FusekiAPI$fusekiAPIservice */
      $fusekiAPIservice = \Drupal::service('sir.api_connector');

      $instrument_list = $fusekiAPIservice->instrumentList($uemail);

    

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
          if($obj->isSuccessful)
          {
            foreach($obj->body as $instrument){
              $content = [
            'iname' => $instrument->label,
            'iuri' => $instrument->uri,
            'ilabel' => $instrument->hasShortName,
            'ilanguage' => $instrument->hasLanguage,
            'iversion' => $instrument->hasVersion,
          ];

         
          $instrumentCards[] = 
          [
            '#theme' => 'editinstrument-card',
            '#content' =>  $content,
          ];

         }
        }         
      }

      return $instrumentCards;

    }
    
}