<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\sir\Entity\Tables;

class AddResponseOptionForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

  protected $experienceUri;

  public function getExperienceUri() {
    return $this->experienceUri;
  }

  public function setExperienceUri($uri) {
    return $this->experienceUri = $uri; 
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
    $tables = new Tables;
    $languages = $tables->getLanguages();

    $form['responseoption_experience'] = [
      '#type' => 'textfield',
      '#title' => t('Experience'),
      '#value' => $this->getExperienceUri(),
      '#disabled' => TRUE,
    ];
    $form['responseoption_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Priority'),
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
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('responseoption_priority')) < 1) {
        $form_state->setErrorByName('responseoption_priority', $this->t('Please enter a valid priority value'));
      }
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
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.manage_response_options');
      $url->setRouteParameter('experienceuri', base64_encode($this->getExperienceUri()));
      $form_state->setRedirectUrl($url);
      return;
    } 

    try{
      $config = $this->config(static::CONFIGNAME);     
      $api_url = $config->get("api_url");
      $repository_abbreviation = $config->get("repository_abbreviation");
  
      $uid = \Drupal::currentUser()->id();
      $uemail = \Drupal::currentUser()->getEmail();

      $iid = time().rand(10000,99999).$uid;
      
      $data = [
        'uri' => 'http://hadatac.org/kb/test/ResponseOption'.$iid,
        'typeUri' => 'http://hadatac.org/ont/vstoi#ResponseOption',
        'hascoTypeUri' => 'http://hadatac.org/ont/vstoi#ResponseOption',
        'ofExperience' => $this->getExperienceUri(),
        'hasPriority' => $form_state->getValue('responseoption_priority'),
        'hasContent' => $form_state->getValue('responseoption_content'),
        'hasLanguage' => $form_state->getValue('responseoption_language'),
        'hasVersion' => $form_state->getValue('responseoption_version'),
        'comment' => $form_state->getValue('responseoption_description'),
        'hasSIRMaintainerEmail' => $uemail, 
      ];
      
      $datap = '{"uri":"http://hadatac.org/kb/test/ResponseOption'.$iid.'",'.
        '"typeUri":"http://hadatac.org/ont/vstoi#ResponseOption",'.
        '"hascoTypeUri":"http://hadatac.org/ont/vstoi#ResponseOption",'.
        '"ofExperience":"' . $this->getExperienceUri().'",'.
        '"hasPriority":"'.$form_state->getValue('responseoption_priority').'",'.
        '"hasContent":"'.$form_state->getValue('responseoption_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('responseoption_language').'",'.
        '"hasVersion":"'.$form_state->getValue('responseoption_version').'",'.
        '"comment":"'.$form_state->getValue('responseoption_description').'",'.
        '"hasSIRMaintainerEmail":"'.$uemail.'"}';

      $dataJ = json_encode($data);
    
      $dataE = rawurlencode($datap);

      $newResponseOption = $this->addResponseOption($api_url,"/sirapi/api/responseoption/create/".$dataE,$data);
    
      \Drupal::messenger()->addMessage(t("Response Option has been added successfully."));
      $url = Url::fromRoute('sir.manage_response_options');
      $url->setRouteParameter('experienceuri', base64_encode($this->getExperienceUri()));
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the Response Option: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_response_options');
      $url->setRouteParameter('experienceuri', base64_encode($this->getExperienceUri()));
      $form_state->setRedirectUrl($url);
    }

  }

  public function addResponseOption($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $newResponseOption = $fusekiAPIservice->responseOptionAdd($api_url,$endpoint,$data);
    if(!empty($newResponseOptiont)){
      return $newResponseOption;
    }
    return [];
  }


}