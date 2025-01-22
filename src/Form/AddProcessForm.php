<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class AddProcessForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_process_form';
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


    $form['process_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['process_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['process_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['process_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['process_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
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
      if(strlen($form_state->getValue('process_name')) < 1) {
        $form_state->setErrorByName('process_name', $this->t('Please enter a valid name for the Process'));
      }
      if(strlen($form_state->getValue('process_description')) < 1) {
        $form_state->setErrorByName('process_description', $this->t('Please enter a valid description of the Process'));
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
      $newProcessUri = Utils::uriGen('process');
      $processJSON = '{"uri":"'.$newProcessUri.'",' .
        '"typeUri":"'.VSTOI::PROCESS.'",'.
        '"hascoTypeUri":"'.VSTOI::PROCESS.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"label":"' . $form_state->getValue('process_name') . '",' .
        '"hasLanguage":"' . $form_state->getValue('process_language') . '",' .
        '"hasVersion":"' . $form_state->getValue('process_version') . '",' .
        '"comment":"' . $form_state->getValue('process_description') . '",' .
        '"hasSIRManagerEmail":"' . $uemail . '"}';

      $api = \Drupal::service('rep.api_connector');
      $api->processAdd($processJSON);
      \Drupal::messenger()->addMessage(t("Process has been added successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding an process: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_process');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
