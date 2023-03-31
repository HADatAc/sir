<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\block\Entity\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\sir\Exception\SirExceptions;
use Drupal\sir\Controller\UtilsController;
use Symfony\Component\HttpFoundation\RedirectResponse;



class EditExperiencesController extends ControllerBase{

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
      $endpoint = "/sirapi/api/experience/maintaineremail/".rawurlencode($uemail);

      $this->listExperiences($api_url,$endpoint);
      $content = [];
      $root_url = \Drupal::request()->getBaseUrl();
      $content['experiences'] = $this->createExperienceCard($api_url,$endpoint);
        return[
            '#theme' => 'editexperiences-list',
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

    public function listExperiences($api_url,$endpoint){
      $fusekiAPIservice = \Drupal::service('sir.api_connector');

      $experience_list = $fusekiAPIservice->experiencesList($api_url,$endpoint);

      if(!empty($experience_list)){
        return $experience_list;
      }
      return [];
    }

    public function createExperienceCard($api_url,$endpoint) {
      $experienceCards = [];
      $experiences = $this->listExperiences($api_url,$endpoint);

      // Decode the JSON data into a PHP object 
      $obj = json_decode($experiences);

        if(!empty($obj)){
          if($obj->isSuccessful) {
            foreach($obj->body as $experience){

            $content = [
                'iuri' => $experience->uri,
                'ilabel' => $experience->label,
              ];

         
          $experienceCards[] = 
          [
            '#theme' => 'editexperience-card',
            '#content' =>  $content,
          ];

         }
        }         
      }

      return $experienceCards;

    }
    
}