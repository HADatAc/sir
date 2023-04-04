<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiExperienceController
 * @package Drupal\sir\Controller
 */
class JsonApiExperienceController extends ControllerBase{

  /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";


  /**
   * @return JsonResponse
   */
  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }
    $input = Xss::filter($input);

    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");
    $endpoint = "/sirapi/api/experience/keyword/".rawurlencode($input);
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $experience_list = $fusekiAPIservice->experiencesList($api_url,$endpoint);
    $obj = json_decode($experience_list);
    $experiences = [];
    if ($obj->isSuccessful) {
      $experiences = $obj->body;
    }
    foreach ($experiences as $experience) {
      $results[] = [
        'value' => $experience->uri,
        #'label' => $experience->label.' ('.$experience->uri.')',
        'label' => $experience->label,
      ];
    }
    return new JsonResponse($results);
  }

}