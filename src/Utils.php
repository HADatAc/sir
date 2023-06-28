<?php

namespace Drupal\sir;

class Utils {

  /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  public static function uriFromAutocomplete($field) {   
    $uri = '';
    if ($field === NULL || $field === '') {
      return $uri;
    }
    preg_match('/\[([^\]]*)\]/', $field, $match);
    $uri = $match[1];
    return $uri;
  }

  /**
   * 
   *  Return the value of configuration parameter api_ulr
   * 
   *  @var string
   */
  public static function configApiUrl() {   
    $config = \Drupal::config(Utils::CONFIGNAME);           
    return $config->get("api_url");
  }

  /**
   * 
   *  Return the value of configuration parameter repository_abbreviation
   * 
   *  @var string
   */
  public static function configRepositoryAbbreviation() {   
    $config = \Drupal::config(Utils::CONFIGNAME);           
    return $config->get("repository_abbreviation");
  }

}