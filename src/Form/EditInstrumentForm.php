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
use Drupal\rep\Vocabulary\REPGUI;
use Drupal\file\Entity\File;
use Drupal\Core\Render\Markup;

class EditInstrumentForm extends FormBase {

  protected $instrumentUri;

  protected $instrument;

  protected $container;

  public function getInstrumentUri() {
    return $this->instrumentUri;
  }

  public function setInstrumentUri($uri) {
    return $this->instrumentUri = $uri;
  }

  public function getInstrument() {
    return $this->instrument;
  }

  public function setInstrument($instrument) {
    return $this->instrument = $instrument;
  }

  public function getContainer() {
    return $this->container;
  }

  public function setContainer($container) {
    return $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_instrument_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {

    // Does the repo have a social network?
    $socialEnabled = \Drupal::config('rep.settings')->get('social_conf');

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $uri_decode=base64_decode($instrumenturi);
    $this->setInstrumentUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $informants = $tables->getInformants();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getInstrumentUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setInstrument($obj->body);
      //dpm($this->getInstrument());
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Instrument."));
      self::backUrl();
      return;
    }

    $hasInformant = Constant::DEFAULT_INFORMANT;
    if ($this->getInstrument()->hasInformant != NULL && $this->getInstrument()->hasInformant != '') {
      $hasInformant = $this->getInstrument()->hasInformant;
    }

    $hasLanguage = Constant::DEFAULT_LANGUAGE;
    if ($this->getInstrument()->hasLanguage != NULL && $this->getInstrument()->hasLanguage != '') {
      $hasLanguage = $this->getInstrument()->hasLanguage;
    }

    $form['information'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-publication',
    ];

    // INSTRUMENT RELATED

    $form['instrument_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Simulator Form'),
      '#group' => 'information',
    ];

