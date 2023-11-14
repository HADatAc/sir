<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Utils;

class EditDetectorSlotForm extends FormBase {

  protected $detectorslotUri;

  protected $detectorslot;

  public function getDetectorSlotUri() {
    return $this->detectorslotUri;
  }

  public function setDetectorSlotUri($uri) {
    return $this->detectorslotUri = $uri; 
  }

  public function getDetectorSlot() {
    return $this->detectorslot;
  }

  public function setDetectorSlot($obj) {
    return $this->detectorslot = $obj; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_detectorslot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $detectorsloturi = NULL) {
    $uri=$detectorsloturi;
    $uri_decode=base64_decode($uri);
    $this->setDetectorSlotUri($uri_decode);

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getDetectorSlotUri());
    $obj = json_decode($rawresponse);

    $content = "";
    if ($obj->isSuccessful) {
      $this->setDetectorSlot($obj->body);
      if ($this->getDetectorSlot()->detector != NULL) {
        $content = $this->getDetectorSlot()->detector->hasContent . ' [' . $this->getDetectorSlot()->hasDetector . ']';
      }
        //dpm($this->getDetectorSlot());
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve DetectorSlot."));
      $url = Url::fromRoute('sir.manage_instruments');
      $form_state->setRedirectUrl($url);
    }

    $form['detectorslot_uri'] = [
      '#type' => 'textfield',
      '#title' => t('DetectorSlot URI'),
      '#value' => $this->getDetectorSlotUri(),
      '#disabled' => TRUE,
    ];
    //$form['detectorslot_instrument'] = [
    //  '#type' => 'textfield',
    //  '#title' => t('Instrument URI'),
    //  '#value' => $this->getDetectorSlot()->belongsTo,
    //  '#disabled' => TRUE,
    //];
    $form['detectorslot_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Priority'),
      '#default_value' => $this->getDetectorSlot()->hasPriority,
      '#disabled' => TRUE,
    ];
    $form['detectorslot_detector'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Item"),
      '#default_value' => $content,
      '#autocomplete_route_name' => 'sir.detectorslot_detector_autocomplete',
      '#maxlength' => NULL,

    ];
    //$form['detectorslot_detector_uri'] = [
    //  '#type' => 'textfield',
    //  '#title' => $this->t("Item Uri"),
    //  '#default_value' => $this->getDetectorSlot()->hasDetector,
    //  '#disabled' => TRUE,
    //];
    $form['new_detector_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('New Item'),
      '#name' => 'new_detector',
    ];
    $form['reset_detector_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Item'),
      '#name' => 'reset_detector',
    ];
    $form['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
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
      if(strlen($form_state->getValue('detectorslot_priority')) < 1) {
        $form_state->setErrorByName('detectorslot_priority', $this->t('Please enter a valid priority value'));
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

    $uid = \Drupal::currentUser()->id();
    $uemail = \Drupal::currentUser()->getEmail();

    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.manage_detectorslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getDetectorSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
      return;
    } 

    if ($button_name === 'new_detector') {
      $url = Url::fromRoute('sir.add_detector');
      $url->setRouteParameter('sourcedetectoruri', 'EMPTY'); 
      $url->setRouteParameter('detectorsloturi', base64_encode($this->getDetectorSlotUri())); 
      $form_state->setRedirectUrl($url);
      return;
    } 

    if ($button_name === 'reset_detector') {
      // RESET DETECTOR
      if ($this->getDetectorSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->detectorslotReset($this->getDetectorSlotUri());
      } 

      $url = Url::fromRoute('sir.manage_detectorslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getDetectorSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
      return;
    } 

    try{
      // UPDATE DETECTOR
      if ($this->getDetectorSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->detectorAttach(Utils::uriFromAutocomplete($form_state->getValue('detectorslot_detector')),$this->getDetectorSlotUri());
      } 

      \Drupal::messenger()->addMessage(t("DetectorSlot has been updated successfully."));
      $url = Url::fromRoute('sir.manage_detectorslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getDetectorSlot()->belongsTo));
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the detectorslot: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_detectorslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getDetectorSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
    }

  }

}