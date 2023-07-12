<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sir\Utils;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\VSTOI;

class EditExperienceForm extends FormBase {

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
  public function buildForm(array $form, FormStateInterface $form_state, $experienceuri = NULL) {
    $uri=$experienceuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setExperienceUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawresponse = $fusekiAPIservice->getUri($this->getExperienceUri());
    $obj = json_decode($rawresponse);
    
    if ($obj->isSuccessful) {
      $this->setExperience($obj->body);
      #dpm($this->getExperience());
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Experience."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('experience'));
    }

    $form['experience_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getExperience()->label,
    ];
    $form['experience_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
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
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('experience'));
      return;
    } 

    try{
      $useremail = \Drupal::currentUser()->getEmail();

      $experienceJson = '{"uri":"'. $this->getExperience()->uri .'",'.
        '"typeUri":"'.VSTOI::EXPERIENCE.'",'.
        '"hascoTypeUri":"'.VSTOI::EXPERIENCE.'",'.
        '"label":"'.$form_state->getValue('experience_name').'",'.
        '"hasLanguage":"'.$form_state->getValue('experience_language').'",'.
        '"hasVersion":"'.$form_state->getValue('experience_version').'",'.
        '"comment":"'.$form_state->getValue('experience_description').'",'.
        '"hasSIRMaintainerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->experienceDel($this->getExperience()->uri);
      $fusekiAPIservice->experienceAdd($experienceJson);
    
      \Drupal::messenger()->addMessage(t("Experience has been updated successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('experience'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating Experience: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('experience'));
    }

  }

}