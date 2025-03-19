<?php

namespace Drupal\sir\Entity;

use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class ContainerSlot {

  public static function generateHeader() {

    return $header = [
      'element_detector' => t('Item(s)'),
      'element_position' => t('Position'),
    ];

  }

  public static function generateOutput($list) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();
    //dpm($root_url);

    //dpm($list);

    $api = \Drupal::service('rep.api_connector');
    $output = array();
    if ($list != NULL && sizeof($list) > 0) {
      foreach ($list as $element) {
        $uri = "";
        $priority = "";
        $detectorStr = "";
        if ($element->uri != NULL) {
          $uri = $element->uri;
          $priority = $element->hasPriority;
          $uri = Utils::namespaceUri($uri);
          if ($element->hasDetector != NULL && $element->hasDetector != "") {
            $detector = $api->parseObjectResponse($api->getUri($element->hasDetector), 'getUri');
            if ($detector != NULL) {
              $nsUri = Utils::namespaceUri($detector->uri);
              $detectorStr =  t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($detector->uri).'">'.$nsUri.'</a>');
            }
          }
        }
        $output[$uri] = [
          'element_detector' => $detectorStr,
          'element_position' => $priority,
        ];
      }
    }
    return $output;

  }

}
