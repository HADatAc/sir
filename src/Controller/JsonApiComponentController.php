<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
//use Drupal\Core\Entity\Element\EntityAutocomplete;

/**
 * Class JsonApiComponentController
 * @package Drupal\sir\Controller
 */
class JsonApiComponentController extends ControllerBase{

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
    $api = \Drupal::service('rep.api_connector');
    $component_list = $api->listByKeyword('component',$input,10,0);
    $obj = json_decode($component_list);
    $components = [];
    if ($obj->isSuccessful) {
      $components = $obj->body;
    }
    foreach ($components as $component) {
      //$label = [
      //  $component->hasContent,
      //  '<small>(' . $component->uri . ')</small>',
      //];
      //$results[] = [
      //  'value' => EntityAutocomplete::getEntityLabels([$component]),
      //  'label' => implode(' ', $label),
      //];
      // $results[] = [
      //   'value' => $component->componentStem->hasContent . '  -- CB: '  . $component->codebook->label . ' [' . $component->uri . ']',
      //   'label' => $component->componentStem->hasContent . '  -- CB: '  . $component->codebook->label,
      // ];
      $results[] = [
          'value' => $component->label . ' [' . $component->uri . ']',
          'label' => $component->label,
        ];
    }

    return new JsonResponse($results);
  }

}
