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

class EditDetectorStemForm extends FormBase {

  protected $detectorStemUri;

  protected $detectorStem;

  protected $sourceDetectorStem;

  public function getDetectorStemUri() {
    return $this->detectorStemUri;
  }

  public function setDetectorStemUri($uri) {
    return $this->detectorStemUri = $uri;
  }

  public function getDetectorStem() {
    return $this->detectorStem;
  }

  public function setDetectorStem($obj) {
    return $this->detectorStem = $obj;
  }

  public function getSourceDetectorStem() {
    return $this->sourceDetectorStem;
  }

  public function setSourceDetectorStem($obj) {
    return $this->sourceDetectorStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_detectorstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $detectorstemuri = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_detectorstem';

    $uri=$detectorstemuri;
    $uri_decode=base64_decode($uri);
    $this->setDetectorStemUri($uri_decode);

    $this->setDetectorStem($this->retrieveDetectorStem($this->getDetectorStemUri()));
    if ($this->getDetectorStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Detector."));
      self::backUrl();
      return;
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    // IN CASE ITS A DERIVATION ORIGINAL MUST BE REMOVED ALSO
    if ($this->getDetectorStem()->hasStatus === VSTOI::CURRENT || $this->getDetectorStem()->hasVersion > 1) {
      unset($derivations[Constant::DEFAULT_WAS_GENERATED_BY]);
    }

    $languages = ['' => $this->t('Select one please')] + $languages;
    $derivations = ['' => $this->t('Select one please')] + $derivations;

    $form['detectorstem_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getDetectorStemUri()).'">'.$this->getDetectorStemUri().'</a>'),
    ];

    // dpm($this->getDetectorStem());

    $form['detectorstem_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Parent Type'),
        '#name' => 'detectorstem_type',
        '#default_value' => $this->getDetectorStem()->superUri ? Utils::fieldToAutocomplete($this->getDetectorStem()->superUri, $this->getDetectorStem()->superClassLabel) : '',
        '#id' => 'detectorstem_type',
        '#parents' => ['detectorstem_type'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'detectorstem',
          ], ['query' => ['field_id' => 'detectorstem_type']])->toString(),
          'data-field-id' => 'detectorstem_type',
          'data-elementtype' => 'detectorstem',
          'data-search-value' => $this->getDetectorStem()->superUri ?? '',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];

    $form['detectorstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getDetectorStem()->hasContent,
    ];
    $form['detectorstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getDetectorStem()->hasLanguage,
      '#attributes' => [
        'id' => 'detectorstem_language'
      ]
    ];
    $form['detectorstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getDetectorStem()->hasStatus === VSTOI::CURRENT || $this->getDetectorStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getDetectorStem()->hasVersion + 1 : $this->getDetectorStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['detectorstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getDetectorStem()->comment,
    ];

    if ($this->getDetectorStem()->wasDerivedFrom !== NULL) {
      $form['detectorstem_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      if ($this->getDetectorStem()->wasDerivedFrom !== NULL) {
        $form['detectorstem_df_wrapper']['detectorstem_wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => $this->getDetectorStem()->wasDerivedFrom,
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 0;",
            'disabled' => 'disabled',
          ],
        ];
      }

