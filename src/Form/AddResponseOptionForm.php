<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class AddResponseOptionForm extends FormBase {

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

  public function setResponseOptionSlot($uri) {
    return $this->responseOptionSlot = $uri; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_responseoption_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $responseoptionsloturi = NULL) {

    // SAVE RESPONSEOPTION SLOT URI
    if ($responseoptionsloturi == "EMPTY") {
      $this->setResponseOptionSlotUri("");
      $this->setResponseOptionSlot(NULL);
    } else {
      $uri_decode=base64_decode($responseoptionsloturi);
      $this->setResponseOptionSlotUri($uri_decode);

      // RETRIEVE RESPONSEOPTION SLOT
      $api = \Drupal::service('rep.api_connector');
      $rawresponse = $api->getUri($this->getResponseOptionSlotUri());
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setResponseOptionSlot($obj->body);
    }
    }

    // RETRIEVE TABLES
    $tables = new Tables;
    $languages = $tables->getLanguages();

    $form['responseoption_responseoption_slot'] = [
      '#type' => 'textfield',
      '#title' => t('Response Option Slot URI'),
      '#value' => $this->getResponseOptionSlotUri(),
      '#disabled' => TRUE,
    ];
    $form['responseoption_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
    ];
    $form['responseoption_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['responseoption_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
    ];
    $form['responseoption_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
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
      if(strlen($form_state->getValue('responseoption_content')) < 1) {
        $form_state->setErrorByName('responseoption_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('responseoption_language')) < 1) {
        $form_state->setErrorByName('responseoption_language', $this->t('Please enter a valid language'));
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
      if ($this->getResponseOptionSlotUri() == "") {
        $form_state->setRedirectUrl(Utils::selectBackUrl('responseoption'));
        return;
      } else {
        $url = Url::fromRoute('sir.edit_responseoption_slot');
        $url->setRouteParameter('responseoptionsloturi', base64_encode($this->getResponseOptionSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }
    } 

    try {
      $useremail = \Drupal::currentUser()->getEmail();
      $newResponseOptionUri = Utils::uriGen('responseoption');
      $responseOptionJSON = '{"uri":"'.$newResponseOptionUri.'",'.
        '"typeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
        '"hascoTypeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
        '"hasContent":"'.$form_state->getValue('responseoption_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('responseoption_language').'",'.
        '"hasVersion":"'.$form_state->getValue('responseoption_version').'",'.
        '"comment":"'.$form_state->getValue('responseoption_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      $api = \Drupal::service('rep.api_connector');
      $api->responseOptionAdd($responseOptionJSON);
      if ($this->getResponseOptionSlotUri() != NULL && $this->getResponseOptionSlot() != NULL && $this->getResponseOptionSlot()->belongsTo != NULL) {
        $api->responseOptionAttach($newResponseOptionUri,$this->getResponseOptionSlotUri());
      }
      
      \Drupal::messenger()->addMessage(t("Response Option has been added successfully."));
      if ($this->getResponseOptionSlotUri() == "") {
        $form_state->setRedirectUrl(Utils::selectBackUrl('responseoption'));
        return;
      } else {
        $url = Url::fromRoute('sir.edit_responseoption_slot');
        $url->setRouteParameter('responseoptionsloturi', base64_encode($this->getResponseOptionSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the Response Option: ".$e->getMessage()));
      if ($this->getResponseOptionSlotUri() == "") {
        $form_state->setRedirectUrl(Utils::selectBackUrl('responseoption'));
        return;
      } else {
        $url = Url::fromRoute('sir.edit_responseoption_slot');
        $url->setRouteParameter('responseoptionsloturi', base64_encode($this->getResponseOptionSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }
    }

  }

}