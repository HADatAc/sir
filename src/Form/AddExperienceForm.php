<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sir\Utils;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\VSTOI;

class AddExperienceForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_experience_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $tables = new Tables;
    $languages = $tables->getLanguages();
    $form['experience_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['experience_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['experience_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
    ];
    $form['experience_description'] = [
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
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'save') {
      if(strlen($form_state->getValue('experience_name')) < 1) {
        $form_state->setErrorByName('experience_name', $this->t('Please enter a valid name for the Experience'));
      }
      if(strlen($form_state->getValue('experience_description')) < 1) {
        $form_state->setErrorByName('experience_description', $this->t('Please enter a valid description of the Experience'));
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
      $form_state->setRedirectUrl(Utils::selectBackUrl('experience'));
      return;
    } 

    try {
      $uemail = \Drupal::currentUser()->getEmail();
      $newExperienceUri = Utils::uriGen('experience');
      $experienceJSON = '{"uri":"'.$newExperienceUri.'",' . 
        '"typeUri":"'.VSTOI::EXPERIENCE.'",'.
        '"hascoTypeUri":"'.VSTOI::EXPERIENCE.'",'.
        '"label":"' . $form_state->getValue('experience_name') . '",' . 
        '"hasLanguage":"' . $form_state->getValue('experience_language') . '",' . 
        '"hasVersion":"' . $form_state->getValue('experience_version') . '",' . 
        '"comment":"' . $form_state->getValue('experience_description') . '",' . 
        '"hasSIRMaintainerEmail":"' . $uemail . '"}';

      $fusekiAPIservice = \Drupal::service('sir.api_connector');
      $fusekiAPIservice->experienceAdd($experienceJSON);
      \Drupal::messenger()->addMessage(t("Experience has been added successfully."));      
      $form_state->setRedirectUrl(Utils::selectBackUrl('experience'));

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding an experience: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('experience'));
    }

  }

}