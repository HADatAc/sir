<?php

namespace Drupal\sir\Entity;

use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\SIRAPI;
use Drupal\sir\Utils;

class Instrument {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_abbreviation' => t('Abbreviation'),
      'element_name' => t('Name'),
      'element_language' => t('Language'),
      'element_version' => t('Version'),
      'element_rendering_downloads' => t('Rendering Downloads'),
      'element_interoperability_downloads' => t('Interoperability Downloads'),
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
        $lang = $languages[$element->hasLanguage];
      }
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = $element->hasVersion;
      }
      $root_url = \Drupal::request()->getBaseUrl();
      $encodedUri = rawurlencode(rawurlencode($element->uri));
      $totxt = '<a href="'. $root_url . SIRAPI::DOWNLOAD . 'plain'. '/'. $encodedUri . '">TXT</a>';
      $tohtml = '<a href="'. $root_url . SIRAPI::DOWNLOAD . 'html'. '/'. $encodedUri . '">HTML</a>';
      $topdf = '<a href="'. $root_url . SIRAPI::DOWNLOAD . 'pdf'. '/'. $encodedUri . '">PDF</a>';
      $tordf = '<a href="'. $root_url . SIRAPI::DOWNLOAD . 'rdf'. '/'. $encodedUri . '">RDF</a>';
      $tofhir = '<a href="'. $root_url . SIRAPI::DOWNLOAD . 'fhir'. '/'. $encodedUri . '">FHIR</a>';
      $output[$element->uri] = [
        'element_uri' => $uri,
        'element_abbreviation' => $shortName,     
        'element_name' => $label,     
        'element_language' => $lang,
        'element_version' => $version,
        'element_rendering_downloads' => t($totxt . ' ' . $tohtml . ' ' . $topdf),
        'element_interoperability_downloads' => t($tordf . ' ' . $tofhir),
      ];
    }
    return $output;

  }

}