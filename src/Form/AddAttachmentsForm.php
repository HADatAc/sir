<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

class AddAttachmentsForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

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

     protected function getEditableConfigNames() {
      return [
          static::CONFIGNAME,
      ];
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
    $submitted_values = $form_state->cleanValues()->getValues();
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
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.manage_instruments');
      $form_state->setRedirectUrl($url);
      return;
    } 

    try{
      $config = $this->config(static::CONFIGNAME);     
      $api_url = $config->get("api_url");
      $repository_abbreviation = $config->get("repository_abbreviation");
  
      $uid = \Drupal::currentUser()->id();
      $uemail = \Drupal::currentUser()->getEmail();

      $data = [];
      
      $datap = $this->getInstrumentUri();

      $dataE = rawurlencode($datap);

      $newAttachments = $this->addAttachments($api_url,"/sirapi/api/attachment/create/".$dataE."/".$form_state->getValue('attachment_total_number'),$data);
    
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

  public function addAttachments($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $newAttachments = $fusekiAPIservice->responseOptionAdd($api_url,$endpoint,$data);
    if(!empty($newAttachments)){
      return $newAttachments;
    }
    return [];
  }

}