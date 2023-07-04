<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class AddAttachmentsForm extends FormBase {

  protected $instrumentUri;

  public function getInstrumentUri() {
    return $this->instrumentUri;
  }

  public function setInstrumentUri($uri) {
    return $this->instrumentUri = $uri; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_attachments_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {
    $uri=$instrumenturi ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setInstrumentUri($uri_decode);

    $form['attachment_instrument'] = [
      '#type' => 'textfield',
      '#title' => t('Instrument URI'),
      '#value' => $this->getInstrumentUri(),
      '#disabled' => TRUE,
    ];
    $form['attachment_total_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specify the total number of items for this questionnaire'),
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
      if(strlen($form_state->getValue('attachment_total_number')) < 1) {
        $form_state->setErrorByName('attachment_total_number', $this->t('Please specify a number of items greater than zero.'));
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
      $url = Url::fromRoute('sir.manage_instruments');
      $form_state->setRedirectUrl($url);
      return;
    } 

    try{
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->attachmentAdd($this->getInstrumentUri(),$form_state->getValue('attachment_total_number'));
    
      \Drupal::messenger()->addMessage(t("Attachments has been added successfully."));
      $url = Url::fromRoute('sir.manage_attachments');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getInstrumentUri()));
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the Attachment: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_attachments');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getInstrumentUri()));
      $form_state->setRedirectUrl($url);
    }

  }

}