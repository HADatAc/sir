<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class AddCodebookSlotsForm extends FormBase {

  protected $codebookUri;

  public function getCodebookUri() {
    return $this->codebookUri;
  }

  public function setCodebookUri($uri) {
    return $this->codebookUri = $uri; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_codebook_slot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebookuri = NULL) {
    $uri=$codebookuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setCodebookUri($uri_decode);

    $form['slot_instrument'] = [
      '#type' => 'textfield',
      '#title' => t('Codebook URI'),
      '#value' => $this->getCodebookUri(),
      '#disabled' => TRUE,
    ];
    $form['slot_total_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specify the total number of slots for this codebook'),
    ];
    $form['save_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('slot_total_number')) < 1) {
        $form_state->setErrorByName('slot_total_number', $this->t('Please specify a number of codebook slots greater than zero.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.manage_codebooks');
      $form_state->setRedirectUrl($url);
      return;
    } 

    try {
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->codebookSlotAdd($this->getCodebookUri(),$form_state->getValue('slot_total_number'));
    
      \Drupal::messenger()->addMessage(t("Codebook Slots has been added successfully."));
      $url = Url::fromRoute('sir.manage_codebook_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getCodebookUri()));
      $form_state->setRedirectUrl($url);

    } catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the Codebook slots: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_codebook_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getCodebookUri()));
      $form_state->setRedirectUrl($url);
    }

  }

}