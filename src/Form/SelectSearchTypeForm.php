<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use BorderCloud\SPARQL\SparqlClient;

class SelectSearchTypeForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'select_search_type_form';
  }

    /**
     * {@inheritdoc}
     */

     protected function getEditableConfigNames() {
      return [
          static::CONFIGNAME,
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
     
    $form['search_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Language.'),
        '#options' => [
          '' => $this->t('Please choose'),
          'questionnaries' => $this->t('Questionnaries'),
          'scales' => $this->t('Scales'),
          'symptoms' => $this->t('Symptoms'),
        ],
      ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if($form_state->getValue('search_type') == "") {
      $form_state->setErrorByName('search_type', $this->t('Please choose a search type'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addMessage(t("ok"));
  }
}