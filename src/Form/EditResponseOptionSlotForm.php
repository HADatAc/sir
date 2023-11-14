<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Utils;

class EditResponseOptionSlotForm extends FormBase {

  protected $responseOptionSlotUri;

  protected $responseOptionSlot;

  public function getResponseOptionSlotUri() {
    return $this->responseOptionSlotUri;
  }

  public function setResponseOptionSlotUri($uri) {
    return $this->responseOptionSlotUri = $uri; 
  }

  public function getResponseOptionSlot() {
    return $this->responseOptionSlot;
  }

  public function setResponseOptionSlot($obj) {
    return $this->responseOptionSlot = $obj; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_responseoptionslot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $responseoptionsloturi = NULL) {
    $uri=$responseoptionsloturi;
    $uri_decode=base64_decode($uri);
    $this->setResponseOptionSlotUri($uri_decode);

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getResponseOptionSlotUri());
    $obj = json_decode($rawresponse);

    $content = "";
    if ($obj->isSuccessful) {
      $this->setResponseOptionSlot($obj->body);
      if ($this->getResponseOptionSlot()->responseOption != NULL) {
        $content = $this->getResponseOptionSlot()->responseOption->hasContent . ' [' . $this->getResponseOptionSlot()->hasResponseOption . ']';
      }
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Response Option Slot."));
      $url = Url::fromRoute('sir.manage_codebooks');
      $form_state->setRedirectUrl($url);
    }

    $form['responseoption_slot_uri'] = [
      '#type' => 'textfield',
      '#title' => t('ResponseOption Slot URI'),
      '#value' => $this->getResponseOptionSlotUri(),
      '#disabled' => TRUE,
    ];
    $form['responseoption_slot_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Priority'),
      '#default_value' => $this->getResponseOptionSlot()->hasPriority,
      '#disabled' => TRUE,
    ];
    $form['responseoption_slot_response_option'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Response Option"),
      '#default_value' => $content,
      '#autocomplete_route_name' => 'sir.responseoptionslot_response_option_autocomplete',
    ];
    $form['new_responseoption_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('New Response Option'),
      '#name' => 'new_response_option',
    ];
    $form['reset_responseoption_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Response Option Slot'),
      '#name' => 'reset_response_option',
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
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('responseoption_slot_priority')) < 1) {
        $form_state->setErrorByName('responseoption_slot_priority', $this->t('Please enter a valid priority value'));
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
      $url = Url::fromRoute('sir.manage_responseoption_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getResponseOptionSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
      return;
    } 

    if ($button_name === 'new_response_option') {
      $url = Url::fromRoute('sir.add_response_option');
      $url->setRouteParameter('responseoptionsloturi', base64_encode($this->getResponseOptionSlotUri())); 
      $form_state->setRedirectUrl($url);
      return;
    } 

    if ($button_name === 'reset_response_option') {
      // RESET responseOption
      if ($this->getResponseOptionSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->responseOptionSlotReset($this->getResponseOptionSlotUri());
      } 

      $url = Url::fromRoute('sir.manage_responseoption_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getResponseOptionSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
      return;
    } 

    try {
      // UPDATE responseOption
      if ($this->getResponseOptionSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->responseOptionAttach(Utils::uriFromAutocomplete($form_state->getValue('responseoption_slot_response_option')),$this->getResponseOptionSlotUri());
      } 

      \Drupal::messenger()->addMessage(t("Response Option Slot has been updated successfully."));
      $url = Url::fromRoute('sir.manage_responseoption_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getResponseOptionSlot()->belongsTo));
      $form_state->setRedirectUrl($url);

    } catch(\Exception $e) {
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Response Option Slot: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_responseoption_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getResponseOptionSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
    }

  }

}