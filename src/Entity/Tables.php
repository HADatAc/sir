<?php

namespace Drupal\sir\Entity;

use Kint\Kint;

class Tables {
  
  public function getLanguages() {
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $language_list = $fusekiAPIservice->languageList();
    $obj = json_decode($language_list);
    if ($obj->isSuccessful) {
      $languages = $obj->body;
    }
    $results = array();
    foreach ($languages as $language) {
      $results[$language->code] = $language->value;
    }
    return $results;
  }

}