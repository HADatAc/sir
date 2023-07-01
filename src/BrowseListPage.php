<?php

namespace Drupal\sir;

use Drupal\sir\Vocabulary\SIRAPI;

class BrowseListPage {

  public static function exec($elementtype, $keyword, $language, $page, $pagesize) {
    if ($elementtype == NULL || $page == NULL || $pagesize == NULL) {
        $resp = array();
        return $resp;
    }

    $offset = -1;
    if ($page <= 1) {
      $offset = 0;
    } else {
      $offset = ($page - 1) * $pagesize;
    }

    if ($keyword == NULL) {
      $keyword = "_";
    }
    if ($language == NULL) {
      $language = "_";
    }
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $detector_list = $fusekiAPIservice->listByKeywordAndLanguage($elementtype,$keyword,$language,$pagesize,$offset);
    $detectors = [];
    if ($detector_list != null) {
      $obj = json_decode($detector_list);
      if ($obj->isSuccessful) {
        $detectors = $obj->body;
      }
    }
    //$resp = [];
    //foreach ($detectors as &$det) {
    //  $value = $det->uri . ' [' . $det->hasContent . ']';
    //  array_push($resp, $value);
    //}
    //unset($det);
    return $detectors;

  }

  public static function total($elementtype, $keyword, $language) {
    if ($elementtype == NULL) {
      return -1;
    }
    if ($keyword == NULL) {
      $keyword = "_";
    }
    if ($language == NULL) {
      $language = "_";
    }
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $response = $fusekiAPIservice->listSizeByKeywordAndLanguage($elementtype,$keyword,$language);
    $listSize = -1;
    if ($response != null) {
      $obj = json_decode($response);
      if ($obj->isSuccessful) {
        $listSizeStr = $obj->body;
        $obj2 = json_decode($listSizeStr);
        $listSize = $obj2->total;
      }
    }
    return $listSize;

  }

  public static function link($elementtype, $keyword, $language, $page, $pagesize) {
    $root_url = \Drupal::request()->getBaseUrl();
    if ($elementtype != NULL && $page > 0 && $pagesize > 0) {
      return $root_url . SIRAPI::LIST_PAGE . 
          $elementtype . '/' .
          $keyword . '/' .
          $language . '/' .
          strval($page) . '/' . 
          strval($pagesize);
    }
    return ''; 
  }

}

?>