<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
//use Drupal\Core\Entity\Element\EntityAutocomplete;

/**
 * Class JsonApiExperienceController
 * @package Drupal\sir\Controller
 */
class JsonApiDetectorController extends ControllerBase{

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
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $detector_list = $fusekiAPIservice->detectorListByKeyword($input);
    $obj = json_decode($detector_list);
    $detectors = [];
    if ($obj->isSuccessful) {
      $detectors = $obj->body;
    }
    foreach ($detectors as $detector) {
      //$label = [
      //  $detector->hasContent,
      //  '<small>(' . $detector->uri . ')</small>',
      //];
      //$results[] = [
      //  'value' => EntityAutocomplete::getEntityLabels([$detector]),
      //  'label' => implode(' ', $label),
      //];
      $results[] = [
        'value' => $detector->hasContent . ' [' . $detector->uri . ']',
        'label' => $detector->hasContent,
      ];
    }

    return new JsonResponse($results);
  }

}