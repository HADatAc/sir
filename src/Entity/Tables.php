<?php

namespace Drupal\sir\Entity;

//use Kint\Kint;

class Tables {
  
  public function getNamespaces() {
    $APIservice = \Drupal::service('sir.api_connector');
    $namespaces = $APIservice->parseObjectResponse($APIservice->namespaceList(), 'namespaceList');
    if ($namespaces == NULL) {
      return NULL;
    }
    $results = array();
    foreach ($namespaces as $namespace) {
      $results[$namespace->label] = $namespace->uri;
    }
    return $results;
  }

  public function getLanguages() {
    $APIservice = \Drupal::service('sir.api_connector');
    $languages = $APIservice->parseObjectResponse($APIservice->languageList(), 'languageList');
    if ($languages == NULL) {
      return NULL;
    }
    $results = array();
    foreach ($languages as $language) {
      $results[$language->code] = $language->value;
    }
    return $results;
  }

  public function getInformants() {
    $APIservice = \Drupal::service('sir.api_connector');
    $informants = $APIservice->parseObjectResponse($APIservice->informantList(), 'informantList');
    if ($informants == NULL) {
      return NULL;
    }
    $results = array();
    foreach ($informants as $informant) {
      $results[$informant->url] = $informant->value;
    }
    return $results;
  }

  public function getGenerationActivities() {
    $APIservice = \Drupal::service('sir.api_connector');
    $generationActivities = $APIservice->parseObjectResponse($APIservice->generationActivityList(), 'generationActivityList');
    if ($generationActivities == NULL) {
      return NULL;
    }
    $results = array();
    foreach ($generationActivities as $generationActivity) {
      $results[$generationActivity->url] = $generationActivity->value;
    }
    return $results;
  }

}