<?php

namespace Drupal\sir\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\SearchInstrumentForm;

/**
 * Provides a 'SearchInstrumentBlock' block.
 *
 * @Block(
 *  id = "search_instrument_block",
 *  admin_label = @Translation("Search Instrument"),
 * category = @Translation("Search Instrument")
 * )
 */
class SearchInstrumentBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\sir\Form\SearchInstrumentForm');

    return $form;
  }

}