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

     protected function getEditableConfigNames() {
      return [
          static::CONFIGNAME,
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['experience_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
    ];
    $form['experience_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#required' => TRUE,
    ];
    $form['experience_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Experience description'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if(strlen($form_state->getValue('experience_name')) < 1) {
      $form_state->setErrorByName('experience_name', $this->t('Please enter a valid name for the Experience'));
    }
    if(strlen($form_state->getValue('experience_description')) < 1) {
      $form_state->setErrorByName('experience_description', $this->t('Please enter a valid description of the Experience'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    try{
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
        'comment' => $form_state->getValue('experience_description')
      ];
      

      $datap = '{"uri":"http://hadatac.org/kb/test/Experience' . $iid . '",' . 
        '"typeUri":"http://hadatac.org/ont/vstoi#Experience",' . 
        '"hascoTypeUri":"http://hadatac.org/ont/vstoi#Experience",' . 
        '"label":"' . $form_state->getValue('experience_name') . '",' . 
        '"comment":"' . $form_state->getValue('experience_description') . '",' . 
        '"hasSIRMaintainerEmail":"' . $uemail . '"}';

    $dataJ = json_encode($data);
    
    $dataE = rawurlencode($datap);

    $newExperience = $this->addExperience($api_url,"/sirapi/api/experience/create/".$dataE,$data);  
    
    $root_url = \Drupal::request()->getBaseUrl();
    $url = $root_url.'/sir/manage/editexperiences';
    $url_object = Url::fromUri($url);
    $form_state->setRedirectUrl($url_object);

    \Drupal::messenger()->addMessage(t("Experience has been added successfully."));      

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding an experience: ".$e->getMessage()));
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