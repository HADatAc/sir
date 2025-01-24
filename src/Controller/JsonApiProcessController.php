<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiProcessController
 * @package Drupal\sir\Controller
 */
class JsonApiProcessController extends ControllerBase{

  /**
   * @return JsonResponse
   */
  public function handleAutocomplete(Request $request) {
    $results = [];
    $input = $request->query->get('q');
    if (!$input) {
      return new JsonResponse($results);
    }
    $keyword = Xss::filter($input);
    $api = \Drupal::service('rep.api_connector');
    $process_list = $api->listByKeyword('process',$keyword,10,0);
    $obj = json_decode($process_list);
    $processes = [];
    if ($obj->isSuccessful) {
      $processes = $obj->body;
    }
    foreach ($processes as $process) {
      $results[] = [
        'value' => $process->label . ' [' . $process->uri . ']',
        'label' => $process->label,
      ];
    }
    return new JsonResponse($results);
  }

  public function loadDetectors(Request $request) {
    $api = \Drupal::service('rep.api_connector');
    $detectors = $api->detectorListFromInstrument($request->query->get('instrument_id'));
    $obj = json_decode($detectors);
    $detectors = [];
    if ($obj->isSuccessful) {
      $detectors = $obj->body;
    }
    dpm($detectors);
    $detectors = [];
    if ($obj->isSuccessful) {
      $detectors = $obj->body;
    }
    foreach ($detectors as $detector) {
      $detectors[] = [
        'name' => $detector->label,
        'value' => $detector->uri,
      ];
    }

    return new JsonResponse($detectors);
  }

}
