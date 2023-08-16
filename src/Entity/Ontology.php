<?php

namespace Drupal\sir\Entity;

use Drupal\sir\Utils;
use Drupal\sir\Vocabulary\SIRGUI;

class Ontology {

  public static function generateHeader() {

    return $header = [
      'ontology_abbrev' => t('Abbrev'),
      'ontology_uri' => t('NameSpace'),
      'ontology_name' => t('Source URL'),
      'ontology_mime_type' => t('MIME Type'),
      'ontology_triples' => t('Triples'),
    ];
  
  }

  public static function generateOutput($list) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    $output = array();
    foreach ($list as $ontology) {

      $abbrev = ' ';
      if ($ontology->abbreviation != NULL) {
        $abbrev = $ontology->abbreviation;
      }
      $uri = ' ';
      if ($ontology->uri != NULL) {
        $uri = $ontology->uri;
      }
      $url = ' ';
      if ($ontology->name != NULL) {
        $url = $ontology->url;
      }
      $mimeType = ' ';
      if ($ontology->mimeType != NULL) {
        $mimeType = $ontology->mimeType;
      }
      $triples = ' ';
      if ($ontology->numberOfLoadedTriples != NULL) {
        $triples = $ontology->numberOfLoadedTriples;
      }
      $output[$ontology->uri] = [
        'ontology_abbrev' => $abbrev,     
        'ontology_uri' => t('<a href="'.$uri.'">'.$uri.'</a>'),     
        'ontology_name' => $url,
        'ontology_mime_type' => $mimeType,
        'ontology_triples' => $triples,
      ];
    }
    return $output;

  }

}