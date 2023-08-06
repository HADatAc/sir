<?php

namespace Drupal\sir\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'DescribeDerivationBlock' block.
 *
 * @Block(
 *  id = "describe_derivation_block",
 *  admin_label = @Translation("Describe Derivation"),
 *  category = @Translation("Describe Derivation")
 * )
 */
class DescribeDerivationBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    //return [
    //  '#markup' => $this->t('Hello, World!'),
    //];

    $form = \Drupal::formBuilder()->getForm('Drupal\sir\Form\DescribeDerivationForm');

    return $form;
  }

}
