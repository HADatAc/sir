<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
//use Drupal\Core\Entity\Element\EntityAutocomplete;

/**
 * Class JsonApiActuatorController
 * @package Drupal\sir\Controller
 */
class JsonApiActuatorController extends ControllerBase{

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
    $actuator_list = $api->listByKeyword('actuator',$input,10,0);
    $obj = json_decode($actuator_list);
    $actuators = [];
    if ($obj->isSuccessful) {
      $actuators = $obj->body;
    }
    foreach ($actuators as $actuator) {
      //$label = [
      //  $actuator->hasContent,
      //  '<small>(' . $actuator->uri . ')</small>',
      //];
      //$results[] = [
      //  'value' => EntityAutocomplete::getEntityLabels([$actuator]),
      //  'label' => implode(' ', $label),
      //];
      // $results[] = [
      //   'value' => $actuator->actuatorStem->hasContent . '  -- CB: '  . $actuator->codebook->label . ' [' . $actuator->uri . ']',
      //   'label' => $actuator->actuatorStem->hasContent . '  -- CB: '  . $actuator->codebook->label,
      // ];
      $results[] = [
          'value' => $actuator->label . ' [' . $actuator->uri . ']',
          'label' => $actuator->label,
        ];
    }

    return new JsonResponse($results);
  }

}
