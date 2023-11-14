<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiResponseOptionController
 * @package Drupal\sir\Controller
 */
class JsonApiResponseOptionController extends ControllerBase{

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
    $response_option_list = $api->listByKeyword('responseoption',$keyword,10,0);
    $obj = json_decode($response_option_list);
    $responseoptions = [];
    if ($obj->isSuccessful) {
      $responseoptions = $obj->body;
    }
    foreach ($responseoptions as $responseoption) {
      $results[] = [
        'value' => $responseoption->hasContent . ' [' . $responseoption->uri . ']',
        'label' => $responseoption->hasContent,
      ];
    }
    return new JsonResponse($results);
  }

}