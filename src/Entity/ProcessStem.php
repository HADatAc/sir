<?php

namespace Drupal\sir\Entity;

use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class ProcessStem {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_type' => t('Parent Type'),
      'element_content' => t('Content'),
      'element_language' => t('Language'),
      'element_version' => t('Version'),
      'element_generated_by' => t('Was Generated By'),
      'element_status' => t('Status'),
    ];

  }

  public static function generateOutput($list) {

    //dpm($list);
    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // GET LANGUAGES
    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $output = array();
    $disabled_rows = [];
    foreach ($list as $element) {
      $uri = ' ';
      if ($element->uri != NULL) {
        $uri = $element->uri;
      }
      $type = ' ';
      if ($element->superUri != NULL) {
        $type = Utils::namespaceUri($element->superUri);
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
      $derivationVal = $derivations["http://hadatac.org/ont/vstoi#Original"];
      if ($element->wasGeneratedBy != NULL && $element->wasGeneratedBy != '') {
        if ($derivations != NULL) {
          $derivationVal = $derivations[$element->wasGeneratedBy];
        }
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
        'element_type' => $type,
        'element_content' => $content,
        'element_language' => $lang,
        'element_version' => $version,
        'element_generated_by' => $derivationVal,
        'element_status' => $status,
        'element_hasStatus' => parse_url($element->hasStatus, PHP_URL_FRAGMENT),
        'element_hasLanguage' => $element->hasLanguage,
      ];
    }

    // Para garantir que disabled_rows seja um array associativo
    $normalized_disabled_rows = array_fill_keys($disabled_rows, TRUE);

    return [
      'output'        => $output,
      'disabled_rows' => $normalized_disabled_rows,
    ];

  }

}
