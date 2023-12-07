<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

class AddAnnotationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_annotation_form';
  }

  protected $annotation;

  protected $container;

  protected $annotationStem;

  public function getContainer() {
    return $this->container;
  }

  public function setContainer($obj) {
    return $this->container = $obj; 
  }

  public function getAnnotationStem() {
    return $this->annotationStem;
  }

  public function setAnnotationStem($stem) {
    return $this->annotationStem = $stem; 
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    $tables = new Tables;
    $positions = $tables->getInstrumentPositions();

    $form['annotation_container'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instrument'),
      '#autocomplete_route_name' => 'sir.annotation_container_autocomplete',
    ];
    $form['annotation_position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => $positions,
      '#default_value' => 'en',
    ];
    $form['annotation_stem'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Annotation Stem'),
      '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
    ];
    $form['annotation_style'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content w/Style (HTML)'),
    ];
    $form['annotation_description'] = [
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

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    if ($button_name != 'back') {

      if ($form_state->getValue('annotation_stem') == NULL || $form_state->getValue('annotation_stem') == '') {
        $form_state->setErrorByName('annotation_stem', $this->t('Annotation stem value is empty. Please enter a valid stem.'));
      }
      $stemUri = Utils::uriFromAutocomplete($form_state->getValue('annotation_stem'));
      $this->setAnnotationStem($api->parseObjectResponse($api->getUri($stemUri),'getUri'));
      if($this->getAnnotationStem() == NULL) {
        $form_state->setErrorByName('annotation_stem', $this->t('Value for Annotation Stem is not valid. Please enter a valid stem.'));
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

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('annotation'));
      return;
    } 

    try {

      $belongsTo = '';
      if ($form_state->getValue('annotation_container') != NULL && $form_state->getValue('annotation_container') != '') {
        $belongsTo = Utils::uriFromAutocomplete($form_state->getValue('annotation_container'));
      } 

      $useremail = \Drupal::currentUser()->getEmail();

      // CREATE A NEW DETECTOR
      $newAnnotationUri = Utils::uriGen('annotation');
      $annotationJson = '{"uri":"'.$newAnnotationUri.'",'.
        '"typeUri":"'.VSTOI::ANNOTATION.'",'.
        '"hascoTypeUri":"'.VSTOI::ANNOTATION.'",'.
        '"hasAnnotationStem":"'.$this->getAnnotationStem()->uri.'",'.
        '"hasPosition":"'.$form_state->getValue('annotation_position').'",'.
        '"hasContentWithStyle":"'.$form_state->getValue('annotation_style').'",'.
        '"comment":"'.$form_state->getValue('annotation_description').'",'.
        '"belongsTo":"'.$belongsTo.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';
      $api->annotationAdd($annotationJson);
    
      \Drupal::messenger()->addMessage(t("Annotation has been added successfully."));
      $form_state->setRedirectUrl(Utils::selectBackUrl('annotation'));
      return;

    } catch(\Exception $e) {
      \Drupal::messenger()->addMessage(t("An error occurred while adding the Annotation: ".$e->getMessage()));
      $form_state->setRedirectUrl(Utils::selectBackUrl('annotation'));
    }

  }

}