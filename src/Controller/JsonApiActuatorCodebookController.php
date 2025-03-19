<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiCodebookController
 * @package Drupal\sir\Controller
 */
class JsonApiActuatorCodebookController extends ControllerBase{

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
    $codebook_list = $api->listByKeyword('codebook',$keyword,10,0);
    $obj = json_decode($codebook_list);
    $codebooks = [];
    if ($obj->isSuccessful) {
      $codebooks = $obj->body;
    }
    foreach ($codebooks as $codebook) {
      $results[] = [
        'value' => $codebook->label . ' [' . $codebook->uri . ']',
        'label' => $codebook->label,
      ];
    }
    return new JsonResponse($results);
  }

}
