<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class AddSubcontainerForm extends FormBase {

  protected $belongsTo;

  protected array $crumbs;

  public function getBelongsTo() {
    return $this->belongsTo;
  }

  public function setBelongsTo($uri) {
    return $this->belongsTo = $uri; 
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
    return 'add_subcontainer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $belongsto = NULL, $breadcrumbs = NULL) {

    // SETUP CONTEXT
    $uri=$belongsto ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setBelongsTo($uri_decode);
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
      '#title' => t('<h3>Subcontainer of Container ' . $path . '</h3>'),
    ];
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
      $this->backToSlotElement($form_state);
      return;
    } 

    try{
      $api = \Drupal::service('rep.api_connector');

      $useremail = \Drupal::currentUser()->getEmail();
      $newSubcontainerUri = Utils::uriGen('subcontainer');
      $subcontainerJson = '{"uri":"'.$newSubcontainerUri.'",'.
        '"superUri":"'.VSTOI::SUBCONTAINER.'",'.
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
      $this->backToSlotElement($form_state);
    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding the ContainerSlot: ".$e->getMessage()));
      $this->backToSlotElement($form_state);
    }

  }

  /**
   * {@inheritdoc}
   */
  private function backToSlotElement(FormStateInterface $form_state) {
    $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
    $url = Url::fromRoute('sir.manage_slotelements'); 
    $url->setRouteParameter('containeruri', base64_encode($this->getBelongsTo()));
    $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
    $form_state->setRedirectUrl($url);
    return;
  } 
  
}