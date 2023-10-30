<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sir\Utils;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\VSTOI;

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

    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawresponse = $fusekiAPIservice->getUri($this->getCodebookUri());
    $obj = json_decode($rawresponse);
    
    if ($obj->isSuccessful) {
      $this->setCodebook($obj->body);
      #dpm($this->getCodebook());
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Codebook."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('codebook'));
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
      $form_state->setRedirectUrl(Utils::selectBackUrl('codebook'));
      return;
    } 

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      $codebookJson = '{"uri":"'. $this->getCodebook()->uri .'",'.
        '"typeUri":"'.VSTOI::CODEBOOK.'",'.
        '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
        '"label":"'.$form_state->getValue('codebook_name').'",'.
        '"hasLanguage":"'.$form_state->getValue('codebook_language').'",'.
        '"hasVersion":"'.$form_state->getValue('codebook_version').'",'.
        '"comment":"'.$form_state->getValue('codebook_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->codebookDel($this->getCodebook()->uri);
      $fusekiAPIservice->codebookAdd($codebookJson);
    
      \Drupal::messenger()->addMessage(t("Codebook has been updated successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('codebook'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating Codebook: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('codebook'));
    }

  }

}