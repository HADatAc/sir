<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

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

    // SAVE RESPONSEOPTION SLOT URI
    if ($codebooksloturi == "EMPTY") {
      $this->setCodebookSlotUri("");
      $this->setCodebookSlot(NULL);
    } else {
      $uri_decode=base64_decode($codebooksloturi);
      $this->setCodebookSlotUri($uri_decode);

      // RETRIEVE RESPONSEOPTION SLOT
      $api = \Drupal::service('rep.api_connector');
      $rawresponse = $api->getUri($this->getCodebookSlotUri());
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setCodebookSlot($obj->body);
      }
    }

    // RETRIEVE TABLES
    $tables = new Tables;
    $languages = $tables->getLanguages();

    if ($this->getCodebookSlotUri() != NULL && $this->getCodebookSlotUri() != "") {
      $form['responseoption_codebook_slot'] = [
        '#type' => 'textfield',
        '#title' => t('Being created in the context of the following Response Option Slot URI'),
        '#value' => $this->getCodebookSlotUri(),
        '#disabled' => TRUE,
      ];
    }
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
    // $form['responseoption_version'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Version'),
    // ];
    $form['responseoption_version_display'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['responseoption_description'] = [
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
        self::backUrl();
        return;
      } else {
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
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
      if ($this->getCodebookSlotUri() != NULL && $this->getCodebookSlot() != NULL && $this->getCodebookSlot()->belongsTo != NULL) {
        $api->responseOptionAttach($newResponseOptionUri,$this->getCodebookSlotUri());
      }

      \Drupal::messenger()->addMessage(t("Response Option has been added successfully."));
      if ($this->getCodebookSlotUri() == "") {
        self::backUrl();
        return;
      } else {
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while adding the Response Option: ".$e->getMessage()));
      if ($this->getCodebookSlotUri() == "") {
        self::backUrl();
        return;
      } else {
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_response_option');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }



}
