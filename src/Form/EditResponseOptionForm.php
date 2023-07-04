<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\VSTOI;

class EditResponseOptionForm extends FormBase {

  protected $responseOptionUri;

  protected $responseOption;

  public function getResponseOptionUri() {
    return $this->responseOptionUri;
  }

  public function setResponseOptionUri($uri) {
    return $this->responseOptionUri = $uri; 
  }

  public function getResponseOption() {
    return $this->responseOption;
  }

  public function setResponseOption($respOption) {
    return $this->responseOption = $respOption; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_responseoption_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $responseoptionuri = NULL) {
    $uri=$responseoptionuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setResponseOptionUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawresponse = $fusekiAPIservice->getUri($this->getResponseOptionUri());
    $obj = json_decode($rawresponse);
    
    if ($obj->isSuccessful) {
      $this->setResponseOption($obj->body);
      #dpm($this->getResponseOption());
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Response Option."));
      $url = Url::fromRoute('sir.manage_response_options');
      # $url->setRouteParameter('experienceuri', base64_encode($this->getResponseOption()->ofExperience));
      $form_state->setRedirectUrl($url);
    }

    $form['responseoption_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getResponseOption()->hasContent,
    ];
    $form['responseoption_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getResponseOption()->hasLanguage,
    ];
    $form['responseoption_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getResponseOption()->hasVersion,
    ];
    $form['responseoption_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getResponseOption()->comment,
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
      $form_state->setRedirectUrl($this->backUrl());
      return;
    } 

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      $responseOptionJSON = '{"uri":"'. $this->getResponseOption()->uri .'",'.
        '"typeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
        '"hascoTypeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
        '"hasContent":"'.$form_state->getValue('responseoption_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('responseoption_language').'",'.
        '"hasVersion":"'.$form_state->getValue('responseoption_version').'",'.
        '"comment":"'.$form_state->getValue('responseoption_description').'",'.
        '"hasSIRMaintainerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->responseoptionDel($this->getResponseOption()->uri);
      $fusekiAPIservice->responseoptionAdd($responseOptionJSON);
    
      \Drupal::messenger()->addMessage(t("Response Option has been updated successfully."));
      $form_state->setRedirectUrl($this->backUrl());

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Response Option: ".$e->getMessage()));
      $form_state->setRedirectUrl($this->backUrl());
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