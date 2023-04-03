<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

class AddExperienceForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

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
    $form['experience_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['experience_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
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
      $url = Url::fromRoute('sir.manage_experiences');
      $form_state->setRedirectUrl($url);
      return;
    } 

    try {
      $config = $this->config(static::CONFIGNAME);     
      $api_url = $config->get("api_url");
      $repository_abbreviation = $config->get("repository_abbreviation");
  
      $uid = \Drupal::currentUser()->id();
      $uemail = \Drupal::currentUser()->getEmail();

      $iid = time().rand(10000,99999).$uid;
      
      $data = [
        'uri' => 'http://hadatac.org/kb/test/Experience'.$iid,
        'typeUri' => 'http://hadatac.org/ont/vstoi#Experience',
        'hascoTypeUri' => 'http://hadatac.org/ont/vstoi#Experience',
        'label' => $form_state->getValue('experience_name'),
        'hasLanguage' => $form_state->getValue('experience_language'),
        'hasVersion' => $form_state->getValue('experience_version'),
        'comment' => $form_state->getValue('experience_description'),
        'hasSIRMaintainerEmail' => $uemail,
      ];
      

      $datap = '{"uri":"http://hadatac.org/kb/test/Experience' . $iid . '",' . 
        '"typeUri":"http://hadatac.org/ont/vstoi#Experience",' . 
        '"hascoTypeUri":"http://hadatac.org/ont/vstoi#Experience",' . 
        '"label":"' . $form_state->getValue('experience_name') . '",' . 
        '"hasLanguage":"' . $form_state->getValue('experience_language') . '",' . 
        '"hasVersion":"' . $form_state->getValue('experience_version') . '",' . 
        '"comment":"' . $form_state->getValue('experience_description') . '",' . 
        '"hasSIRMaintainerEmail":"' . $uemail . '"}';

      $dataJ = json_encode($data);
      
      $dataE = rawurlencode($datap);

      $newExperience = $this->addExperience($api_url,"/sirapi/api/experience/create/".$dataE,$data);  
      
      \Drupal::messenger()->addMessage(t("Experience has been added successfully."));      
      $url = Url::fromRoute('sir.manage_experiences');
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding an experience: ".$e->getMessage()));
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


}