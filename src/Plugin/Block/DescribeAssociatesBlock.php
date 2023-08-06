<?php

namespace Drupal\sir\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'DescribeAssociatesBlock' block.
 *
 * @Block(
 *  id = "describe_associates_block",
 *  admin_label = @Translation("Describe Associates"),
 *  category = @Translation("Describe Associates")
 * )
 */
class DescribeAssociatesBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    //return [
    //  '#markup' => $this->t('Hello, World!'),
    //];

    $form = \Drupal::formBuilder()->getForm('Drupal\sir\Form\DescribeAssociatesForm');

    return $form;
  }

}
