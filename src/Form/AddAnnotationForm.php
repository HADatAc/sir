<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

  protected $crumbs;

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

  public function getBreadcrumbs() {
    return $this->crumbs;
  }

  public function setBreadcrumbs(array $crumbs) {
    return $this->crumbs = $crumbs;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $containeruri = NULL, $breadcrumbs = NULL) {

    // SET CONTEXT
    $uri=base64_decode($containeruri);
    if ($breadcrumbs == "_") {
      $crumbs = array();
    } else {
      $crumbs = explode('|',$breadcrumbs);
    }
    $this->setBreadcrumbs($crumbs);

    // GET manager EMAIL
    $this->manager_email = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $this->manager_name = $user->name->value;

    // RETRIEVE CONTAINER BY URI
    $api = \Drupal::service('rep.api_connector');
    $container = $api->parseObjectResponse($api->getUri($uri),'getUri');
    if ($container == NULL) {
      \Drupal::messenger()->addError(t("Cannot read annotations from null container."));
      $this->backToSlotElement($form_state);
    }
    $this->setContainer($container);

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    $tables = new Tables;
    $positions = $tables->getInstrumentPositions();

    $belongsTo = ' ';
    if ($this->getContainer() != NULL &&
        $this->getContainer()->uri != NULL &&
        $this->getContainer()->label != NULL) {
      $belongsTo = Utils::fieldToAutocomplete($this->getContainer()->uri,$this->getContainer()->label);
    }

    $form['annotation_container'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Container'),
      '#default_value' => $belongsTo,
      '#disabled' => TRUE,
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
      self::backUrl();
      return;
    }

    try {

      $belongsTo = '';
      if ($form_state->getValue('annotation_container') != NULL && $form_state->getValue('annotation_container') != '') {
        $belongsTo = Utils::uriFromAutocomplete($form_state->getValue('annotation_container'));
      }

      $useremail = \Drupal::currentUser()->getEmail();

      // CREATE A NEW ANNOTATION
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
      self::backUrl();
      return;

      return;

    } catch(\Exception $e) {
      \Drupal::messenger()->addError(t("An error occurred while adding the Annotation: ".$e->getMessage()));
      self::backUrl();
      return;

    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_annotation');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }



}