    // Campo de texto desativado que ocupa todo o espaço disponível
    $form['instrument_information']['instrument_parent_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['align-items-center', 'gap-2', 'mt-2'], // Flexbox para alinhar na mesma linha
        'style' => 'max-width: 1280px;margin-bottom:0!important;',
      ],
    ];

    $form['instrument_information']['instrument_parent_wrapper']['instrument_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getInstrumentUri()).'">'.$this->getInstrumentUri().'</a>'),
    ];

    $form['instrument_information']['instrument_parent_wrapper']['instrument_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Parent Type'),
        '#name' => 'instrument_type',
        '#default_value' => Utils::fieldToAutocomplete($this->getInstrument()->superUri, $this->getInstrument()->superClassLabel),
        '#id' => 'instrument_type',
        '#parents' => ['instrument_type'],
        // '#disabled' => TRUE,
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'instrument',
          ], ['query' => ['field_id' => 'instrument_type']])->toString(),
          'data-field-id' => 'instrument_type',
          'data-elementtype' => 'instrument',
          'data-search-value' => $this->getInstrument()->superUri ?? '',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];

    $form['instrument_information']['instrument_parent_wrapper']['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getInstrument()->label,
    ];
    if ($socialEnabled) {
      $makerUri = $api->getUri($this->getInstrument()->hasMakerUri);
      $form['instrument_information']['instrument_parent_wrapper']['instrument_maker'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Maker'),
        '#default_value' => isset($this->getInstrument()->hasMakerUri) ?
                              Utils::fieldToAutocomplete($this->getInstrument()->hasMakerUri, $makerUri->label) : '',
        // '#required' => TRUE,
        '#autocomplete_route_name'       => 'rep.social_autocomplete',
        '#autocomplete_route_parameters' => [
          'entityType' => 'organization',
        ],
      ];
    }
    $form['instrument_information']['instrument_parent_wrapper']['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
      '#default_value' => $this->getInstrument()->hasShortName,
    ];
    $form['instrument_information']['instrument_parent_wrapper']['instrument_informant'] = [
      '#type' => 'select',
      '#title' => $this->t('Informant'),
      '#options' => $informants,
      '#default_value' => $hasInformant,
    ];
    $form['instrument_information']['instrument_parent_wrapper']['instrument_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $hasLanguage,
    ];
    $form['instrument_information']['instrument_parent_wrapper']['instrument_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => isset($this->getInstrument()->hasVersion) && $this->getInstrument()->hasVersion !== null
        ? (
            ($this->getInstrument()->hasStatus === VSTOI::CURRENT || $this->getInstrument()->hasStatus === VSTOI::DEPRECATED)
              ? $this->getInstrument()->hasVersion + 1
              : $this->getInstrument()->hasVersion
          )
        : 1,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['instrument_information']['instrument_parent_wrapper']['instrument_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getInstrument()->comment,
    ];

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current instrument and its image.
    $instrument = $this->getInstrument();
    $instrument_uri = Utils::namespaceUri($this->getInstrumentUri());
    $instrument_image = $instrument->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($instrument_image) && stripos(trim($instrument_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($instrument_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($instrument_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $instrument_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['instrument_information']['instrument_image_type'] = [
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
    $form['instrument_information']['instrument_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $instrument_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="instrument_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['instrument_information']['instrument_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="instrument_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($instrument_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $instrument_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['instrument_information']['instrument_image_upload_wrapper']['instrument_image_upload'] = [
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
    $instrument_webdocument = $instrument->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($instrument_webdocument) && stripos(trim($instrument_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($instrument_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['instrument_information']['instrument_webdocument_type'] = [
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
    $form['instrument_information']['instrument_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $instrument_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="instrument_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['instrument_information']['instrument_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="instrument_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($instrument_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $instrument_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['instrument_information']['instrument_webdocument_upload_wrapper']['instrument_webdocument_upload'] = [
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

    // **************
    // CONTAINER AREA
    // **************
    $form['instrument_structure'] = [
      '#type' => 'details',
      '#title' => $this->t('Container Elements'),
      '#group' => 'information',
    ];

    # POPULATE DATA
    $uri=$this->getInstrument()->uri;
    // dpm($uri);
    $api = \Drupal::service('rep.api_connector');
    $container = $api->parseObjectResponse($api->getUri($uri),'getUri');
    if ($container == NULL) {

      // Give message to the user saying that there is no structure for current Simulator
      $form['instrument_structure']['no_structure_warning'] = [
        '#type' => 'item',
        '#value' => t('This Simulator has no Structure bellow!')
      ];

      return;
    }

    $form['instrument_structure']['scope'] = [
      '#type' => 'item',
      '#title' => t('<h4>Slots Elements of Container <font color="DarkGreen">' . $this->getInstrument()->label . '</font>, maintained by <font color="DarkGreen">' . $this->getInstrument()->hasSIRManagerEmail . '</font></h4>'),
      '#wrapper_attributes' => [
        'class' => 'mt-3'
      ],
    ];

    $this->setContainer($container);
    $containerUri = $this->getContainer()->uri;
    $slotElementsOutput = UTILS::buildSlotElements($containerUri, $api, 'table'); // or 'table'
    $form['instrument_structure']['slot_elements'] = $slotElementsOutput;


    // **************
    // REVIEWER AREA
    // **************
    if ($this->getInstrument()->hasReviewNote) {
      $form['instrument_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getInstrument()->hasReviewNote,
        '#attributes' => [
          'disabled' => 'disabled',
        ]
      ];
      $form['instrument_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => $this->getInstrument()->hasEditorEmail,
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
      if(strlen($form_state->getValue('instrument_name')) < 1) {
        $form_state->setErrorByName('instrument_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('instrument_abbreviation')) < 1) {
        $form_state->setErrorByName('instrument_abbreviation', $this->t('Please enter a valid abbreviation'));
      }
      if(strlen($form_state->getValue('instrument_language')) < 1) {
        $form_state->setErrorByName('instrument_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('instrument_version')) < 1) {
        $form_state->setErrorByName('instrument_version', $this->t('Please enter a valid version'));
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

    $api = \Drupal::service('rep.api_connector');

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getInstrument()->hasStatus === VSTOI::CURRENT || $this->getInstrument()->hasStatus === VSTOI::DEPRECATED) {

        $instrumentJson = '{"uri":"'.Utils::uriGen('instrument').'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($form_state->getValue('instrument_type')).'",'.
        '"hascoTypeUri":"'.VSTOI::INSTRUMENT.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"label":"'.$form_state->getValue('instrument_name').'",'.
        '"hasShortName":"'.$form_state->getValue('instrument_abbreviation').'",'.
        '"hasInformant":"'.$form_state->getValue('instrument_informant').'",'.
        '"hasLanguage":"'.$form_state->getValue('instrument_language').'",'.
        '"hasVersion":"'.$form_state->getValue('instrument_version').'",'.
        '"hasWebDocument":"",'.
        '"hasImageUri":"",' .
        '"comment":"'.$form_state->getValue('instrument_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        // ADD NEW INSTRUMENT VERSION
        $api->elementAdd('instrument', $instrumentJson);
        \Drupal::messenger()->addMessage(t("New Version instrument has been created successfully."));
      } else {

        // Determine the chosen document type.
        $doc_type = $form_state->getValue('instrument_webdocument_type');
        $instrument_webdocument = $this->getInstrument()->hasWebDocument;

        // If user selected URL, use the textfield value.
        if ($doc_type === 'url') {
          $instrument_webdocument = $form_state->getValue('instrument_webdocument_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($doc_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('instrument_webdocument_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'instrument', 1);
              // Now get the filename from the file entity.
              $instrument_webdocument = $file->getFilename();
            }
          }
        }

        // Determine the chosen image type.
        $image_type = $form_state->getValue('instrument_image_type');
        $instrument_image = $this->getInstrument()->hasImageUri;

        // If user selected URL, use the textfield value.
        if ($image_type === 'url') {
          $instrument_image = $form_state->getValue('instrument_image_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($image_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('instrument_image_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'instrument', 1);
              // Now get the filename from the file entity.
              $instrument_image = $file->getFilename();
            }
          }
        }

        // MUST PAY ATENTION TO CONTANINER ANS NEXT/PREVIOUS/ETC...

        $instrumentJson = '{"uri":"'.$this->getInstrumentUri().'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($form_state->getValue('instrument_type')).'",'.
        '"hascoTypeUri":"'.VSTOI::INSTRUMENT.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"label":"'.$form_state->getValue('instrument_name').'",'.
        '"hasShortName":"'.$form_state->getValue('instrument_abbreviation').'",'.
        '"hasInformant":"'.$form_state->getValue('instrument_informant').'",'.
        '"hasLanguage":"'.$form_state->getValue('instrument_language').'",'.
        '"hasVersion":"'.$form_state->getValue('instrument_version').'",'.
        '"hasWebDocument":"' . $instrument_webdocument . '",' .
        '"hasImageUri":"' . $instrument_image . '",' .
        '"comment":"'.$form_state->getValue('instrument_description').'",'.

        '"hasFirst":"'.$this->getInstrument()->hasFirst.'",'.
        '"belongsTo":"'.$this->getInstrument()->belongsTo.'",'.
        '"hasNext":"'.$this->getInstrument()->hasNext.'",'.
        '"hasPrevious":"'.$this->getInstrument()->hasPrevious.'",'.
        '"hasPriority":"'.$this->getInstrument()->hasPriority.'",'.
        '"hasMakerUri":"'.Utils::uriFromAutocomplete($form_state->getValue('instrument_maker')).'",'.
        // '"annotations":"' . ($this->getInstrument()->annotations ?? null).'",'.

        '"hasReviewNote":"'.$this->getInstrument()->hasReviewNote.'",'.
        '"hasEditorEmail":"'.$this->getInstrument()->hasEditorEmail.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        // dpm($instrumentJson);
        // return false;

        // UPDATE BY DELETING AND CREATING CURRENT INSTRUMENT
        $api->elementDel('instrument', $this->getInstrumentUri());
        $api->elementAdd('instrument', $instrumentJson);

        // UPLOAD IMAGE TO API
        if ($image_type === 'upload' && $instrument_image !== $this->getInstrument()->hasImageUri) {
          $fids = $form_state->getValue('instrument_image_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getInstrumentUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded Image FAILED to be submited to API."));
          }
        }

        // UPLOAD DOCUMENT TO API
        if ($doc_type === 'upload' && $instrument_webdocument !== $this->getInstrument()->hasWebDocument) {
          $fids = $form_state->getValue('instrument_webdocument_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getInstrumentUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded WebDocument FAILED to be submited to API."));
          }
        }

        \Drupal::messenger()->addMessage(t("instrument has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Instrument: ".$e->getMessage()));
      self::backUrl();
      return;
    }

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
