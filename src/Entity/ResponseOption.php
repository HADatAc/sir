<?php

namespace Drupal\sir\Entity;

use Drupal\rep\Entity\Tables;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;
use Drupal\rep\Vocabulary\VSTOI;

class ResponseOption {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_content' => t('Content'),
      'element_language' => t('Language'),
      'element_version' => t('Version'),
      'element_status' => t('Status'),
    ];

  }

  public static function generateOutput($list) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // GET LANGUAGES
    $tables = new Tables;
    $languages = $tables->getLanguages();

    $output = array();
    $disabled_rows = [];
    foreach ($list as $element) {
      $uri = ' ';
      if ($element->uri != NULL) {
        $uri = $element->uri;
      }
      $uri = Utils::namespaceUri($uri);
      $content = ' ';
      if ($element->hasContent != NULL) {
        $content = $element->hasContent;
      }
      $lang = ' ';
      if ($element->hasLanguage != NULL) {
        if ($languages != NULL) {
          $lang = $languages[$element->hasLanguage];
        }
      }
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = $element->hasVersion;
      }
      $status = ' ';
      $row_key = $element->uri;
      if ($element->hasStatus != NULL) {

        // DISABLE SUBMIT FOR REVIEW BASED ON STATUS
        if (
          $element->hasStatus === VSTOI::UNDER_REVIEW ||
          $element->hasStatus === VSTOI::CURRENT ||
          $element->hasStatus === VSTOI::DEPRECATED
        ) {
          $disabled_rows[] = $row_key;
        }

        // GET STATUS
        if ($element->hasStatus === VSTOI::DRAFT && $element->hasReviewNote !== NULL) {
          $status = "Draft (Already Reviewed)";
        } else if($element->hasStatus === VSTOI::UNDER_REVIEW) {
          $status = "Under Review";
        } else {
          $status = parse_url($element->hasStatus, PHP_URL_FRAGMENT);
        }

      }
      $output[$row_key] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),
        'element_content' => $content,
        'element_language' => $lang,
        'element_version' => $version,
        'element_status' => $status,
        'element_hasStatus' => parse_url($element->hasStatus, PHP_URL_FRAGMENT),
      ];
    }

    // Para garantir que disabled_rows seja um array associativo
    $normalized_disabled_rows = array_fill_keys($disabled_rows, TRUE);

    return [
      'output'        => $output,
      'disabled_rows' => $normalized_disabled_rows,
    ];

  }

  public static function generateReviewHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_content' => t('Content'),
      'element_language' => t('Language'),
      'element_version' => t('Version'),
      'element_status' => t('Status'),
      'element_owner' => t('Owner'),
    ];

  }

  public static function generateReviewOutput($list) {

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
      $content = ' ';
      if ($element->hasContent != NULL) {
        $content = $element->hasContent;
      }
      $lang = ' ';
      if ($element->hasLanguage != NULL) {
        if ($languages != NULL) {
          $lang = $languages[$element->hasLanguage];
        }
      }
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = $element->hasVersion;
      }
      $status = ' ';
      if ($element->hasStatus != NULL) {

        // GET STATUS
        if ($element->hasStatus === VSTOI::DRAFT && $element->hasReviewNote !== NULL) {
          $status = "Draft (Already Reviewed)";
        } else if($element->hasStatus === VSTOI::UNDER_REVIEW) {
          $status = "Under Review";
        } else {
          $status = parse_url($element->hasStatus, PHP_URL_FRAGMENT);
        }

      }
      $owner = ' ';
      if ($element->hasSIRManagerEmail != NULL) {
        $owner = $element->hasSIRManagerEmail;
      }
      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),
        'element_content' => $content,
        'element_language' => $lang,
        'element_version' => $version,
        'element_status' => $status,
        'element_owner' => $owner,
        'element_hasStatus' => parse_url($element->hasStatus, PHP_URL_FRAGMENT),
      ];
    }

    //dpm($output);
    return $output;

  }

  /**
   * Clone Response Option
   */
  public static function cloneResponseOption($uri, $status = VSTOI::UNDER_REVIEW) {

    $api = \Drupal::service('rep.api_connector');
    $result = $api->responseOptionGet($uri);

    // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
    if ($result->wasDerivedFrom !== NULL
      && Utils::checkDerivedElements($uri) === false) {
        \Drupal::messenger()->addError(t('There is a previous version that has the same content.'), ['@elements' => 'Response Option']);
        return false;

    } elseif ($result->wasDerivedFrom === NULL) { // CENARIO #2: CHECK IF THERE ARE ANY OTHER R.O. WITH SAME CONTENT ALREADY IN REP

      //$response = $api->listSizeByKeywordAndLanguage($this->element_type, $result->hasContent, $result->hasLanguage);
      $response = $api->listByKeywordAndLanguage('responseoption', $result->hasContent, $result->hasLanguage, 99999, 0);
      if ($response > 1) {
        \Drupal::messenger()->addError(t('There is already a Response Option with the same content on the Repository.'), ['@elements' => 'Response Option']);
        return false;
      }
    }

    // NO RESTRITIONS? SEND TO REVIEW
    $clonedObject = $result;
    $clonedObject->hasStatus = $status;

    // UNSET UNNECESSARY PROPERTIES
    unset($clonedObject->deletable);
    unset($clonedObject->count);
    unset($clonedObject->uriNamespace);
    unset($clonedObject->typeNamespace);
    unset($clonedObject->label);
    unset($clonedObject->nodeId);
    unset($clonedObject->field);
    unset($clonedObject->query);
    unset($clonedObject->namedGraph);
    unset($clonedObject->serialNumber);
    unset($clonedObject->image);
    unset($clonedObject->typeLabel);
    unset($clonedObject->hascoTypeLabel);

    $finalObject = json_encode($clonedObject, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // UPDATE BY DELETING AND CREATING
    $api = \Drupal::service('rep.api_connector');
    $api->responseOptionDel($uri);
    $api->responseOptionAdd($finalObject);

  }
}
