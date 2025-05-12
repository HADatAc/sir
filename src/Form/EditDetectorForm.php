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
use Drupal\rep\Vocabulary\REPGUI;
use Drupal\file\Entity\File;
use Drupal\Core\Render\Markup;

class EditDetectorForm extends FormBase {

  protected $detectorUri;

  protected $detector;

  protected $sourceDetector;

  public function getDetectorUri() {
    return $this->detectorUri;
  }

  public function setDetectorUri($uri) {
    return $this->detectorUri = $uri;
  }

  public function getDetector() {
    return $this->detector;
  }

  public function setDetector($obj) {
    return $this->detector = $obj;
  }

  public function getSourceDetector() {
    return $this->sourceDetector;
  }

  public function setSourceDetector($obj) {
    return $this->sourceDetector = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_detector_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $detectoruri = NULL) {

    // Does the repo have a social network?
    $socialEnabled = \Drupal::config('rep.settings')->get('social_conf');

     // ROOT URL
     $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';


    $uri=$detectoruri;
    $uri_decode=base64_decode($uri);
    $this->setDetectorUri($uri_decode);

    $sourceContent = '';
    $stemLabel = '';
    $codebookLabel = '';
    $this->setDetector($this->retrieveDetector($this->getDetectorUri()));
    if ($this->getDetector() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Detector."));
      self::backUrl();
      return;
    } else {
      if ($this->getDetector()->detectorStem != NULL) {
        $stemLabel = $this->getDetector()->detectorStem->hasContent . ' [' . $this->getDetector()->detectorStem->uri . ']';
      }
      if ($this->getDetector()->codebook != NULL) {
        $codebookLabel = $this->getDetector()->codebook->label . ' [' . $this->getDetector()->codebook->uri . ']';
      }
    }

    // dpm($this->getDetector());

    $form['detector_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getDetectorUri()).'">'.$this->getDetectorUri().'</a>'),
    ];

    $form['detector_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => \Drupal::moduleHandler()->moduleExists('pmsr') ?
          $this->t('Simulation Technique Stem') :
          $this->t('Detector Stem'),
        '#name' => 'detector_stem',
        '#default_value' => Utils::fieldToAutocomplete($this->getDetector()->typeUri, $this->getDetector()->detectorStem->label),
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
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.detector_codebook_autocomplete',
    ];
    // if ($socialEnabled) {
      $form['detector_maker'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Maker'),
        '#default_value' => isset($this->getDetector()->hasMakerUri) ?
                              Utils::fieldToAutocomplete($this->getDetector()->hasMakerUri, $this->getDetector()->hasMaker->label) : '',
        // '#required' => TRUE,
        '#autocomplete_route_name'       => 'rep.social_autocomplete',
        '#autocomplete_route_parameters' => [
          'entityType' => 'organization',
        ],
      ];
    // }
    $form['detector_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => isset($this->getDetector()->hasVersion) && $this->getDetector()->hasVersion !== null
        ? (
            ($this->getDetector()->hasStatus === VSTOI::CURRENT || $this->getDetector()->hasStatus === VSTOI::DEPRECATED)
              ? $this->getDetector()->hasVersion + 1
              : $this->getDetector()->hasVersion
          )
        : 1,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    if (isset($this->getDetector()->isAttributeOf)) {
      $api = \Drupal::service('rep.api_connector');
      $attributOf = $api->parseObjectResponse($api->getUri($this->getDetector()->isAttributeOf),'getUri');
    }
    $form['detector_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'detector_isAttributeOf',
        '#default_value' => (isset($attributOf) ? $attributOf->label . ' [' . $this->getDetector()->isAttributeOf . ']' : ''),
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

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current detector and its image.
    $detector = $this->getDetector();
    $detector_uri = Utils::namespaceUri($this->getDetectorUri());
    $detector_image = $detector->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($detector_image) && stripos(trim($detector_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($detector_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($detector_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $detector_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['detector_information']['detector_image_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Type'),
      '#options' => [
        '' => $this->t('Select Image Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#default_value' => $image_type,
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['detector_information']['detector_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $detector_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="detector_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['detector_information']['detector_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="detector_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($detector_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $detector_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['detector_information']['detector_image_upload_wrapper']['detector_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'], // Allowed file extensions.
        'file_validate_size' => [2097152], // Maximum file size (in bytes).
      ],
      // Description in red: allowed file types and a warning that choosing a new image will remove the previous one.
      '#description' => Markup::create('<span style="color: red;">Allowed file types: png, jpg, jpeg. Selecting a new image will remove the previous one.</span>'),
    ];

    // **** WEBDOCUMENT ****
    // Retrieve the current web document value.
    $detector_webdocument = $detector->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($detector_webdocument) && stripos(trim($detector_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($detector_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['detector_information']['detector_webdocument_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Web Document Type'),
      '#options' => [
        '' => $this->t('Select Document Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#default_value' => $webdocument_type,
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['detector_information']['detector_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $detector_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="detector_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['detector_information']['detector_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="detector_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($detector_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $detector_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['detector_information']['detector_webdocument_upload_wrapper']['detector_webdocument_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'],
        'file_validate_size' => [2097152], // Maximum file size (in bytes).
      ],
      // Description in red: allowed file types and a warning that choosing a new image will remove the previous one.
      '#description' => Markup::create('<span style="color: red;">Allowed file types: pdf, doc, docx, txt, xls, xlsx. Selecting a new document will remove the previous one.</span>'),
    ];

    if ($this->getDetector()->hasReviewNote !== NULL && $this->getDetector()->hasSatus !== null) {
      $form['detector_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getDetector()->hasReviewNote,
        '#attributes' => [
          'disabled' => 'disabled',
        ]
      ];
      $form['detector_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => $this->getDetector()->hasEditorEmail,
        '#attributes' => [
          'disabled' => 'disabled',
        ],
      ];
    }
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
      if(strlen($form_state->getValue('detector_stem')) < 1) {
        $form_state->setErrorByName('detector_stem', $this->t('Please enter a valid detector stem'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $api = \Drupal::service('rep.api_connector');
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{

      $uid = \Drupal::currentUser()->id();
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

      $hasCodebook = '';
      if ($form_state->getValue('detector_codebook') != NULL && $form_state->getValue('detector_codebook') != '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('detector_codebook'));
      }

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getDetector()->hasStatus === VSTOI::CURRENT || $this->getDetector()->hasStatus === VSTOI::DEPRECATED) {

        $newDetectorUri = Utils::uriGen('detector');
        $detectorJson = '{"uri":"'.$newDetectorUri.'",'.
        '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
        '"hasDetectorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasContent":"'.$label.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'",'.
        '"label":"'.$label.'",'.
        '"hasWebDocument":"",'.
        '"hasImageUri":"",' .
        '"hasVersion":"'.$form_state->getValue('detector_version').'",'.
        '"isAttributeOf":"'.$form_state->getValue('detector_isAttributeOf').'",'.
        '"wasDerivedFrom":"'.$this->getDetector()->uri.'",'.
        '"hasReviewNote":"'.($this->getDetector()->hasSatus !== null ? $this->getDetector()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getDetector()->hasSatus !== null ? $this->getDetector()->hasEditorEmail : '').'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'"}';

        $api->elementAdd('detector', $detectorJson);
        \Drupal::messenger()->addMessage(t("New Version detector has been created successfully."));

      } else {

        // Determine the chosen document type.
        $doc_type = $form_state->getValue('detector_webdocument_type');
        $detector_webdocument = $this->getDetector()->hasWebDocument;

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
        $detector_image = $this->getDetector()->hasImageUri;

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

        $detectorJson = '{"uri":"'.$this->getDetector()->uri.'",'.
          '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
          '"hasDetectorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_stem')).'",'.
          '"hasCodebook":"'.$hasCodebook.'",'.
          '"hasContent":"'.$label.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'",'.
          '"label":"'.$label.'",'.
          '"hasWebDocument":"' . $detector_webdocument . '",' .
          '"hasImageUri":"' . $detector_image . '",' .
          '"hasVersion":"'.$form_state->getValue('detector_version').'",'.
          '"isAttributeOf":"'.$form_state->getValue('detector_isAttributeOf').'",'.
          '"wasDerivedFrom":"'.$this->getDetector()->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$this->getDetector()->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$this->getDetector()->hasEditorEmail.'",'.
          '"hasMakerUri":"'.Utils::uriFromAutocomplete($form_state->getValue('detector_maker')).'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        $api->elementDel('detector', $this->getDetectorUri());
        $api->elementAdd('detector', $detectorJson);

        // UPLOAD IMAGE TO API
        if ($image_type === 'upload' && $detector_image !== $this->getDetector()->hasImageUri) {
          $fids = $form_state->getValue('detector_image_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getDetectorUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded Image FAILED to be submited to API."));
          }
        }

        // UPLOAD DOCUMENT TO API
        if ($doc_type === 'upload' && $detector_webdocument !== $this->getDetector()->hasWebDocument) {
          $fids = $form_state->getValue('detector_webdocument_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getDetectorUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded WebDocument FAILED to be submited to API."));
          }
        }

        \Drupal::messenger()->addMessage(t("Detector has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Detector: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveDetector($detectorUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($detectorUri);
    $obj = json_decode($rawresponse);
    if ($obj->isSuccessful) {
      return $obj->body;
    }
    return NULL;
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, \Drupal::request()->getRequestUri());
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
