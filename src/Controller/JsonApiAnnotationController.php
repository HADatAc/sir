<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;

/**
 * Class JsonApiAnnotationController
 * @package Drupal\sir\Controller
 */
class JsonApiAnnotationController extends ControllerBase{

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

    // $containeruri = base64_decode($containeruri);
    // $manageremail = base64_decode($manageremail);
    //dpm($containeruri);
    $api = \Drupal::service('rep.api_connector');
    $annotation_list = $api->listByKeyword('annotation',$keyword,10,0);
    //$annotation_list = $api->listByManagerEmailByContainer($containeruri, 'annotation',$manageremail,10,0);
    $obj = json_decode($annotation_list);
    $annotations = [];
    if ($obj->isSuccessful) {
      $annotations = $obj->body;
    }
    foreach ($annotations as $annotation) {
      $results[] = [
        'value' => $annotation->label . ' [' . $annotation->uri . ']',
        'label' => $annotation->label . ' [' . $annotation->uri . ']',
      ];
    }
    return new JsonResponse($results);
  }

}
