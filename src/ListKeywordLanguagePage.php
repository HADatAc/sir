<?php

namespace Drupal\sir;

use Drupal\sir\Vocabulary\SIRAPI;

class ListKeywordLanguagePage {

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
    $element_list = $fusekiAPIservice->listByKeywordAndLanguage($elementtype,$keyword,$language,$pagesize,$offset);
    $elements = [];
    if ($element_list != null) {
      $obj = json_decode($element_list);
      if ($obj->isSuccessful) {
        $elements = $obj->body;
      }
    }
    return $elements;

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