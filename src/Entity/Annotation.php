<?php

namespace Drupal\sir\Entity;

use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class Annotation {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_container' => t('Instrument'),
      'element_position' => t('Position'),
      'element_annotationstem' => t('Content'),
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
        $container = "";
        $content = "";
        $annotationStemStr = "";
        if ($element->uri != NULL) {
          $uri = $element->uri;
          $position = $element->hasPosition;
          $uri = Utils::namespaceUri($uri);
          if ($element->belongsTo != NULL) {
            $instrument = $api->parseObjectResponse($api->getUri($element->belongsTo), 'getUri');
            if ($instrument != NULL && $instrument->label != NULL) {
              $container = $instrument->label;
            }
          }
          if ($element->annotationStem != NULL) {
            if ($element->annotationStem->hasContent != NULL) {
              $content = $element->annotationStem->hasContent;
            }
          }
        }
        $output[$uri] = [
          'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),     
          'element_container' => $container,     
          'element_position' => Utils::namespaceUri($position),     
          'element_annotationstem' => $content,     
        ];
      }
    }
    return $output;

  }

}