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
class JsonApiStemController extends ControllerBase{

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
    dpm($keyword);
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $stem_list = $fusekiAPIservice->listByKeyword('detectorstem',$keyword,10,0);
    $obj = json_decode($stem_list);
    $stems = [];
    if ($obj->isSuccessful) {
      $stems = $obj->body;
    }
    dpm($stems);
    foreach ($stems as $stem) {
      $results[] = [
        'value' => $stem->hasContent . ' [' . $stem->uri . ']',
        'label' => $stem->hasContent,
      ];
    }
    return new JsonResponse($results);
  }

}