<?php

namespace Drupal\sir\Form\Review;

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

class ReviewDetectorForm extends FormBase {

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
    return 'review_detector_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $detectoruri = NULL) {

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

    //dpm($this->getDetector());

    $form['detector_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'max-width: 1280px;margin-bottom:15px!important;',
      ],
    ];

    $form['detector_wrapper']['detector_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getDetectorUri()).'">'.$this->getDetectorUri().'</a>'),
    ];

    $form['detector_wrapper']['detector_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="col border border-white">',
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
      '#disabled' => TRUE
    ];
    $form['detector_wrapper']['detector_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.detector_codebook_autocomplete',
      '#disabled' => TRUE
    ];
    $form['detector_wrapper']['detector_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getDetector()->hasStatus === VSTOI::CURRENT || $this->getDetector()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getDetector()->hasVersion + 1 : $this->getDetector()->hasVersion,
      '#disabled' => TRUE
    ];
    $form['detector_wrapper']['detector_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'detector_isAttributeOf',
        '#default_value' => $this->getDetector()->isAttributeOf,
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
      '#disabled' => TRUE
    ];
    if ($this->getDetector()->wasDerivedFrom !== NULL) {
      $form['detector_wrapper']['detector_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'],
          'style' => "width: 100%; gap: 10px;",
        ],
      ];

      $form['detector_wrapper']['detector_df_wrapper']['detector_wasderivedfrom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Derived From'),
        '#default_value' => $this->getDetector()->wasDerivedFrom,
        '#attributes' => [
          'class' => ['flex-grow-1'],
          'style' => "width: 100%; min-width: 0;",
          'disabled' => 'disabled',
        ],
      ];

      $elementUri = Utils::namespaceUri($this->getDetector()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['detector_wrapper']['detector_df_wrapper']['detector_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }
    $form['detector_wrapper']['detector_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getDetector()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
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
    $form['detector_wrapper']['detector_information']['detector_image_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Image Type'),
      '#options' => [
        '' => $this->t('Select Image Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#disabled' => TRUE,
      '#default_value' => $image_type,
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['detector_wrapper']['detector_information']['detector_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $detector_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="detector_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['detector_wrapper']['detector_information']['detector_image_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['detector_wrapper']['detector_information']['detector_image_upload_wrapper']['detector_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
        'file_validate_size' => [2097152],
      ],
      '#disabled' => TRUE,
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_image_fid ? [$existing_image_fid] : NULL,
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
    $form['detector_wrapper']['detector_information']['detector_webdocument_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Web Document Type'),
      '#options' => [
        '' => $this->t('Select Document Type'),
        'url' => $this->t('URL'),
        'upload' => $this->t('Upload'),
      ],
      '#disabled' => TRUE,
      '#default_value' => $webdocument_type,
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['detector_wrapper']['detector_information']['detector_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $detector_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="detector_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['detector_wrapper']['detector_information']['detector_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['detector_wrapper']['detector_information']['detector_webdocument_upload_wrapper']['detector_webdocument_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/webdoc',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'],
      ],
      '#disabled' => TRUE,
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_fid ? [$existing_fid] : NULL,
    ];

    $form['detector_wrapper']['detector_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getDetector()->hasReviewNote,
    ];
    $form['detector_wrapper']['detector_haseditoremail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reviewer Email'),
      '#default_value' => \Drupal::currentUser()->getEmail(),
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['review_approve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Approve'),
      '#name' => 'review_approve',
      '#attributes' => [
        'onclick' => 'if(!confirm("Are you sure you want to Approve?")){return false;}',
        'class' => ['btn', 'btn-success', 'aprove-button'],
      ],
    ];
    $form['review_reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
      '#name' => 'review_reject',
      '#attributes' => [
        'onclick' => 'if(!confirm("Are you sure you want to Reject?")){return false;}',
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br>'),
    ];
    $form['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
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
      // if ($button_name === 'review_reject') {
      //   if(strlen($form_state->getValue('detector_hasreviewnote')) < 1) {
      //     $form_state->setErrorByName('detector_hasreviewnote', $this->t('You must enter a Reject Note'));
      //   }
      // }
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('detector_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getDetector();

      //APROVE
      if ($button_name !== 'review_reject') {

        $detectorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
          '"hasDetectorStem":"'.$result->hasDetectorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('detector_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'",'.
          '"hasImageUri":"'.$result->hasImageUri.'",'.
          '"hasWebDocument":"'.$result->hasWebDocument.'"'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('detector', $result->uri);
        $api->elementAdd('detector', $detectorJson);

        //IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED
        if ($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') {

          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentDetectorJson = '{'.
            '"uri":"'.$resultParent->uri.'",'.
            '"typeUri":"'.$resultParent->typeUri.'",'.
            '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
            '"hasDetectorStem":"'.$resultParent->hasDetectorStem.'",'.
            '"hasCodebook":"'.$resultParent->hasCodebook.'",'.
            '"hasContent":"'.$resultParent->label.'",'.
            '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'",'.
            '"label":"'.$resultParent->label.'",'.
            '"hasVersion":"'.$resultParent->hasVersion.'",'.
            '"isAttributeOf":"'.$resultParent->isAttributeOf.'",'.
            '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",'.
            '"hasReviewNote":"'.$resultParent->hasReviewNote.'",'.
            '"hasEditorEmail":"'.$resultParent->hasEditorEmail.'",'.
            '"hasStatus":"'.VSTOI::DEPRECATED.'",'.
            '"hasImageUri":"'.$resultParent->hasImageUri.'",'.
            '"hasWebDocument":"'.$resultParent->hasWebDocument.'"'.
          '}';

          // UPDATE BY DELETING AND CREATING
          $api->elementDel('detector', $resultParent->uri);
          $api->elementAdd('detector', $parentDetectorJson);
        }

        \Drupal::messenger()->addMessage(t("Detector has been APPROVED successfully."));
          self::backUrl();
          return;

      // REJECT
      } else {

        //MAIN BODY CODEBOOK
        $detectorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
          '"hasDetectorStem":"'.$result->hasDetectorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('detector_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasImageUri":"'.$result->hasImageUri.'",'.
          '"hasWebDocument":"'.$result->hasWebDocument.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // \Drupal::messenger()->addWarning($detectorJson);
        // return false;

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('detector', $result->uri);
        $api->elementAdd('detector', $detectorJson);

        \Drupal::messenger()->addWarning(t("Detector has been REJECTED."));
          self::backUrl();
          return;
      }

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
