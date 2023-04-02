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


class ExperienceController extends ControllerBase{

   /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";
  

    public function index()
    {

      //verify if SIR is configured
      $utils_controller = new UtilsController();
      $response = $utils_controller->siripconfigured();
      if ($response instanceof RedirectResponse) {
        return $response;
      }

      $config = $this->config(static::CONFIGNAME);           
      $api_url = $config->get("api_url");     
      $endpoint = "/sirapi/api/experience/all";

      $content = [];
      $root_url = \Drupal::request()->getBaseUrl();
      $content['experiences'] = $this->createExperienceCard($api_url,$endpoint);
        return[
            '#theme' => 'experiences-list',
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

      $experiences_list = $fusekiAPIservice->experienceList($api_url,$endpoint);
      if(!empty($experiences_list)){
        return $experiences_list;
      }
      return [];
    }

    public function deleteExperience($api_url,$endpoint,$data){
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $newExperience = $fusekiAPIservice->experienceDel($api_url,$endpoint,$data);
    return [];
    }

    public function createExperienceCard($api_url,$endpoint) {
      $experienceCards = [];
      $experiences = $this->listExperiences($api_url,$endpoint);

      // Decode the JSON data into a PHP object
      $obj = json_decode($experiences);

        if(!empty($obj)){
         foreach($obj->body as $experience){
         
              $content = [
            'iname' => $experience->label,
            'url' => $experience->uri,
            'ilabel' => $experience->hasShortName,
          ];

         
          $experienceCards[] = 
          [
            '#theme' => 'experience-card',
            '#content' =>  $content,
          ];

         }
      }

      return $experienceCards;

    }

    public function delExperienceAjax(Request $request) {
      if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
        // Retrieve the data from the AJAX request.
        $data = $request->getContent();
  
        // Process the data as needed.
        $obj = json_decode($data);
        $filter = "";

        if(strlen($obj->experience) > 0)
        {
          $endpoint =  "/sirapi/api/experience/delete/".rawurlencode($obj->experience);
        }else{
          return "0";
        }
        

        $config = $this->config(static::CONFIGNAME);           
        $api_url = $config->get("api_url");
        $pdata = [];
       
        $instruments = $this->deleteExperience($api_url,$endpoint,$pdata);

     
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
 
    public function searchexperiencesAjax(Request $request) {
      if ($request->isXmlHttpRequest() && $request->isMethod('POST')) {
        // Retrieve the data from the AJAX request.
        $data = $request->getContent();
  
        // Process the data as needed.
        $obj = json_decode($data);
        $filter = "";

        if($obj->typeofsearch == 'experience') {
          if(strlen($obj->experiencename) > 0) {
            $endpoint =  "/sirapi/api/experience/keyword/".$obj->experiencename;
          }
        }

       $config = $this->config(static::CONFIGNAME);           
       $api_url = $config->get("api_url");
       
       $experiences = $this->listExperiences($api_url,$endpoint);

       $content = "";
      // Decode the JSON data into a PHP object
      $obj = json_decode($experiences);

      if(!empty($obj)){
        foreach($obj->body as $experience){
        $content.='<tr><td scope="row">'.$experience->label.'</td></tr>';
        }
      }

      // Return a JSON response.
      return new JsonResponse($content);

      }
  
      // Return an empty response if the request is not an AJAX POST request.
      return new JsonResponse([]);
    }


}