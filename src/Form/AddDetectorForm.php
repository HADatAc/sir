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

class AddDetectorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_detector_form';
  }

  protected $sourceDetectorUri;

  protected $sourceDetector;

  protected $detectorStem;

  protected $containerslotUri;

  protected $containerslot;

  public function getSourceDetectorUri() {
    return $this->sourceDetectorUri;
  }

  public function setSourceDetectorUri($uri) {
    return $this->sourceDetectorUri = $uri;
  }

  public function getSourceDetector() {
    return $this->sourceDetector;
  }

  public function setSourceDetector($obj) {
    return $this->sourceDetector = $obj;
  }

  public function getDetectorStem() {
    return $this->detectorStem;
  }

  public function setDetectorStem($stem) {
    return $this->detectorStem = $stem;
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
  public function buildForm(array $form, FormStateInterface $form_state, $sourcedetectoruri = NULL, $containersloturi = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    // HANDLE SOURCE DETECTOR,  IF ANY
    $sourceuri=$sourcedetectoruri;
    if ($sourceuri === NULL || $sourceuri === 'EMPTY') {
      $this->setSourceDetector(NULL);
      $this->setSourceDetectorUri('');
    } else {
      $sourceuri_decode=base64_decode($sourceuri);
      $this->setSourceDetectorUri($sourceuri_decode);
      $rawresponse = $api->getUri($this->getSourceDetectorUri());
      //dpm($rawresponse);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setSourceDetector($obj->body);
        //dpm($this->getDetector());
      } else {
        $this->setSourceDetector(NULL);
        $this->setSourceDetectorUri('');
      }
    }
    $disabledDerivationOption = ($this->getSourceDetector() === NULL);

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
    if ($this->getSourceDetector() != NULL) {
      $sourceContent = $this->getSourceDetector()->hasContent;
    }

    // $form['detector_stem'] = [
    //   '#type' => 'textfield',
    //   '#title' => \Drupal::moduleHandler()->moduleExists('pmsr') ?
    //     $this->t('Simulation Technique Stem') :
    //     $this->t('Detector Stem'),
    //   '#autocomplete_route_name' => 'sir.detector_stem_autocomplete',
    // ];
    $form['detector_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Detector Stem'),
        '#name' => 'detector_stem',
        '#default_value' => '',
        '#id' => 'detector_stem',
        '#parents' => ['detector_stem'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'detectorstem',
          ], ['query' => ['field_id' => 'detector_stem']])->toString(),
          'data-field-id' => 'detector_stem',
          'data-elementtype' => 'detectorstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['detector_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#autocomplete_route_name' => 'sir.detector_codebook_autocomplete',
    ];
    $form['detector_version_hidden'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['detector_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['detector_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'detector_isAttributeOf',
        '#default_value' => '',
        '#id' => 'detector_isAttributeOf',
        '#parents' => ['detector_isAttributeOf'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'detectorattribute',
          ], ['query' => ['field_id' => 'detector_isAttributeOf']])->toString(),
          'data-field-id' => 'detector_isAttributeOf',
          'data-elementtype' => 'detectorattribute',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
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

      if ($form_state->getValue('detector_stem') == NULL || $form_state->getValue('detector_stem') == '') {
        $form_state->setErrorByName('detector_stem', $this->t('Detector stem value is empty. Please enter a valid stem.'));
      }
      // $stemUri = Utils::uriFromAutocomplete($form_state->getValue('detector_stem'));
      // $this->setDetectorStem($api->parseObjectResponse($api->getUri($stemUri),'getUri'));
      // if($this->getDetectorStem() == NULL) {
      //   $form_state->setErrorByName('detector_stem', $this->t('Value for Detector Stem is not valid. Please enter a valid stem.'));
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
      if ($form_state->getValue('detector_codebook') !== NULL && $form_state->getValue('detector_codebook') !== '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('detector_codebook'));
      } else {
        $hasCodebook = NULL;
      }

      $useremail = \Drupal::currentUser()->getEmail();

      // GET THE DETECTOR STEM URI
      $rawresponse = $api->getUri($form_state->getValue('detector_stem'));
      $obj = json_decode($rawresponse);
      $result = $obj->body;

      $label = "";
      if ($result->hasContent !== NULL) {
        $label .= $result->hasContent;
      } else {
        $label .= $result->label;
      }

      if ($form_state->getValue('detector_codebook') !== NULL && $form_state->getValue('detector_codebook') != '') {
        $codebook = Utils::uriFromAutocomplete($form_state->getValue('detector_codebook'));
        $rawresponseCB = $api->getUri($codebook);
        $objCB = json_decode($rawresponseCB);
        $resultCB = $objCB->body;
        $label .= '  -- CB:'.$resultCB->label;
      } else {
        $label = $result->label . '  -- CB:EMPTY';
      }

      // CREATE A NEW DETECTOR
      $newDetectorUri = Utils::uriGen('detector');
      $detectorJson = '{"uri":"'.$newDetectorUri.'",'.
        '"typeUri":"'.$form_state->getValue('detector_stem').'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
        '"hasDetectorStem":"'.$form_state->getValue('detector_stem').'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasContent":"'.$label.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'",'.
        '"label":"'.$label.'",'.
        '"hasVersion":"1",'.
        '"isAttributeOf":"'.$form_state->getValue('detector_isAttributeOf').'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'"}';
      $api->detectorAdd($detectorJson);

      // IF IN THE CONTEXT OF AN EXISTING CONTAINER_SLOT, ATTACH THE NEWLY CREATED DETECTOR TO THE CONTAINER_SLOT
      if ($this->getContainerSlot() != NULL) {
        $api->detectorAttach($newDetectorUri,$this->getContainerSlotUri());
        \Drupal::messenger()->addMessage(t("Detector [" . $newDetectorUri ."] has been added and attached to intrument [" . $this->getContainerSlot()->belongsTo . "] successfully."));
        $url = Url::fromRoute('sir.edit_containerslot');
        $url->setRouteParameter('containersloturi', base64_encode($this->getContainerSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      } else {
        \Drupal::messenger()->addMessage(t("Detector has been added successfully."));
        self::backUrl();
        return;
      }
    } catch(\Exception $e) {
      if ($this->getContainerSlot() != NULL) {
        \Drupal::messenger()->addError(t("An error occurred while adding the Detector: ".$e->getMessage()));
        $url = Url::fromRoute('sir.edit_containerslot');
        $url->setRouteParameter('containersloturi', base64_encode($this->getContainerSlotUri()));
        $form_state->setRedirectUrl($url);
      } else {
        \Drupal::messenger()->addError(t("An error occurred while adding the Detector: ".$e->getMessage()));
        self::backUrl();
        return;
      }
    }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_detector');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
