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
class JsonApiDetectorController extends ControllerBase{

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
    $endpoint = "/sirapi/api/detector/keyword/".rawurlencode($input);
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $detector_list = $fusekiAPIservice->detectorsList($api_url,$endpoint);
    $obj = json_decode($detector_list);
    $detectors = [];
    if ($obj->isSuccessful) {
      $detectors = $obj->body;
    }
    foreach ($detectors as $detector) {
      $results[] = [
        'value' => $detector->uri,
        #'label' => $experience->label.' ('.$experience->uri.')',
        'label' => $detector->hasContent,
      ];
    }
    return new JsonResponse($results);
  }

}