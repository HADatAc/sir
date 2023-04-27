<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;

class EditResponseOptionForm extends FormBase {

    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

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

     protected function getEditableConfigNames() {
      return [
          static::CONFIGNAME,
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $responseoptionuri = NULL) {
    $uri=$responseoptionuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setResponseOptionUri($uri_decode);

    $config = $this->config(static::CONFIGNAME);           
    $api_url = $config->get("api_url");
    $endpoint = "/sirapi/api/uri/".rawurlencode($this->getResponseOptionUri());

    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $rawresponse = $fusekiAPIservice->getUri($api_url,$endpoint);
    $obj = json_decode($rawresponse);
    
    if ($obj->isSuccessful) {
      $this->setResponseOption($obj->body);
      #dpm($this->getResponseOption());
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Response Option."));
      $url = Url::fromRoute('sir.manage_response_options');
      $url->setRouteParameter('experienceuri', base64_encode($this->getResponseOption()->ofExperience));
      $form_state->setRedirectUrl($url);
    }

    $form['responseoption_experience'] = [
      '#type' => 'textfield',
      '#title' => t('Experience'),
      '#value' => $this->getResponseOption()->ofExperience,
      '#disabled' => TRUE,
    ];
    $form['responseoption_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Priority'),
      '#default_value' => $this->getResponseOption()->hasPriority,
    ];
    $form['responseoption_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => $this->getResponseOption()->hasContent,
    ];
    $form['responseoption_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Language'),
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
      $url->setRouteParameter('experienceuri', base64_encode($this->getResponseOption()->ofExperience));
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
        'uri' => $this->getResponseOption()->uri,
        'typeUri' => 'http://hadatac.org/ont/vstoi#ResponseOption',
        'hascoTypeUri' => 'http://hadatac.org/ont/vstoi#ResponseOption',
        'ofExperience' => $this->getResponseOption()->ofExperience,
        'hasPriority' => $form_state->getValue('responseoption_priority'),
        'hasContent' => $form_state->getValue('responseoption_content'),
        'hasLanguage' => $form_state->getValue('responseoption_language'),
        'hasVersion' => $form_state->getValue('responseoption_version'),
        'comment' => $form_state->getValue('responseoption_description'),
        'hasSIRMaintainerEmail' => $uemail, 
      ];
      
      $datap = '{"uri":"'. $this->getResponseOption()->uri .'",'.
        '"typeUri":"http://hadatac.org/ont/vstoi#ResponseOption",'.
        '"hascoTypeUri":"http://hadatac.org/ont/vstoi#ResponseOption",'.
        '"ofExperience":"' . $this->getResponseOption()->ofExperience . '",'.
        '"hasPriority":"'.$form_state->getValue('responseoption_priority').'",'.
        '"hasContent":"'.$form_state->getValue('responseoption_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('responseoption_language').'",'.
        '"hasVersion":"'.$form_state->getValue('responseoption_version').'",'.
        '"comment":"'.$form_state->getValue('responseoption_description').'",'.
        '"hasSIRMaintainerEmail":"'.$uemail.'"}';

      $dataJ = json_encode($data);
    
      $dataE = rawurlencode($datap);

      // UPDATE BY DELETING AND CREATING
      $uriEncoded = rawurlencode($this->getResponseOptionUri());
      $this->deleteResponseOption($api_url,"/sirapi/api/responseoption/delete/".$uriEncoded,[]);    
      $updatedResponseOption = $this->addResponseOption($api_url,"/sirapi/api/responseoption/create/".$dataE,$data);
    
      \Drupal::messenger()->addMessage(t("Response Option has been updated successfully."));
      $url = Url::fromRoute('sir.manage_response_options');
      $url->setRouteParameter('experienceuri', base64_encode($this->getResponseOption()->ofExperience));
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Response Option: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_response_options');
      $url->setRouteParameter('experienceuri', base64_encode($this->getResponseOption()->ofExperience));
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

  public function deleteResponseOption($api_url,$endpoint,$data){
    $fusekiAPIservice = \Drupal::service('sir.api_connector');
    $fusekiAPIservice->responseoptionDel($api_url,$endpoint,$data);
    return true;
  }
  


}