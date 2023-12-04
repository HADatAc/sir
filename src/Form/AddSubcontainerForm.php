<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class AddSubcontainerForm extends FormBase {

  protected $iUri;

  public function getParentUri() {
    return $this->parentUri;
  }

  public function setParentUri($uri) {
    return $this->parentUri = $uri; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_subcontainer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $parenturi = NULL) {
    $uri=$parenturi ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setParentUri($uri_decode);

    $form['subcontainer_parent'] = [
      '#type' => 'textfield',
      '#title' => t('Parent URI'),
      '#value' => $this->getParentUri(),
      '#disabled' => TRUE,
    ];
    $form['subcontainer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
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
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('subcontainer_name')) < 1) {
        $form_state->setErrorByName('subcontainer_name', $this->t('Please provide a name for the new subcontainer.'));
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
      $url = Url::fromRoute('sir.manage_detectorslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getParentUri()));
      $form_state->setRedirectUrl($url);
    } 

    try{
      $api = \Drupal::service('rep.api_connector');

      $useremail = \Drupal::currentUser()->getEmail();
      $newSubcontainerUri = Utils::uriGen('subcontainer');
      $subcontainerJson = '{"uri":"'.$newSubcontainerUri.'",'.
        '"typeUri":"'.VSTOI::SUBCONTAINER.'",'.
        '"hascoTypeUri":"'.VSTOI::SUBCONTAINER.'",'.
        '"hasParent":"'.$this->getParentUri().'",'.
        '"label":"'.$form_state->getValue('subcontainer_name').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      $api = \Drupal::service('rep.api_connector');
      $message = $api->parseObjectResponse($api->subcontainerAdd($subcontainerJson),'subcontainerAdd');
      if ($message != null) {
        \Drupal::messenger()->addMessage(t("Subcontainer has been added successfully."));
      }
      $url = Url::fromRoute('sir.manage_detectorslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getParentUri()));
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the DetectorSlot: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_detectorslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getParentUri()));
      $form_state->setRedirectUrl($url);
    }

  }

}