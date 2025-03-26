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
use Drupal\file\Entity\File;

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

  protected $detectorUri;

  public function setDetectorUri() {
    $this->detectorUri = Utils::uriGen('detector');
  }

  public function getDetectorUri() {
    return $this->detectorUri;
  }

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

    // Check if the detector URI already exists in the form state.
    // If not, generate a new URI and store it in the form state.
    if (!$form_state->has('detector_uri')) {
      $this->setDetectorUri();
      $form_state->set('detector_uri', $this->getDetectorUri());
    }
    else {
      // Retrieve the persisted URI from form state.
      $this->detectorUri = $form_state->get('detector_uri');
    }

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
        '#markup' => '<div class="col border border-white">',
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

    // Add a hidden field to persist the detector URI between form rebuilds.
    $form['detector_uri'] = [
      '#type' => 'hidden',
      '#value' => $this->detectorUri,
    ];

    // Add a select box to choose between URL and Upload.
    $form['detector_image_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Type'),
      '#options' => [
        '' => $this->t('Select Image Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#default_value' => '',
    ];

    // The textfield for entering a URL.
    // It is only visible when the select box value is 'url'.
    $form['detector_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="detector_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted detector URI for file uploads)
    $modUri = (explode(":/", utils::namespaceUri($this->detectorUri)))[1];
    $form['detector_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="detector_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['detector_image_upload_wrapper']['detector_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'], // Adjust allowed extensions as needed.
        'file_validate_size' => [2097152],
      ],
    ];

    // Add a select box to choose between URL and Upload.
    $form['detector_webdocument_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Web Document Type'),
      '#options' => [
        '' => $this->t('Select Document Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#default_value' => '',
    ];

    // The textfield for entering a URL.
    // It is only visible when the select box value is 'url'.
    $form['detector_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="detector_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted detector URI for file uploads)
    $form['detector_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="detector_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['detector_webdocument_upload_wrapper']['detector_webdocument_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/webdoc',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'], // Adjust allowed extensions as needed.
        'file_validate_size' => [2097152],
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
      $rawresponse = $api->getUri(Utils::uriFromAutocomplete($form_state->getValue('detector_stem')));
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

      // Get the current user email and generate a new detector URI.
      $useremail = \Drupal::currentUser()->getEmail();
      // $newInstrumentUri = Utils::uriGen('detector');
      $newDetectorUri = $form_state->getValue('detector_uri');

      // Determine the chosen document type.
      $doc_type = $form_state->getValue('detector_webdocument_type');
      $detector_webdocument = '';

      // If user selected URL, use the textfield value.
      if ($doc_type === 'url') {
        $detector_webdocument = $form_state->getValue('detector_webdocument_url');
      }
      // If user selected Upload, load the file entity and get its filename.
      elseif ($doc_type === 'upload') {
        // Get the file IDs from the managed_file element.
        $fids = $form_state->getValue('detector_webdocument_upload');
        if (!empty($fids)) {
          // Load the first file (file ID is returned, e.g. "374").
          $file = File::load(reset($fids));
          if ($file) {
            // Mark the file as permanent and save it.
            $file->setPermanent();
            $file->save();
            // Optionally register file usage to prevent cleanup.
            \Drupal::service('file.usage')->add($file, 'sir', 'detector', 1);
            // Now get the filename from the file entity.
            $detector_webdocument = $file->getFilename();
          }
        }
      }

      // Determine the chosen image type.
      $image_type = $form_state->getValue('detector_image_type');
      $detector_image = '';

      // If user selected URL, use the textfield value.
      if ($image_type === 'url') {
        $detector_image = $form_state->getValue('detector_image_url');
      }
      // If user selected Upload, load the file entity and get its filename.
      elseif ($image_type === 'upload') {
        // Get the file IDs from the managed_file element.
        $fids = $form_state->getValue('detector_image_upload');
        if (!empty($fids)) {
          // Load the first file (file ID is returned, e.g. "374").
          $file = File::load(reset($fids));
          if ($file) {
            // Mark the file as permanent and save it.
            $file->setPermanent();
            $file->save();
            // Optionally register file usage to prevent cleanup.
            \Drupal::service('file.usage')->add($file, 'sir', 'detector', 1);
            // Now get the filename from the file entity.
            $detector_image = $file->getFilename();
          }
        }
      }

      // CREATE A NEW DETECTOR
      $detectorJson = '{"uri":"'.$newDetectorUri.'",'.
        '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
        '"hasDetectorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasContent":"'.$label.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'",'.
        '"label":"'.$label.'",'.
        '"hasVersion":"1",'.
        '"isAttributeOf":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_isAttributeOf')).'",'.
        '"hasWebDocument":"' . $detector_webdocument . '",' .
        '"hasImageUri":"' . $detector_image . '",' .
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
