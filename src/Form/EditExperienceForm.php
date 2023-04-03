<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

class EditExperienceForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  protected $experienceUri;

  protected $experience;

  public function getExperienceUri() {
    return $this->experienceUri;
  }

  public function setExperienceUri($uri) {
    return $this->experienceUri = $uri; 
  }

  public function getExperience() {
    return $this->experience;
  }

  public function setExperience($exp) {
    return $this->experience = $exp; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_experience_form';
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
  public function buildForm(array $form, FormStateInterface $form_state, $experienceuri = NULL) {
    $uri=$experienceuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setExperienceUri($uri_decode);

    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");
    $endpoint = "/sirapi/api/uri/".rawurlencode($this->getExperienceUri());

    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawresponse = $fusekiAPIservice->getUri($api_url,$endpoint);
    $obj = json_decode($rawresponse);
    
    if ($obj->isSuccessful) {
      $this->setExperience($obj->body);
      #dpm($this->getExperience());
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Experience."));
      $url = Url::fromRoute('sir.manage_experiences');
      $form_state->setRedirectUrl($url);
    }

    $form['experience_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getExperience()->label,
    ];
    $form['experience_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
      '#default_value' => $this->getExperience()->hasLanguage,
    ];
    $form['experience_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getExperience()->hasVersion,
    ];
    $form['experience_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getExperience()->comment,
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
      if(strlen($form_state->getValue('experience_name')) < 1) {
        $form_state->setErrorByName('experience_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('experience_language')) < 1) {
        $form_state->setErrorByName('experience_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('experience_version')) < 1) {
        $form_state->setErrorByName('experience_version', $this->t('Please enter a valid version'));
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
      $url = Url::fromRoute('sir.manage_experiences');
      $form_state->setRedirectUrl($url);
      return;
    } 

    try{
      $config = $this->config(static::CONFIGNAME);     
      $api_url = $config->get("api_url");
      $repository_abbreviation = $config->get("repository_abbreviation");
  
      $uid = \Drupal::currentUser()->id();
      $uemail = \Drupal::currentUser()->getEmail();

      $data = [
        'uri' => $this->getExperience()->uri,
        'typeUri' => 'http://hadatac.org/ont/vstoi#Experience',
        'hascoTypeUri' => 'http://hadatac.org/ont/vstoi#Experience',
        'label' => $form_state->getValue('experience_name'),
        'hasLanguage' => $form_state->getValue('experience_language'),
        'hasVersion' => $form_state->getValue('experience_version'),
        'comment' => $form_state->getValue('experience_description'),
        'hasSIRMaintainerEmail' => $uemail, 
      ];
      
      $datap = '{"uri":"'. $this->getExperience()->uri .'",'.
        '"typeUri":"http://hadatac.org/ont/vstoi#Experience",'.
        '"hascoTypeUri":"http://hadatac.org/ont/vstoi#Experience",'.
        '"label":"'.$form_state->getValue('experience_name').'",'.
        '"hasLanguage":"'.$form_state->getValue('experience_language').'",'.
        '"hasVersion":"'.$form_state->getValue('experience_version').'",'.
        '"comment":"'.$form_state->getValue('experience_description').'",'.
        '"hasSIRMaintainerEmail":"'.$uemail.'"}';

      $dataJ = json_encode($data);
    
      $dataE = rawurlencode($datap);

      // UPDATE BY DELETING AND CREATING
      $uriEncoded = rawurlencode($this->getExperienceUri());
      $this->deleteExperience($api_url,"/sirapi/api/experience/delete/".$uriEncoded,[]);    
      $updatedExperience = $this->addExperience($api_url,"/sirapi/api/experience/create/".$dataE,$data);
    
      \Drupal::messenger()->addMessage(t("Experience has been updated successfully."));
      $url = Url::fromRoute('sir.manage_experiences');
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating Experience: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_experiences');
      $form_state->setRedirectUrl($url);
    }

  }

  public function addExperience($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $newExperience = $fusekiAPIservice->experienceAdd($api_url,$endpoint,$data);
    if(!empty($newExperience)){
      return $newExperience;
    }
    return [];
  }

  public function deleteExperience($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $fusekiAPIservice->experienceDel($api_url,$endpoint,$data);
    return true;
  }
  
}