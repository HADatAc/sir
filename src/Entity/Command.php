<?php

namespace Drupal\sir\Entity;

use Drupal\rep\Entity\Tables;
use Drupal\rep\Utils;
use Drupal\Core\Render\Markup;

class Command {

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

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $output = [];
    $disabled_rows = [];

    foreach ($list as $element) {
      $row_key = (string) ($element->uri ?? '');

      $uri = Utils::namespaceUri((string) ($element->uri ?? ''));
      $content = (string) ($element->hasContent ?? ' ');

      $lang = ' ';
      if (!empty($element->hasLanguage) && is_array($languages) && isset($languages[$element->hasLanguage])) {
        $lang = $languages[$element->hasLanguage];
      }

      $version = (string) ($element->hasVersion ?? ' ');

      $status_fragment = '';
      $status = ' ';
      if (!empty($element->hasStatus)) {
        $status_fragment = (string) parse_url($element->hasStatus, PHP_URL_FRAGMENT);
        $status = $status_fragment;
        if ($status_fragment === 'Under Review') {
          $disabled_rows[] = $row_key;
        }
      }

      $output[$row_key] = [
        'element_uri' => Markup::create(Utils::describeAnchor((string) ($element->uri ?? ''), (string) $uri)),
        'element_content' => $content,
        'element_language' => $lang,
        'element_version' => $version,
        'element_status' => $status,
        'element_hasStatus' => $status_fragment,
        'element_hasLanguage' => (string) ($element->hasLanguage ?? ''),
        'element_hasImageUri' => (string) ($element->hasImageUri ?? ''),
      ];
    }

    $normalized_disabled_rows = array_fill_keys($disabled_rows, TRUE);

    return [
      'output' => $output,
      'disabled_rows' => $normalized_disabled_rows,
    ];

  }

}
