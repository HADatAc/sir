<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\sir\Exception\SirExceptions;
use Drupal\Core\Routing\TrustedRedirectResponse;

class UtilsController extends ControllerBase{

   /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";
  
  public function siripconfigured() {
    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");
    $sir_not_configured = ($api_url == NULL || $api_url == "" || strpos($api_url,'x.x.x.x'));
    
    if ($sir_not_configured){
      $root_url = \Drupal::request()->getBaseUrl();
      //$url = $root_url.'/admin/config/sir';
      $url = $root_url.'/admin/config/sir/ip';
      $response = new TrustedRedirectResponse($url);
      \Drupal::messenger()->addMessage(t("Please configure SIR API IP address."));
      return $response;
    }  
  }
}