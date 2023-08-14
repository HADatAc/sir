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

    $sir_updated = $this->sirRepoVersion();
    $sir_api_version = $sir_updated->body->hasVersion;

    $sir_gui_version = $config->get("sir_gui_version");     
    
    if($sir_gui_version != $sir_api_version) {
      echo "Please update SIR API to version [" . $sir_gui_version . "]";
      //exit();
    }

    $root_url = \Drupal::request()->getBaseUrl();
    $redirect = new RedirectResponse($root_url . '/sir/list/instrument/_/_/1/12');
  
    return $redirect;

  }

  private function sirRepoVersion() {
    $content = [];
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $sirRepoVersion = $fusekiAPIservice->repoInfo();
    //dpm($sirRepoVersion);
    if (!empty($sirRepoVersion)) {
      $content = json_decode($sirRepoVersion);
      return $content;
    }
  }

}
