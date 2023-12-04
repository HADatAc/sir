<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiAnnotationContainerController
 * @package Drupal\sir\Controller
 */
class JsonApiAnnotationContainerController extends ControllerBase{

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
    $container_list = $api->listByKeyword('instrument',$keyword,10,0);
    $obj = json_decode($container_list);
    $containers = [];
    if ($obj->isSuccessful) {
      $containers = $obj->body;
    }
    //dpm($containers);
    foreach ($containers as $container) {
      $results[] = [
        'value' => $container->label . ' [' . $container->uri . ']',
        'label' => $container->label,
      ];
    }
    return new JsonResponse($results);
  }

}