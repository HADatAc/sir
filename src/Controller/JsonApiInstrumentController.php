<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiStemController
 * @package Drupal\sir\Controller
 */
class JsonApiInstrumentController extends ControllerBase{

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
    //dpm($keyword);
    $api = \Drupal::service('rep.api_connector');
    $instrument_list = $api->listByKeyword('instrument',$keyword,10,0);
    $obj = json_decode($instrument_list);
    $instruments = [];
    if ($obj->isSuccessful) {
      $instruments = $obj->body;
    }
    //dpm($instruments);
    foreach ($instruments as $instrument) {
      $results[] = [
        'value' => $instrument->uri,
        'label' => $instrument->label  . ' [' . $instrument->uri . ']',
      ];
    }
    return new JsonResponse($results);
  }

}
