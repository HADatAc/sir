<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class AddSubcontainerForm extends FormBase {

  protected $belongsTo;

  public function getBelongsTo() {
    return $this->belongsTo;
  }

  public function setBelongsTo($uri) {
    return $this->belongsTo = $uri; 
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
  public function buildForm(array $form, FormStateInterface $form_state, $belongsto = NULL) {
    $uri=$belongsto ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setBelongsTo($uri_decode);

    $form['subcontainer_belongsto'] = [
      '#type' => 'textfield',
      '#title' => t('Parent URI'),
      '#value' => $this->getBelongsTo(),
      '#disabled' => TRUE,
    ];
    $form['subcontainer_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Priority (e.g., 'Section 1.1')"),
    ];
    $form['subcontainer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Name (e.g., 'Demographics', 'Lab Results')"),
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
      $url = Url::fromRoute('sir.manage_slotelements');
      $url->setRouteParameter('containeruri', base64_encode($this->getBelongsTo()));
      $form_state->setRedirectUrl($url);
      return;
    } 

    try{
      $api = \Drupal::service('rep.api_connector');

      $useremail = \Drupal::currentUser()->getEmail();
      $newSubcontainerUri = Utils::uriGen('subcontainer');
      $subcontainerJson = '{"uri":"'.$newSubcontainerUri.'",'.
        '"typeUri":"'.VSTOI::SUBCONTAINER.'",'.
        '"hascoTypeUri":"'.VSTOI::SUBCONTAINER.'",'.
        '"belongsTo":"'.$this->getBelongsTo().'",'.
        '"label":"'.$form_state->getValue('subcontainer_name').'",'.
        '"hasPriority":"'.$form_state->getValue('subcontainer_priority').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      $api = \Drupal::service('rep.api_connector');
      $message = $api->parseObjectResponse($api->subcontainerAdd($subcontainerJson),'subcontainerAdd');
      if ($message != null) {
        \Drupal::messenger()->addMessage(t("Subcontainer has been added successfully."));
      }
      $url = Url::fromRoute('sir.manage_slotelements');
      $url->setRouteParameter('containeruri', base64_encode($this->getBelongsTo()));
      $form_state->setRedirectUrl($url);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the ContainerSlot: ".$e->getMessage()));
      $url = Url::fromRoute('sir.manage_slotelements');
      $url->setRouteParameter('containeruri', base64_encode($this->getBelongsTo()));
      $form_state->setRedirectUrl($url);
    }

  }

}