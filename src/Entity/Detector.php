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
      'element_type' => t('Attribute Of'),
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
      $output[$element->uri] = [
        'element_uri' => t('<a href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($uri).'">'.$uri.'</a>'),
        'element_content' => $content,
        'element_language' => $lang,
        'element_version' => $version,
        'element_codebook' => $codebookLabel,
        'element_attribute_of' => $attributeOf,
      ];
    }
    return $output;

  }

}