      $elementUri = Utils::namespaceUri($this->getDetectorStem()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['detectorstem_df_wrapper']['detectorstem_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-primary text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }
    $form['detectorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $this->getDetectorStem()->wasGeneratedBy,
      '#attributes' => [
        'id' => 'detectorstem_was_generated_by'
      ],
      '#disabled' => ($this->getDetectorStem()->wasGeneratedBy === Constant::WGB_ORIGINAL ? true:false)
    ];
    if ($this->getDetectorStem()->hasReviewNote !== NULL && $this->getDetectorStem()->hasStatus !== null) {
      $form['detectorstem_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getDetectorStem()->hasReviewNote,
        '#disabled' => TRUE
      ];
      $form['detectorstem_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => \Drupal::currentUser()->getEmail(),
        '#attributes' => [
          'disabled' => 'disabled',
        ],
      ];
    }

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current detectorstem and its image.
    $detectorstem = $this->getDetectorStem();
    $detectorstem_uri = Utils::namespaceUri($this->getDetectorStemUri());
    $detectorstem_image = $detectorstem->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($detectorstem_image) && stripos(trim($detectorstem_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($detectorstem_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($detectorstem_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $detectorstem_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['detectorstem_information']['detectorstem_image_type'] = [
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
    $form['detectorstem_information']['detectorstem_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $detectorstem_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="detectorstem_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['detectorstem_information']['detectorstem_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="detectorstem_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($detectorstem_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $detectorstem_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['detectorstem_information']['detectorstem_image_upload_wrapper']['detectorstem_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
        'file_validate_size' => [2097152],
      ],
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_image_fid ? [$existing_image_fid] : NULL,
    ];

    // **** WEBDOCUMENT ****
    // Retrieve the current web document value.
    $detectorstem_webdocument = $detectorstem->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($detectorstem_webdocument) && stripos(trim($detectorstem_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($detectorstem_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['detectorstem_information']['detectorstem_webdocument_type'] = [
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
    $form['detectorstem_information']['detectorstem_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $detectorstem_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="detectorstem_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['detectorstem_information']['detectorstem_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="detectorstem_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($detectorstem_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $detectorstem_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['detectorstem_information']['detectorstem_webdocument_upload_wrapper']['detectorstem_webdocument_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/webdoc',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'],
      ],
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_fid ? [$existing_fid] : NULL,
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
        'id' => 'cancel_button'
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
      if(strlen($form_state->getValue('detectorstem_content')) < 1) {
        $form_state->setErrorByName('detectorstem_content', $this->t('Please enter a valid Name'));
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

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getDetectorStem()->hasStatus === VSTOI::CURRENT || $this->getDetectorStem()->hasStatus === VSTOI::DEPRECATED) {

        $detectorStemJson = '{"uri":"'.Utils::uriGen('detectorstem').'",'.
          '"superUri":"'.Utils::uriFromAutocomplete($this->getDetectorStem()->superUri).'",'.
          '"label":"'.$form_state->getValue('detectorstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('detectorstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('detectorstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('detectorstem_version').'",'.
          '"comment":"'.$form_state->getValue('detectorstem_description').'",'.
          '"wasDerivedFrom":"'.$this->getDetectorStem()->uri.'",'. //Previous Version is the New Derivation Value
          '"wasGeneratedBy":"'.$form_state->getValue('detectorstem_was_generated_by').'",'.
          '"hasWebDocument":"",'.
          '"hasImageUri":"",' .
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->detectorStemAdd($detectorStemJson);
        \Drupal::messenger()->addMessage(t("New Version Detector Stem has been created successfully."));

      } else {

        // Determine the chosen document type.
        $doc_type = $form_state->getValue('detectorstem_webdocument_type');
        $detectorstem_webdocument = '';

        // If user selected URL, use the textfield value.
        if ($doc_type === 'url') {
          $detectorstem_webdocument = $form_state->getValue('detectorstem_webdocument_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($doc_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('detectorstem_webdocument_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'detectorstem', 1);
              // Now get the filename from the file entity.
              $detectorstem_webdocument = $file->getFilename();
            }
          }
        }

        // Determine the chosen image type.
        $image_type = $form_state->getValue('detectorstem_image_type');
        $detectorstem_image = '';

        // If user selected URL, use the textfield value.
        if ($image_type === 'url') {
          $detectorstem_image = $form_state->getValue('detectorstem_image_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($image_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('detectorstem_image_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'detectorstem', 1);
              // Now get the filename from the file entity.
              $detectorstem_image = $file->getFilename();
            }
          }
        }

        $detectorStemJson = '{"uri":"'.$this->getDetectorStem()->uri.'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($form_state->getValue('detectorstem_type')).'",'.
        '"label":"'.$form_state->getValue('detectorstem_content').'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
        '"hasStatus":"'.$this->getDetectorStem()->hasStatus.'",'.
        '"hasContent":"'.$form_state->getValue('detectorstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('detectorstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('detectorstem_version').'",'.
        '"comment":"'.$form_state->getValue('detectorstem_description').'",'.
        '"hasWebDocument":"' . $detectorstem_webdocument . '",' .
        '"hasImageUri":"' . $detectorstem_image . '",' .
        '"wasDerivedFrom":"'.$this->getDetectorStem()->wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('detectorstem_was_generated_by').'",'.
        '"hasReviewNote":"'.($this->getDetectorStem()->hasStatus !== null ? $this->getDetectorStem()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getDetectorStem()->hasStatus !== null ? $this->getDetectorStem()->hasEditorEmail : '').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->detectorStemDel($this->getDetectorStemUri());
        $api->detectorStemAdd($detectorStemJson);
        \Drupal::messenger()->addMessage(t("Detector Stem has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Detector Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveDetectorStem($detectorStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($detectorStemUri);
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
