<?php

namespace Drupal\sir\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\SelectSearchTypeForm;

/**
 * Provides a 'SelectSearchTypeForm' block.
 *
 * @Block(
 *  id = "select_search_type_block",
 *  admin_label = @Translation("Select Search Type"),
 * category = @Translation("Select Search Type")
 * )
 */
class SelectSearchTypeBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('Drupal\sir\Form\SelectSearchTypeForm');

    return $form;
  }

}