<?php

namespace Drupal\sir\Entity;

use Drupal\sir\Utils;
use Drupal\sir\Vocabulary\SIRGUI;

class Attachment {

  public static function generateHeader() {

    return $header = [
      'element_position' => t('Position'),
      'element_detector' => t('Item'),
    ];

  }

  public static function generateOutput($list) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();
    //dpm($root_url);

    //dpm($list);

    $api = \Drupal::service('sir.api_connector');
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
              $detectorStr =  t('<a href="'.$root_url.SIRGUI::DESCRIBE_PAGE.base64_encode($detector->uri).'">'.$nsUri.'</a>');
            }
          }
        }
        $output[$uri] = [
          'element_position' => $priority,     
          //'element_detector' => t('<a href="'.$root_url.SIRGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),     
          'element_detector' => $detectorStr,     
        ];
      }
    }
    return $output;

  }

}