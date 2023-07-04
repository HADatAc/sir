<?php

namespace Drupal\sir\Entity;

use Drupal\sir\Entity\Tables;

class ResponseOption {

  public static function generateHeader() {

    return $header = [
      'element_content' => t('Content'),
      'element_language' => t('Language'),
      'element_version' => t('Version'),
      //'element_uri' => t('URI'),
    ];

  }

  public static function generateOutput($list) {

    // GET LANGUAGES
    $tables = new Tables;
    $languages = $tables->getLanguages();

    $output = array();
    foreach ($list as $element) {
      $content = ' ';
      if ($element->hasContent != NULL) {
        $content = $element->hasContent;
      }
      $lang = ' ';
      if ($element->hasLanguage != NULL) {
        $lang = $languages[$element->hasLanguage];
      }
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = $element->hasVersion;
      }
      $output[$element->uri] = [
        'element_content' => $content,     
        'element_language' => $lang,
        'element_version' => $version,
        //'element_uri' => $element->uri,
      ];
    }
    return $output;

  }

}