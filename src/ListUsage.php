<?php

namespace Drupal\sir;

use Drupal\sir\Utils;

class ListUsage {

  public static function exec($uri) {
    if ($uri == NULL) {
        $resp = array();
        return $resp;
    }
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $element_list = $fusekiAPIservice->getUsage($uri);
    $elements = [];
    if ($element_list != null) {
      $obj = json_decode($element_list);
      //dpm($obj);
      if ($obj->isSuccessful) {
        $elements = $obj->body;
      }
    }
    return $elements;
  }

  public static function fromDetectorToHtml($detectorslots) {
    $html = "<ul>";
    if (sizeof($detectorslots) <= 0) {
      $html .= "<li>NONE</li>";
    } else {
      foreach ($detectorslots as $detectorslot) {
        $instrument = ListUsage::getInstrument($detectorslot->belongsTo);
        if ($instrument != NULL) {
          //dpm($detectorslot);
          $html .= "<li>Position " . $detectorslot->hasPriority . " in Questionnaire " . $instrument->label . " (" . Utils::sirUriLink($instrument->uri) . ")</li>"; 
        }
      }     
    }
    $html .= "</ul>";
    return $html;
  }

  public static function getInstrument($uri) {
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $instrument = "";
    $instrumentRaw = $fusekiAPIservice->getUri($uri);
    if ($instrumentRaw != null) {
      $instrumentJson = json_decode($instrumentRaw);
      //dpm($obj);
      if ($instrumentJson->isSuccessful) {
        $instrument = $instrumentJson->body;
      }
    }
    return $instrument;
  }

}

?>