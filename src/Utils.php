<?php

namespace Drupal\sir;

use Drupal\Core\Url;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\SIRGUI;

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
   *  Returns the value of configuration parameter repository_iri
   * 
   *  @var string
   */
  public static function configRepositoryURI() {   
    // RETRIEVE CONFIGURATION FROM CURRENT IP
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $repo = $fusekiAPIservice->repoInfo();
    $obj = json_decode($repo);
    if ($obj->isSuccessful) {
      $repoObj = $obj->body;
      return $repoObj->hasDefaultNamespaceURL;
    }
    return NULL;
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
    $repoUri = Utils::configRepositoryURI();
    if ($repoUri == NULL) {
      return NULL;
    }
    if (!str_ends_with($repoUri,'/')) {
      $repoUri .= '/';
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
      if ($abbrev != NULL && $abbrev != "" && $ns != NULL && $ns != "") {
        if (str_starts_with($uri,$ns)) {
          $replacement = $abbrev . ":";
          return str_replace($ns, $replacement ,$uri);
        }
      }
    }
    return $uri;
  }

  public static function sirUriLink($uri) {
    $root_url = \Drupal::request()->getBaseUrl();
    $uriFinal = Utils::namespaceUri($uri);
    $link = '<a href="'.$root_url.SIRGUI::DESCRIBE_PAGE.base64_encode($uri).'">' . $uriFinal . '</a>';
    return $link;
  }

  public static function plainUri($uri) {
    if ($uri == NULL) {
      return NULL;
    }

    $pos = strpos($uri, ':');
    if ($pos === false) {
      return $uri;
    }
    $potentialNs = substr($uri,0, $pos);

    $tables = new Tables;
    $namespaces = $tables->getNamespaces();

    foreach ($namespaces as $abbrev => $ns) {
      if ($potentialNs == $abbrev) {
        $match = $potentialNs . ":";
        return str_replace($match, $ns ,$uri);
      }
    }
    return $uri;
  }

}