<?php

namespace Drupal\sir\Entity;

use Kint\Kint;

class Tables {
  
  public function getLanguages() {
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $language_list = $fusekiAPIservice->languageList();
    //dpm($language_list);
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

  public function getInformants() {
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $informant_list = $fusekiAPIservice->informantList();
    $obj = json_decode($informant_list);
    if ($obj->isSuccessful) {
      $informants = $obj->body;
    }
    $results = array();
    foreach ($informants as $informant) {
      $results[$informant->url] = $informant->value;
    }
    return $results;
  }

  public function getGenerationActivities() {
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $generation_activity_list = $fusekiAPIservice->generationActivityList();
    $obj = json_decode($generation_activity_list);
    if ($obj->isSuccessful) {
      $generationActivities = $obj->body;
    }
    $results = array();
    foreach ($generationActivities as $generationActivity) {
      $results[$generationActivity->url] = $generationActivity->value;
    }
    return $results;
  }

}