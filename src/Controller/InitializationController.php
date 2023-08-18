<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\sir\Controller\UtilsController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class InitializationController extends ControllerBase{

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

    $APIservice = \Drupal::service('sir.api_connector');
    $sir_updated = $APIservice->parseObjectResponse($APIservice->repoInfo(), 'repoInfo');
    $sir_api_version = NULL;
    if ($sir_updated != NULL) {
      $sir_api_version = $sir_updated->hasVersion;
    }

    $sir_gui_version = $config->get("sir_gui_version");     
    
    if ($sir_api_version == NULL) {
      \Drupal::messenger()->addError(t("API service could not retrieve its version number. Check if API IP configuration is correct."));
    } 
    //else {
    //  if($sir_gui_version != $sir_api_version) {
    //    \Drupal::messenger()->addError(t("SIR's API and GUI are required to have identical version numbers. API version is " . $sir_api_version . ". GUI version is " . $sir_gui_version . "."));
    //  }
    //}

    $root_url = \Drupal::request()->getBaseUrl();
    $redirect = new RedirectResponse($root_url . '/sir/list/instrument/_/_/1/12');
  
    return $redirect;

  }

  private function sirRepoVersion() {
  }

}
