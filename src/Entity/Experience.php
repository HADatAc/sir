<?php

namespace Drupal\sir\Entity;

use Drupal\sir\Entity\Tables;
use Drupal\sir\Utils;
use Drupal\sir\Vocabulary\SIRAPI;

class Experience {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_name' => t('Name'),
      'element_language' => t('Language'),
      'element_version' => t('Version'),
    ];
  
  }

  public static function generateOutput($list) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // GET LANGUAGES
    $tables = new Tables;
    $languages = $tables->getLanguages();
 
    $output = array();
    foreach ($list as $element) {
      $uri = ' ';
      if ($element->uri != NULL) {
        $uri = $element->uri;
      }
      $uri = Utils::namespaceUri($uri);
      $label = ' ';
      if ($element->label != NULL) {
        $label = $element->label;
      }
      $lang = ' ';
      if ($element->hasLanguage != NULL) {
        if ($languages != NULL) {
          $lang = $languages[$element->hasLanguage];
        }
      }
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = $element->hasVersion;
      }
      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.SIRAPI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),     
        'element_name' => $label,     
        'element_language' => $lang,
        'element_version' => $version,
        //'element_uri' => $element->uri,
      ];
    }
    return $output;

  }

}