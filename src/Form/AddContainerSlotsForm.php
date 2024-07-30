<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AddContainerSlotsForm extends FormBase {

  protected $containerUri;

  protected array $crumbs;

  public function getContainerUri() {
    return $this->containerUri;
  }

  public function setContainerUri($uri) {
    return $this->containerUri = $uri; 
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
    return 'add_containerslots_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $containeruri = NULL, $breadcrumbs = NULL) {

    // SETUP CONTEXT
    $uri=$containeruri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setContainerUri($uri_decode);
    if ($breadcrumbs == "_") {
      $crumbs = array();
    } else {
      $crumbs = explode('|',$breadcrumbs);
    }
    $this->setBreadcrumbs($crumbs);

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
      '#title' => t('<h3>Adding Container Slots of Container ' . $path . '</h3>'),
    ];
    $form['containerslot_container'] = [
      '#type' => 'textfield',
      '#title' => t('Container URI'),
      '#value' => $this->getContainerUri(),
      '#disabled' => TRUE,
    ];
    $form['containerslot_total_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Specify number of new items to add for this questionnaire'),
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

  public function validateForm(array &$form, FormStateInterface $form_state, $breadcrumbs = NULL) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('containerslot_total_number')) < 1) {
        $form_state->setErrorByName('containerslot_total_number', $this->t('Please specify a number of items greater than zero.'));
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
      $api = \Drupal::service('rep.api_connector');
      $message = $api->parseObjectResponse($api->containerslotAdd($this->getContainerUri(),$form_state->getValue('containerslot_total_number')),'containerslotAdd');
      if ($message != null) {
        \Drupal::messenger()->addMessage(t("ContainerSlots has been added successfully."));
      }
      $this->backToSlotElement($form_state);
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the ContainerSlot: ".$e->getMessage()));
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
    $url->setRouteParameter('containeruri', base64_encode($this->getContainerUri()));
    $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
    $form_state->setRedirectUrl($url);
    return;
  } 

}