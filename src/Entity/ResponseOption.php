<?php

namespace Drupal\sir\Entity;

use Drupal\rep\Entity\Tables;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

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
        $status = parse_url($element->hasStatus, PHP_URL_FRAGMENT);

        if (parse_url($element->hasStatus, PHP_URL_FRAGMENT) === 'Under Review') {
          $status = "Under Review";
          $disabled_rows[] = $row_key;
        }

      }
      $output[$row_key] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),
        'element_content' => $content,
        'element_language' => $lang,
        'element_version' => $version,
        'element_status' => $status,
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
        $status = parse_url($element->hasStatus, PHP_URL_FRAGMENT);

        if (parse_url($element->hasStatus, PHP_URL_FRAGMENT) === 'UnderReview')
          $status = "Under Review";
      }
      $owner = ' ';
      if ($element->hasOwner != NULL) {
        $status = $element->hasOwner;
      }
      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),
        'element_content' => $content,
        'element_language' => $lang,
        'element_version' => $version,
        'element_status' => $status,
        'element_owner' => $owner,
      ];
    }

    //dpm($output);
    return $output;

  }
}
