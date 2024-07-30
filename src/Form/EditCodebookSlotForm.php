<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;

class EditCodebookSlotForm extends FormBase {

  protected $codebookSlotUri;

  protected $codebookSlot;

  public function getCodebookSlotUri() {
    return $this->codebookSlotUri;
  }

  public function setCodebookSlotUri($uri) {
    return $this->codebookSlotUri = $uri; 
  }

  public function getCodebookSlot() {
    return $this->codebookSlot;
  }

  public function setCodebookSlot($obj) {
    return $this->codebookSlot = $obj; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_codebookslot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebooksloturi = NULL) {
    $uri=$codebooksloturi;
    $uri_decode=base64_decode($uri);
    $this->setCodebookSlotUri($uri_decode);

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getCodebookSlotUri());
    $obj = json_decode($rawresponse);

    $content = "";
    if ($obj->isSuccessful) {
      $this->setCodebookSlot($obj->body);
      if ($this->getCodebookSlot()->responseOption != NULL) {
        $content = $this->getCodebookSlot()->responseOption->hasContent . ' [' . $this->getCodebookSlot()->hasResponseOption . ']';
      }
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Response Option Slot."));
      $url = Url::fromRoute('sir.manage_codebooks');
      $form_state->setRedirectUrl($url);
    }

    $form['codebook_slot_uri'] = [
      '#type' => 'textfield',
      '#title' => t('ResponseOption Slot URI'),
      '#value' => $this->getCodebookSlotUri(),
      '#disabled' => TRUE,
    ];
    $form['codebook_slot_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Priority'),
      '#default_value' => $this->getCodebookSlot()->hasPriority,
      '#disabled' => TRUE,
    ];
    $form['codebook_slot_response_option'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Response Option"),
      '#default_value' => $content,
      '#autocomplete_route_name' => 'sir.codebookslot_response_option_autocomplete',
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
      if(strlen($form_state->getValue('codebook_slot_priority')) < 1) {
        $form_state->setErrorByName('codebook_slot_priority', $this->t('Please enter a valid priority value'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // RETRIEVE TRIGGERING ELEMENT
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // SET USER ID AND PREVIOUS URL FOR TRACKING STORE URLS
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    
    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.manage_codebook_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getCodebookSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
      return;
    } 

    if ($button_name === 'new_response_option') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_response_option');
      $url = Url::fromRoute('sir.add_response_option');
      $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri())); 
      $form_state->setRedirectUrl($url);
      return;
    } 

    if ($button_name === 'reset_response_option') {
      // RESET responseOption
      if ($this->getCodebookSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->codebookSlotReset($this->getCodebookSlotUri());
      } 

      $url = Url::fromRoute('sir.manage_codebook_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getCodebookSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
      return;
    } 

    try {
      // UPDATE responseOption
      if ($this->getCodebookSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->responseOptionAttach(Utils::uriFromAutocomplete($form_state->getValue('codebook_slot_response_option')),$this->getCodebookSlotUri());
      } 

      \Drupal::messenger()->addMessage(t("Response Option Slot has been updated successfully."));
      $url = Url::fromRoute('sir.manage_codebook_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getCodebookSlot()->belongsTo));
      $form_state->setRedirectUrl($url);

    } catch(\Exception $e) {
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Response Option Slot: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_codebook_slots');
      $url->setRouteParameter('codebookuri', base64_encode($this->getCodebookSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
    }

  }

}