<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;

class EditAnnotationForm extends FormBase {

  protected $annotationUri;

  protected $annotation;

  protected $sourceAnnotation;

  protected $instrument;

  public function getAnnotationUri() {
    return $this->annotationUri;
  }

  public function setAnnotationUri($uri) {
    return $this->annotationUri = $uri;
  }

  public function getAnnotation() {
    return $this->annotation;
  }

  public function setAnnotation($obj) {
    return $this->annotation = $obj;
  }

  public function getInstrument() {
    return $this->instrument;
  }

  public function setInstrument($obj) {
    return $this->instrument = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_annotation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $annotationuri = NULL) {

    // HANDLE CURRENT ANNOTATION
    $uri=$annotationuri;
    $uri_decode=base64_decode($uri);
    $uri_full=Utils::plainUri($uri_decode);
    $this->setAnnotationUri($uri_full);

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    $this->setAnnotation($api->parseObjectResponse($api->getUri($this->getAnnotationUri()), 'getUri'));
    $this->setInstrument($api->parseObjectResponse($api->getUri($this->getAnnotation()->belongsTo), 'getUri'));

    $tables = new Tables;
    $positions = $tables->getInstrumentPositions();

    //dpm($this->getAnnotation());

    $containerLabel = "";
    if ($this->getInstrument() != NULL && $this->getInstrument()->label != NULL) {
      $containerLabel = $this->getInstrument()->label . ' [' . $this->getInstrument()->uri . ']';
    }
    $stemLabel = "";
    if ($this->getAnnotation()->annotationStem != NULL) {
      $stemLabel = $this->getAnnotation()->annotationStem->hasContent . ' [' . $this->getAnnotation()->annotationStem->uri . ']';
    }


    $form['annotation_container'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Instrument'),
      '#default_value' => $containerLabel,
      '#disabled' => TRUE,
    ];
    $form['annotation_position'] = [
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#options' => $positions,
      '#default_value' => $this->getAnnotation()->hasPosition,
    ];
    $form['annotation_stem'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Annotation Stem'),
      '#default_value' => $stemLabel,
      '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
    ];
    $form['annotation_style'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content w/Style (HTML)'),
      '#default_value' => $this->getAnnotation()->hasContentWithStyle,
    ];
    $form['annotation_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getAnnotation()->comment,
    ];
    $form['update_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['cancel_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
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
      if(strlen($form_state->getValue('annotation_container')) < 1) {
        $form_state->setErrorByName('annotation_container', $this->t('Please fill out a value for container.'));
      }
      if(strlen($form_state->getValue('annotation_stem')) < 1) {
        $form_state->setErrorByName('annotation_stem', $this->t('Please fill out a value for annotation_stem'));
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
      self::backUrl();
      return;
    }

    $container = '';
    if ($form_state->getValue('annotation_container') != NULL && $form_state->getValue('annotation_container') != '') {
      $container = Utils::uriFromAutocomplete($form_state->getValue('annotation_container'));
    }

    $stem = '';
    if ($form_state->getValue('annotation_stem') != NULL && $form_state->getValue('annotation_stem') != '') {
      $stem = Utils::uriFromAutocomplete($form_state->getValue('annotation_stem'));
    }

    try{

      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $annotationJson = '{"uri":"'.$this->getAnnotation()->uri.'",'.
        '"typeUri":"'.VSTOI::ANNOTATION.'",'.
        '"hascoTypeUri":"'.VSTOI::ANNOTATION.'",'.
        '"hasAnnotationStem":"'.$stem.'",'.
        '"hasPosition":"'.$form_state->getValue('annotation_position').'",'.
        '"hasContentWithStyle":"'.$form_state->getValue('annotation_style').'",'.
        '"comment":"'.$form_state->getValue('annotation_description').'",'.
        '"belongsTo":"'.$container.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->annotationDel($this->getAnnotationUri());
      $updatedAnnotation = $api->annotationAdd($annotationJson);
      \Drupal::messenger()->addMessage(t("Annotation has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Annotation: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.edit_annotation');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }


}
