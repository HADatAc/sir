<?php

namespace Drupal\sir\Entity;

use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\SIRGUI;
use Drupal\sir\Utils;

class Instrument {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_abbreviation' => t('Abbreviation'),
      'element_name' => t('Name'),
      'element_language' => t('Language'),
      'element_downloads' => t('Downloads'),
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
      $shortName = ' ';
      if ($element->hasShortName != NULL) {
        $shortName = $element->hasShortName;
      }
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
        $version = '<br><b>Version</b>: ' . $element->hasVersion;
      }
      $root_url = \Drupal::request()->getBaseUrl();
      $encodedUri = rawurlencode(rawurlencode($element->uri));
      $totxt = '<a href="'. $root_url . SIRGUI::DOWNLOAD . 'plain'. '/'. $encodedUri . '">TXT</a>';
      $tohtml = '<a href="'. $root_url . SIRGUI::DOWNLOAD . 'html'. '/'. $encodedUri . '">HTML</a>';
      $topdf = '<a href="'. $root_url . SIRGUI::DOWNLOAD . 'pdf'. '/'. $encodedUri . '">PDF</a>';
      //$tordf = '<a href="'. $root_url . SIRGUI::DOWNLOAD . 'rdf'. '/'. $encodedUri . '">RDF</a>';
      $tordf = ' ';
      $tofhir = '<a href="'. $root_url . SIRGUI::DOWNLOAD . 'fhir'. '/'. $encodedUri . '">FHIR</a>';
      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.SIRGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),     
        'element_abbreviation' => $shortName,     
        'element_name' => t($label . $version),     
        'element_language' => $lang,
        'element_downloads' => t($totxt . ' ' . $tohtml . ' ' . $topdf . '<br>' . $tordf . ' ' . $tofhir),
      ];
    }
    return $output;

  }

}