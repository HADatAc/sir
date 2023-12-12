<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class EditSubcontainerForm extends FormBase {

  protected $subcontainer;

  protected array $crumbs;

  public function getSubcontainer() {
    return $this->subcontainer;
  }

  public function setSubcontainer($sub) {
    return $this->subcontainer = $sub; 
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
    return 'edit_subcontainer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $subcontaineruri = NULL, $breadcrumbs = NULL) {

    // SETUP CONTEXT
    $uri=$subcontaineruri ?? 'default';
    $subUri=base64_decode($uri);
    if ($breadcrumbs == "_") {
      $crumbs = array();
    } else {
      $crumbs = explode('|',$breadcrumbs);
    }
    $this->setBreadcrumbs($crumbs);

    // LOAD SUBCONTAINER
    $api = \Drupal::service('rep.api_connector');
    $this->setSubcontainer($api->parseObjectresponse($api->getUri($subUri),'getUri'));   
    if ($this->getSubcontainer() == NULL) {
      \Drupal::messenger()->addMessage(t("Failed to retrieve Subcontainer."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
      return;
    }

    //dpm($this->getSubcontainer());

    $belongTo = "";
    if ($this->getSubcontainer()->belongsTo != NULL) {
      $belongsTo = $this->getSubcontainer()->belongsTo;
    }
    $priority = "";
    if ($this->getSubcontainer()->hasPriority != NULL) {
      $priority = $this->getSubcontainer()->hasPriority;
    }

    $name = "";
    if ($this->getSubcontainer()->label != NULL) {
      $name = $this->getSubcontainer()->label;
    }

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
      '#title' => t('<h3>Subcontainer of Container ' . $path . '</h3>'),
    ];
    $form['subcontainer_belongsto'] = [
      '#type' => 'textfield',
      '#title' => t('Parent URI'),
      '#default_value' => $belongsTo,
      '#disabled' => TRUE,
    ];
    $form['subcontainer_priority'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Priority (e.g., 'Section 1.1')"),
      '#default_value' => $priority,
    ];
    $form['subcontainer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Name (e.g., 'Demographics', 'Lab Results')"),
      '#default_value' => $name,
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
      if(strlen($form_state->getValue('subcontainer_name')) < 1) {
        $form_state->setErrorByName('subcontainer_name', $this->t('Please enter a valid name'));
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
      $this->backToSlotElement($form_state);
      return;
    } 

    try{
      $useremail = \Drupal::currentUser()->getEmail();
      $subcontainerJson = '{"uri":"'.$this->getSubcontainer()->uri.'",'.
        '"typeUri":"'.VSTOI::SUBCONTAINER.'",'.
        '"hascoTypeUri":"'.VSTOI::SUBCONTAINER.'",'.
        '"belongsTo":"'.$this->getSubcontainer()->belongsTo.'",'.
        '"label":"'.$form_state->getValue('subcontainer_name').'",'.
        '"hasPriority":"'.$form_state->getValue('subcontainer_priority').'",';
      if (isset($this->getSubcontainer()->hasPrevious)) {
        $subcontainerJson .= '"hasPrevious":"'.$this->getSubcontainer()->hasPrevious.'",';
      } 
      if (isset($this->getSubcontainer()->hasNext)) {
        $subcontainerJson .= '"hasNext":"'.$this->getSubcontainer()->hasNext.'",';
      }
      $subcontainerJson .= '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $msg = $api->parseObjectResponse($api->subcontainerUpdate($subcontainerJson),'subcontainerUpdate');
    
      if ($msg == NULL) {
        \Drupal::messenger()->addMessage(t("Subcontainer has been updated successfully."));
      }
      $this->backToSlotElement($form_state);

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while updating the Response Option: ".$e->getMessage()));
      $this->backToSlotElement($form_state);
    }

  }

  /**
   * {@inheritdoc}
   */
  private function backToSlotElement(FormStateInterface $form_state) {
    $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
    $url = Url::fromRoute('sir.manage_slotelements'); 
    $url->setRouteParameter('containeruri', base64_encode($this->getSubcontainer()->belongsTo));
    $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
    $form_state->setRedirectUrl($url);
    return;
  } 
  


}