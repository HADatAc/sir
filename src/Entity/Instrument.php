<?php

namespace Drupal\sir\Entity;

use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\REPGUI;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Utils;

class Instrument {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_type' => t('Parent Type'),
      'element_abbreviation' => t('Abbreviation'),
      'element_name' => t('Name'),
      'element_language' => t('Language'),
      'element_downloads' => t('Downloads'),
      'element_status' => t('Status'),
    ];

  }

  public static function generatereviewHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_type' => t('Parent Type'),
      'element_abbreviation' => t('Abbreviation'),
      'element_name' => t('Name'),
      'element_language' => t('Language'),
      'element_downloads' => t('Downloads'),
      'element_owner' => t('Owner'),
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
    $isQuestionnaire = false;
    foreach ($list as $element) {
      $isQuestionnaire = Utils::hasQuestionnaireAncestor($element->uri);
      $uri = ' ';
      if ($element->uri != NULL) {
        $uri = $element->uri;
      }
      $type = ' ';
      if ($element->superUri != NULL) {
        $type = Utils::namespaceUri($element->superUri);
      }
      $uri = Utils::namespaceUri($uri);
      $shortName = ' ';
      if ($element->hasShortName != NULL) {
        $shortName = $element->hasShortName;
      }
      $label = ' ';
      if ($element->label != NULL) {
        $label = $element->label;
      }
      $lang = ' ';
      if ($element->hasLanguage != NULL) {
        if ($languages != NULL) {
          $lang = $languages[$element->hasLanguage];
        }
      }
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = '<br><b>Version</b>: ' . $element->hasVersion;
      }

      $encodedUri = rawurlencode(rawurlencode($element->uri));
      $totxt = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'plain'. '/'. $encodedUri . '">TXT</a>';

      if ($isQuestionnaire) {
        $tohtml = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'html'. '/'. $encodedUri . '">HTML</a>';
        $topdf = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'pdf'. '/'. $encodedUri . '">PDF</a>';
        //$tordf = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'rdf'. '/'. $encodedUri . '">RDF</a>';
        $tordf = ' ';
        $tofhir = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'fhir'. '/'. $encodedUri . '">FHIR</a>';
      } else {
        $tohtml = '';
        $topdf = '';
        $tordf = '';
        $tofhir = '';
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
        } else {
          $status = Utils::plainStatus($element->hasStatus);
        }

      }
      $output[$row_key] = [
        'element_uri' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),
        'element_type' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($type).'">'.$type.'</a>'),
        'element_abbreviation' => $shortName,
        'element_name' => t($label . $version),
        'element_language' => $lang,
        // 'element_downloads' => t($totxt . ' ' . $tohtml . ' ' . $topdf . '<br>' . $tordf . ' ' . $tofhir),
        'element_downloads' => t($totxt . ' ' . $tohtml . ' ' . $tordf . ' ' . $tofhir),
        'element_status' => $status,
        'element_hasStatus' => parse_url($element->hasStatus, PHP_URL_FRAGMENT),
        'element_hasLanguage' => $element->hasLanguage,
        'element_hasImageUri' => $element->hasImageUri,
      ];
    }

    // Para garantir que disabled_rows seja um array associativo
    $normalized_disabled_rows = array_fill_keys($disabled_rows, TRUE);

    return [
      'output'        => $output,
      'disabled_rows' => $normalized_disabled_rows,
    ];

  }

  public static function generateReviewOutput($list) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // GET LANGUAGES
    $tables = new Tables;
    $languages = $tables->getLanguages();

    $output = array();
    $isQuestionnaire = false;
    foreach ($list as $element) {
      $isQuestionnaire = Utils::hasQuestionnaireAncestor($element->uri);
      $uri = ' ';
      if ($element->uri != NULL) {
        $uri = $element->uri;
      }
      $type = ' ';
      if ($element->superUri != NULL) {
        $type = Utils::namespaceUri($element->superUri);
      }
      $uri = Utils::namespaceUri($uri);
      $shortName = ' ';
      if ($element->hasShortName != NULL) {
        $shortName = $element->hasShortName;
      }
      $label = ' ';
      if ($element->label != NULL) {
        $label = $element->label;
      }
      $lang = ' ';
      if ($element->hasLanguage != NULL) {
        if ($languages != NULL) {
          $lang = $languages[$element->hasLanguage];
        }
      }
      $version = ' ';
      if ($element->hasVersion != NULL) {
        $version = '<br><b>Version</b>: ' . $element->hasVersion;
      }

      $encodedUri = rawurlencode(rawurlencode($element->uri));
      $totxt = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'plain'. '/'. $encodedUri . '">TXT</a>';

      if ($isQuestionnaire) {
        $tohtml = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'html'. '/'. $encodedUri . '">HTML</a>';
        $topdf = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'pdf'. '/'. $encodedUri . '">PDF</a>';
        //$tordf = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'rdf'. '/'. $encodedUri . '">RDF</a>';
        $tordf = ' ';
        $tofhir = '<a href="'. $root_url . REPGUI::DOWNLOAD . 'fhir'. '/'. $encodedUri . '">FHIR</a>';
      } else {
        $tohtml = '';
        $topdf = '';
        $tordf = '';
        $tofhir = '';
      }

      $status = ' ';
      $row_key = $element->uri;
      if ($element->hasStatus != NULL) {

        // GET STATUS
        if ($element->hasStatus === VSTOI::DRAFT && $element->hasReviewNote !== NULL) {
          $status = "Draft (Already Reviewed)";
        } else {
          $status = Utils::plainStatus($element->hasStatus);
        }

      }
      $owner = ' ';
      if ($element->hasSIRManagerEmail != NULL) {
        $owner = $element->hasSIRManagerEmail;
      }
      $output[$row_key] = [
        'element_uri' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),
        'element_type' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($type).'">'.$type.'</a>'),
        'element_abbreviation' => $shortName,
        'element_name' => t($label . $version),
        'element_language' => $lang,
        // 'element_downloads' => t($totxt . ' ' . $tohtml . ' ' . $topdf . '<br>' . $tordf . ' ' . $tofhir),
        'element_downloads' => t($totxt . ' ' . $tohtml . ' ' . $tordf . ' ' . $tofhir),
        'element_status' => $status,
        'element_owner' => $owner,
        'element_hasStatus' => parse_url($element->hasStatus, PHP_URL_FRAGMENT),
        'element_hasLanguage' => $element->hasLanguage,
        'element_hasImageUri' => $element->hasImageUri,
      ];
    }

    return $output;
  }

}
