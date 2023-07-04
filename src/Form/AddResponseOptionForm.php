<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\VSTOI;

class AddResponseOptionForm extends FormBase {

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

  public function setCodebookSlot($uri) {
    return $this->codebookSlot = $uri; 
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
  public function buildForm(array $form, FormStateInterface $form_state, $codebooksloturi = NULL) {

    // SAVE CODEBOOK SLOT URI
    if ($codebooksloturi == "EMPTY") {
      $this->setCodebookSlotUri("");
      $this->setCodebookSlot(NULL);
    } else {
      $uri_decode=base64_decode($codebooksloturi);
      $this->setCodebookSlotUri($uri_decode);

      // RETRIEVE CODEBOOK SLOT
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $rawresponse = $fusekiAPIservice->getUri($this->getCodebookSlotUri());
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setCodebookSlot($obj->body);
    }
    }

    // RETRIEVE TABLES
    $tables = new Tables;
    $languages = $tables->getLanguages();

    $form['responseoption_codebook_slot'] = [
      '#type' => 'textfield',
      '#title' => t('Codebook Slot URI'),
      '#value' => $this->getCodebookSlotUri(),
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
      if ($this->getCodebookSlotUri() == "") {
        $form_state->setRedirectUrl($this->backUrl());
        return;
      } else {
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }
    } 

    try {
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $iid = time().rand(10000,99999).$uid;
      
      $newResponseOptionUri = "http://hadatac.org/kb/test/ResponseOption".$iid;
      $responseOptionJSON = '{"uri":"'.$newResponseOptionUri.'",'.
        '"typeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
        '"hascoTypeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
        '"hasContent":"'.$form_state->getValue('responseoption_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('responseoption_language').'",'.
        '"hasVersion":"'.$form_state->getValue('responseoption_version').'",'.
        '"comment":"'.$form_state->getValue('responseoption_description').'",'.
        '"hasSIRMaintainerEmail":"'.$useremail.'"}';

      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->responseOptionAdd($responseOptionJSON);
      if ($this->getCodebookSlotUri() != NULL && $this->getCodebookSlot() != NULL && $this->getCodebookSlot()->belongsTo != NULL) {
        $fusekiAPIservice->responseOptionAttach($newResponseOptionUri,$this->getCodebookSlotUri());
      }
      
      \Drupal::messenger()->addMessage(t("Response Option has been added successfully."));
      if ($this->getCodebookSlotUri() == "") {
        $form_state->setRedirectUrl($this->backUrl());
        return;
      } else {
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the Response Option: ".$e->getMessage()));
      if ($this->getCodebookSlotUri() == "") {
        $form_state->setRedirectUrl($this->backUrl());
        return;
      } else {
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }
    }

  }

  private function backUrl() {  
    $url = Url::fromRoute('sir.select_element');
    $url->setRouteParameter('elementtype', 'responseoption');
    $url->setRouteParameter('page', '1');
    $url->setRouteParameter('pagesize', '12');
    return $url;
  }

}