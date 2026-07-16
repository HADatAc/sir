<?php

namespace Drupal\sir\Entity;

use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;
use Drupal\Core\Render\Markup;

class ContainerSlot {

  public static function generateHeader() {

    return $header = [
      'element_component' => t('Item(s)'),
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
        $componentStr = "";
        if ($element->uri != NULL) {
          $uri = $element->uri;
          $priority = $element->hasPriority;
          $uri = Utils::namespaceUri($uri);
          if ($element->hasComponent != NULL && $element->hasComponent != "") {
            $component = $api->parseObjectResponse($api->getUri($element->hasComponent), 'getUri');
            if ($component != NULL) {
              $nsUri = Utils::namespaceUri($component->uri);
              $componentStr = Markup::create(Utils::describeAnchor((string) $component->uri, (string) $nsUri));
            }
          }
        }
        $output[$uri] = [
          'element_component' => $componentStr,
          'element_position' => $priority,
        ];
      }
    }
    return $output;

  }

}
