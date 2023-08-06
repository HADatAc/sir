<?php

namespace Drupal\sir\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'SIRSearchBlock' block.
 *
 * @Block(
 *  id = "search_block",
 *  admin_label = @Translation("Search Criteria"),
 *  category = @Translation("Search Criteria")
 * )
 */
class SIRSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\sir\Form\SIRSearchForm');

    return $form;
  }

}
