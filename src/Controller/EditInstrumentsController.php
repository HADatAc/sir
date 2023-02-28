<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use BorderCloud\SPARQL\SparqlClient;
use Drupal\block\Entity\Block;
use Drupal\Core\Block\BlockBase;


class EditInstrumentsController extends ControllerBase{

   /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

    public function index()
    {

      $config = $this->config(static::CONFIGNAME);           
      $endpoint = $config->get("api_url")."/sir/query";
      $this->listInstruments($endpoint);
      $content = [];
      $content['instruments'] = $this->createInstrumentCard($endpoint);
        return[
            '#theme' => 'editinstruments-list',
            '#content' => $content,
            ];
    }

    public function listInstruments($endpoint){
      /** @var \FusekiAPI$fusekiAPIservice */
      $fusekiAPIservice = \Drupal::service('sir.api_connector');

      $instrument_list = $fusekiAPIservice->instrumentsList($endpoint);
      if(!empty($instrument_list)){
        return $instrument_list;
      }
      return [];
    }

    public function createInstrumentCard($endpoint) {
      $instrumentCards = [];

      $instruments = $this->listInstruments($endpoint);

      if(!empty($instruments)){
         foreach($instruments as $instrument){
              $content = [
            'iname' => $instrument['iname'],
            'ilabel' => $instrument['ilabel'],
          ];
         
          $instrumentCards[] = 
          [
            '#theme' => 'editinstrument-card',
            '#content' =>  $content,
          ];

         }
      }

      return $instrumentCards;

    }
    
}