<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Utils;

class EditCodebookForm extends FormBase {

  protected $codebookUri;

  protected $codebook;

  public function getCodebookUri() {
    return $this->codebookUri;
  }

  public function setCodebookUri($uri) {
    return $this->codebookUri = $uri;
  }

  public function getCodebook() {
    return $this->codebook;
  }

  public function setCodebook($cb) {
    return $this->codebook = $cb;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_codebook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebookuri = NULL) {
    $uri=$codebookuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setCodebookUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getCodebookUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setCodebook($obj->body);
      #dpm($this->getCodebook());
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Codebook."));
      self::backUrl();
      return;
    }

    $form['codebook_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getCodebook()->label,
    ];
    $form['codebook_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getCodebook()->hasLanguage,
    ];
    $form['codebook_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getCodebook()->hasVersion,
      '#disabled' => TRUE,
    ];
    $form['codebook_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getCodebook()->comment,
    ];
    $form['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
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
      if(strlen($form_state->getValue('codebook_name')) < 1) {
        $form_state->setErrorByName('codebook_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('codebook_language')) < 1) {
        $form_state->setErrorByName('codebook_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('codebook_version')) < 1) {
        $form_state->setErrorByName('codebook_version', $this->t('Please enter a valid version'));
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
      self::backUrl();
      return;
    }

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      $codebookJson = '{"uri":"'. $this->getCodebook()->uri .'",'.
        '"typeUri":"'.VSTOI::CODEBOOK.'",'.
        '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
        '"hasStatus":"'.$this->getCodebook()->hasStatus.'",'.
        '"label":"'.$form_state->getValue('codebook_name').'",'.
        '"hasLanguage":"'.$form_state->getValue('codebook_language').'",'.
        '"hasVersion":"'.$form_state->getValue('codebook_version').'",'.
        '"comment":"'.$form_state->getValue('codebook_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->codebookDel($this->getCodebook()->uri);
      $api->codebookAdd($codebookJson);

      \Drupal::messenger()->addMessage(t("Codebook has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating Codebook: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, \Drupal::request()->getRequestUri());
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }



}
