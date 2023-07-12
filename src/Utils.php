<?php

namespace Drupal\sir;

use Drupal\Core\Url;
use Drupal\sir\Entity\Tables;

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
   *  Returns the value of configuration parameter api_ulr
   * 
   *  @var string
   */
  public static function configApiUrl() {   
    $config = \Drupal::config(Utils::CONFIGNAME);           
    return $config->get("api_url");
  }

  /**
   * 
   *  Returns the value of configuration parameter repository_abbreviation
   * 
   *  @var string
   */
  public static function configRepositoryAbbreviation() {   
    $config = \Drupal::config(Utils::CONFIGNAME);           
    return $config->get("repository_abbreviation");
  }

  /**
   * 
   *  Returns the value of configuration parameter repository_iri
   * 
   *  @var string
   */
  public static function configRepositoryIRI() {   
    $config = \Drupal::config(Utils::CONFIGNAME);           
    return $config->get("repository_iri");
  }

  /**
   * 
   *  Generates a new URI for a given $element_type
   * 
   * @var string
   * 
   */
  public static function uriGen($element_type) {
    if ($element_type == NULL) {
      return NULL;
    }
    switch ($element_type) {
      case "instrument":
        $short = "IN";
        break;
      case "detector":
        $short = "DT";
        break;
      case "experience":
        $short = "EX";
        break;
      case "responseoption":
        $short = "RO";
        break;
      default:
        $short = NULL;
    }
    if ($short == NULL) {
      return NULL;
    }
    $repoUri = Utils::configRepositoryIRI();
    if ($repoUri == NULL) {
      return NULL;
    }
    if (!str_ends_with($repoUri,'/')) {
      $repoUri += '/';
    }
    $uid = \Drupal::currentUser()->id();
    $iid = time().rand(10000,99999).$uid;
    return $repoUri . $short . $iid;
  }

  /**
   * 
   *  To be used inside of Add*Form and Edit*Form documents. The function return the URL 
   *  to the SelectForm Form with the corresponding concept.
   * 
   *  @var \Drupal\Core\Url  
   * 
   */
  public static function selectBackUrl($element_type) {  
    $url = Url::fromRoute('sir.select_element');
    $url->setRouteParameter('elementtype', $element_type);
    $url->setRouteParameter('page', '1');
    $url->setRouteParameter('pagesize', '12');
    return $url;
  }

  public static function namespaceUri($uri) {
    $tables = new Tables;
    $namespaces = $tables->getNamespaces();

    foreach ($namespaces as $abbrev => $ns) {
      if (str_starts_with($uri,$ns)) {
        $replacement = $abbrev . ":";
        return str_replace($ns, $replacement ,$uri);
      }
    }
    return $uri;
  }

}