<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class AddCodebookForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_codebook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $tables = new Tables;
    $languages = $tables->getLanguages();

    // $form['codebook_type'] = [
    //   'top' => [
    //     '#type' => 'markup',
    //     '#markup' => '<div class="pt-3 col border border-white">',
    //   ],
    //   'main' => [
    //     '#type' => 'textfield',
    //     '#title' => $this->t('Parent Type'),
    //     '#name' => 'codebook_type',
    //     '#default_value' => '',
    //     '#id' => 'codebook_type',
    //     '#parents' => ['codebook_type'],
    //     '#attributes' => [
    //       'class' => ['open-tree-modal'],
    //       'data-dialog-type' => 'modal',
    //       'data-dialog-options' => json_encode(['width' => 800]),
    //       'data-url' => Url::fromRoute('rep.tree_form', [
    //         'mode' => 'modal',
    //         'elementtype' => 'codebook',
    //       ], ['query' => ['field_id' => 'codebook_type']])->toString(),
    //       'data-field-id' => 'codebook_type',
    //       'data-elementtype' => 'codebook',
    //       'autocomplete' => 'off',
    //     ],
    //   ],
    //   'bottom' => [
    //     '#type' => 'markup',
    //     '#markup' => '</div>',
    //   ],
    // ];
    $form['codebook_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['codebook_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['codebook_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['codebook_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];
    $form['codebook_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ]
    ];
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'save') {
      if(strlen($form_state->getValue('codebook_name')) < 1) {
        $form_state->setErrorByName('codebook_name', $this->t('Please enter a valid name for the Codebook'));
      }
      if(strlen($form_state->getValue('codebook_description')) < 1) {
        $form_state->setErrorByName('codebook_description', $this->t('Please enter a valid description of the Codebook'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
  }

    try {
      $uemail = \Drupal::currentUser()->getEmail();
      $newCodebookUri = Utils::uriGen('codebook');
      $codebookJSON = '{"uri":"'.$newCodebookUri.'",' .
        '"typeUri":"'.VSTOI::CODEBOOK.'",'.
        '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"label":"' . $form_state->getValue('codebook_name') . '",' .
        '"hasLanguage":"' . $form_state->getValue('codebook_language') . '",' .
        '"hasVersion":"' . $form_state->getValue('codebook_version') . '",' .
        '"comment":"' . $form_state->getValue('codebook_description') . '",' .
        '"hasWebDocument":"'.$form_state->getValue('codebook_webdocument').'",'.
        '"hasSIRManagerEmail":"' . $uemail . '"}';

      $api = \Drupal::service('rep.api_connector');
      $api->codebookAdd($codebookJSON);
      \Drupal::messenger()->addMessage(t("Codebook has been added successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding an codebook: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_codebook');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
