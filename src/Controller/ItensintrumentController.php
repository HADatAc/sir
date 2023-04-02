<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\block\Entity\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\sir\Exception\SirExceptions;
use Drupal\sir\Controller\UtilsController;
use Symfony\Component\HttpFoundation\RedirectResponse;



class ItensintrumentController extends ControllerBase{

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
      $endpoint = "/sirapi/api/detector/byinstrument/".rawurlencode($instrument);

      $a = $this->listItensofInstruments($api_url,$endpoint);

      $content = [];
      $root_url = \Drupal::request()->getBaseUrl();
      $content['instrument'] = rawurlencode($instrument);
      $content['itens'] = $this->createItensofInstrumentCard($api_url,$endpoint);

  
        return[
            '#theme' => 'itensofinstrument-list',
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

    public function listItensofInstruments($api_url,$endpoint){
      /** @var \FusekiAPI$fusekiAPIservice */
      $fusekiAPIservice = \Drupal::service('sir.api_connector');

      $instrumentitens_list = $fusekiAPIservice->instrumentsList
      ($api_url,$endpoint);

  
      if(!empty($instrumentitens_list)){
        return $instrumentitens_list;
      }
      return [];
    }

    public function createItensofInstrumentCard($api_url,$endpoint) {
      $itensofinstrumentCards = [];
      $itensofinstruments = $this->listItensofInstruments($api_url,$endpoint);

      // Decode the JSON data into a PHP object 
      $obj = json_decode($itensofinstruments);

   

        if(!empty($obj)){
          if($obj->isSuccessful)
          {
            foreach($obj->body as $item){
              $content = [
            'iname' => $item->label,
            'iuri' => $item->uri
          ];
         
          $itensofinstrumentCards[] = 
          [
            '#theme' => 'itensofinstrument-card',
            '#content' =>  $content,
          ];

         }
        }         
      }

      return $itensofinstrumentCards;

    }
    
}