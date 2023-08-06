<?php

namespace Drupal\sir\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'DescribeHeaderBlock' block.
 *
 * @Block(
 *  id = "describe_header_block",
 *  admin_label = @Translation("Describe Header"),
 *  category = @Translation("Describe Header")
 * )
 */
class DescribeHeaderBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $form = \Drupal::formBuilder()->getForm('Drupal\sir\Form\DescribeHeaderForm');

    return $form;
  }

}
