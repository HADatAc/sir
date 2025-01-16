<?php

namespace Drupal\sir\Entity;

use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;

class Detector {

  public static function generateHeader() {

    return $header = [
      'element_uri' => t('URI'),
      'element_content' => t('Content'),
      'element_language' => t('Language'),
      'element_version' => t('Version'),
      'element_codebook' => t('Codebook'),
      'element_attribute_of' => t('Attribute Of'),
      'element_status' => t('Status'),
    ];

  }

  public static function generateOutput($list) {

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
      $uri = Utils::namespaceUri($uri);
      $content = ' ';
      $lang = ' ';
      $version = ' ';
      if ($element->detectorStem != NULL) {
        if ($element->detectorStem->hasContent != NULL) {
          $content = $element->detectorStem->hasContent;
        }
        if ($element->detectorStem->hasLanguage != NULL) {
          if ($languages != NULL) {
            $lang = $languages[$element->detectorStem->hasLanguage];
          }
        }
        if ($element->detectorStem->hasVersion != NULL) {
          $version = $element->detectorStem->hasVersion;
        }
      }
      $codebookLabel = ' ';
      if ($element->codebook != NULL && $element->codebook->label != '') {
        $codebookLabel = $element->codebook->label;
      }
      $attributeOf = ' ';
      if ($element->superUri != NULL) {
        $attributeOf = Utils::namespaceUri($element->superUri);
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
        'element_codebook' => $codebookLabel,
        'element_attribute_of' => $attributeOf,
        'element_status' => $status
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
