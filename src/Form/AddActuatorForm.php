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

class AddActuatorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_actuator_form';
  }

  protected $sourceActuatorUri;

  protected $sourceActuator;

  protected $actuatorStem;

  protected $containerslotUri;

  protected $containerslot;

  public function getSourceActuatorUri() {
    return $this->sourceActuatorUri;
  }

  public function setSourceActuatorUri($uri) {
    return $this->sourceActuatorUri = $uri;
  }

  public function getSourceActuator() {
    return $this->sourceActuator;
  }

  public function setSourceActuator($obj) {
    return $this->sourceActuator = $obj;
  }

  public function getActuatorStem() {
    return $this->actuatorStem;
  }

  public function setActuatorStem($stem) {
    return $this->actuatorStem = $stem;
  }

  public function getContainerSlotUri() {
    return $this->containerslotUri;
  }

  public function setContainerSlotUri($attachuri) {
    return $this->containerslotUri = $attachuri;
  }

  public function getContainerSlot() {
    return $this->containerslot;
  }

  public function setContainerSlot($attachobj) {
    return $this->containerslot = $attachobj;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sourceactuatoruri = NULL, $containersloturi = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    // HANDLE SOURCE DETECTOR,  IF ANY
    $sourceuri=$sourceactuatoruri;
    if ($sourceuri === NULL || $sourceuri === 'EMPTY') {
      $this->setSourceActuator(NULL);
      $this->setSourceActuatorUri('');
    } else {
      $sourceuri_decode=base64_decode($sourceuri);
      $this->setSourceActuatorUri($sourceuri_decode);
      $rawresponse = $api->getUri($this->getSourceActuatorUri());
      //dpm($rawresponse);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setSourceActuator($obj->body);
        //dpm($this->getActuator());
      } else {
        $this->setSourceActuator(NULL);
        $this->setSourceActuatorUri('');
      }
    }
    $disabledDerivationOption = ($this->getSourceActuator() === NULL);

    // HANDLE CONTAINER_SLOT, IF ANY
    $attachuri=$containersloturi;
    if ($attachuri === NULL || $attachuri === 'EMPTY') {
      $this->setContainerSlot(NULL);
      $this->setContainerSlotUri('');
    } else {
      $attachuri_decode=base64_decode($attachuri);
      $this->setContainerSlotUri($attachuri_decode);
      if ($this->getContainerSlotUri() != NULL) {
        $attachrawresponse = $api->getUri($this->getContainerSlotUri());
        $attachobj = json_decode($attachrawresponse);
        if ($attachobj->isSuccessful) {
          $this->setContainerSlot($attachobj->body);
        }
      }
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    if ($this->getSourceActuator() != NULL) {
      $sourceContent = $this->getSourceActuator()->hasContent;
    }

    // $form['actuator_stem'] = [
    //   '#type' => 'textfield',
    //   '#title' => \Drupal::moduleHandler()->moduleExists('pmsr') ?
    //     $this->t('Simulation Technique Stem') :
    //     $this->t('Actuator Stem'),
    //   '#autocomplete_route_name' => 'sir.actuator_stem_autocomplete',
    // ];
    $form['actuator_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Actuator Stem'),
        '#name' => 'actuator_stem',
        '#default_value' => '',
        '#id' => 'actuator_stem',
        '#parents' => ['actuator_stem'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'actuatorstem',
          ], ['query' => ['field_id' => 'actuator_stem']])->toString(),
          'data-field-id' => 'actuator_stem',
          'data-elementtype' => 'actuatorstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['actuator_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#autocomplete_route_name' => 'sir.actuator_codebook_autocomplete',
    ];
    $form['actuator_version_hidden'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['actuator_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['actuator_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'actuator_isAttributeOf',
        '#default_value' => '',
        '#id' => 'actuator_isAttributeOf',
        '#parents' => ['actuator_isAttributeOf'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'detectorattribute',
          ], ['query' => ['field_id' => 'actuator_isAttributeOf']])->toString(),
          'data-field-id' => 'actuator_isAttributeOf',
          'data-elementtype' => 'detectorattribute',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['actuator_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ]
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

      if ($form_state->getValue('actuator_stem') == NULL || $form_state->getValue('actuator_stem') == '') {
        $form_state->setErrorByName('actuator_stem', $this->t('Actuator stem value is empty. Please enter a valid stem.'));
      }
      // $stemUri = Utils::uriFromAutocomplete($form_state->getValue('actuator_stem'));
      // $this->setActuatorStem($api->parseObjectResponse($api->getUri($stemUri),'getUri'));
      // if($this->getActuatorStem() == NULL) {
      //   $form_state->setErrorByName('actuator_stem', $this->t('Value for Actuator Stem is not valid. Please enter a valid stem.'));
      // }
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

      $hasCodebook = '';
      if ($form_state->getValue('actuator_codebook') !== NULL && $form_state->getValue('actuator_codebook') !== '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('actuator_codebook'));
      } else {
        $hasCodebook = NULL;
      }

      $useremail = \Drupal::currentUser()->getEmail();

      // GET THE DETECTOR STEM URI
      $rawresponse = $api->getUri(Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')));
      $obj = json_decode($rawresponse);
      $result = $obj->body;

      $label = "";
      if ($result->hasContent !== NULL) {
        $label .= $result->hasContent;
      } else {
        $label .= $result->label;
      }

      if ($form_state->getValue('actuator_codebook') !== NULL && $form_state->getValue('actuator_codebook') != '') {
        $codebook = Utils::uriFromAutocomplete($form_state->getValue('actuator_codebook'));
        $rawresponseCB = $api->getUri($codebook);
        $objCB = json_decode($rawresponseCB);
        $resultCB = $objCB->body;
        $label .= '  -- CB:'.$resultCB->label;
      } else {
        $label = $result->label . '  -- CB:EMPTY';
      }

      // CREATE A NEW DETECTOR
      $newActuatorUri = Utils::uriGen('actuator');
      $actuatorJson = '{"uri":"'.$newActuatorUri.'",'.
        '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
        '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
        '"hasActuatorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasContent":"'.$label.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'",'.
        '"label":"'.$label.'",'.
        '"hasVersion":"1",'.
        '"isAttributeOf":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_isAttributeOf')).'",'.
        '"hasWebDocument":"'.$form_state->getValue('actuator_webdocument').'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'"}';

      $api->elementAdd('actuator',$actuatorJson);

      // IF IN THE CONTEXT OF AN EXISTING CONTAINER_SLOT, ATTACH THE NEWLY CREATED DETECTOR TO THE CONTAINER_SLOT
      if ($this->getContainerSlot() != NULL) {
        $api->actuatorAttach($newActuatorUri,$this->getContainerSlotUri());
        \Drupal::messenger()->addMessage(t("Actuator [" . $newActuatorUri ."] has been added and attached to intrument [" . $this->getContainerSlot()->belongsTo . "] successfully."));
        $url = Url::fromRoute('sir.edit_containerslot');
        $url->setRouteParameter('containersloturi', base64_encode($this->getContainerSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      } else {
        \Drupal::messenger()->addMessage(t("Actuator has been added successfully."));
        self::backUrl();
        return;
      }
    } catch(\Exception $e) {
      if ($this->getContainerSlot() != NULL) {
        \Drupal::messenger()->addError(t("An error occurred while adding the Actuator: ".$e->getMessage()));
        $url = Url::fromRoute('sir.edit_containerslot');
        $url->setRouteParameter('containersloturi', base64_encode($this->getContainerSlotUri()));
        $form_state->setRedirectUrl($url);
      } else {
        \Drupal::messenger()->addError(t("An error occurred while adding the Actuator: ".$e->getMessage()));
        self::backUrl();
        return;
      }
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_actuator');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
