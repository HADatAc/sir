<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;

class EditContainerSlotForm extends FormBase {

  protected $containerslotUri;

  protected $containerslot;

  protected array $crumbs;

  public function getContainerSlotUri() {
    return $this->containerslotUri;
  }

  public function setContainerSlotUri($uri) {
    return $this->containerslotUri = $uri; 
  }

  public function getContainerSlot() {
    return $this->containerslot;
  }

  public function setContainerSlot($obj) {
    return $this->containerslot = $obj; 
  }

  public function getBreadcrumbs() {
    return $this->crumbs;
  }

  public function setBreadcrumbs(array $crumbs) {
    return $this->crumbs = $crumbs; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_containerslot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $containersloturi = NULL, $breadcrumbs = NULL) {

    // SETUP CONTEXT
    $uri=$containersloturi;
    $uri_decode=base64_decode($uri);
    $this->setContainerSlotUri($uri_decode);
    if ($breadcrumbs == "_") {
      $crumbs = array();
    } else {
      $crumbs = explode('|',$breadcrumbs);
    }
    $this->setBreadcrumbs($crumbs);

    $api = \Drupal::service('rep.api_connector');
    $this->setContainerSlot($api->parseObjectResponse($api->getUri($this->getContainerSlotUri()),'getUri'));

    $content = "";
    if ($this->getContainerSlot() != NULL) { 
      if (isset($this->getContainerSlot()->detector) &&
          isset($this->getContainerSlot()->detector->detectorStem)) {
          $content = $this->getContainerSlot()->detector->detectorStem->hasContent . ' [' . $this->getContainerSlot()->hasDetector . ']';
        }
    } else {
      \Drupal::messenger()->addMessage(t("Failed to retrieve ContainerSlot."));
      $url = Url::fromRoute('sir.manage_slotelements');
      $url->setRouteParameter('containeruri', base64_encode($this->getContainerSlot()->belongsTo));
      $form_state->setRedirectUrl($url);
    }

    // BUILD FORM
    $path = "";
    $length = count($this->getBreadcrumbs());
    for ($i = 0; $i < $length; $i++) {
        $path .= '<font color="DarkGreen">' . $this->getBreadcrumbs()[$i] . '</font>';
        if ($i < $length - 1) {
            $path .= ' > ';
        }
    }

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>Editing Container Slot of Container ' . $path . '</h3>'),
    ];
    $form['containerslot_uri'] = [
      '#type' => 'textfield',
      '#title' => t('ContainerSlot URI'),
      '#value' => $this->getContainerSlotUri(),
      '#disabled' => TRUE,
    ];
    //$form['containerslot_instrument'] = [
    //  '#type' => 'textfield',
    //  '#title' => t('Instrument URI'),
    //  '#value' => $this->getContainerSlot()->belongsTo,
    //  '#disabled' => TRUE,
    //];
    $form['containerslot_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Priority'),
      '#default_value' => $this->getContainerSlot()->hasPriority,
      '#disabled' => TRUE,
    ];
    $form['containerslot_detector'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Item"),
      '#default_value' => $content,
      '#autocomplete_route_name' => 'sir.containerslot_detector_autocomplete',
      '#maxlength' => NULL,

    ];
    //$form['containerslot_detector_uri'] = [
    //  '#type' => 'textfield',
    //  '#title' => $this->t("Item Uri"),
    //  '#default_value' => $this->getContainerSlot()->hasDetector,
    //  '#disabled' => TRUE,
    //];
    $form['new_detector_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('New Item'),
      '#name' => 'new_detector',
    ];
    $form['reset_detector_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset Item'),
      '#name' => 'reset_detector',
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
      if(strlen($form_state->getValue('containerslot_priority')) < 1) {
        $form_state->setErrorByName('containerslot_priority', $this->t('Please enter a valid priority value'));
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

    $uid = \Drupal::currentUser()->id();
    $uemail = \Drupal::currentUser()->getEmail();

    if ($button_name === 'back') {
      $this->backToSlotElement($form_state);
      return;
    } 

    if ($button_name === 'new_detector') {
      $url = Url::fromRoute('sir.add_detector');
      $url->setRouteParameter('sourcedetectoruri', 'EMPTY'); 
      $url->setRouteParameter('containersloturi', base64_encode($this->getContainerSlotUri())); 
      $form_state->setRedirectUrl($url);
      return;
    } 

    if ($button_name === 'reset_detector') {
      // RESET DETECTOR
      if ($this->getContainerSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->containerslotReset($this->getContainerSlotUri());
      } 
      $this->backToSlotElement($form_state);
      return;    
    } 

    try{
      // UPDATE DETECTOR
      if ($this->getContainerSlotUri() != NULL) {
        $api = \Drupal::service('rep.api_connector');
        $api->detectorAttach(Utils::uriFromAutocomplete($form_state->getValue('containerslot_detector')),$this->getContainerSlotUri());
      } 

      \Drupal::messenger()->addMessage(t("ContainerSlot has been updated successfully."));
      $this->backToSlotElement($form_state);
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the containerslot: ".$e->getMessage()));
      $this->backToSlotElement($form_state);
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  private function backToSlotElement(FormStateInterface $form_state) {
    $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
    $url = Url::fromRoute('sir.manage_slotelements'); 
    $url->setRouteParameter('containeruri', base64_encode($this->getContainerSlot()->belongsTo));
    $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
    $form_state->setRedirectUrl($url);
    return;
  } 

}