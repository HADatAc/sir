<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\REPGUI;

class ReviewInstrumentForm extends FormBase {

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
    return 'review_instrument_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {

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
      '#wrapper_attributes' => [
        'style' => 'max-width: 1280px;margin-bottom:15px!important;',
      ]
    ];

    $form['instrument_information']['instrument_uri_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['mt-3'],
      ],
    ];

    $form['instrument_information']['instrument_uri_container']['instrument_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="' . $root_url . REPGUI::DESCRIBE_PAGE . base64_encode($this->getInstrumentUri()) . '">' . $this->getInstrumentUri() . '</a>'),
    ];

    $form['instrument_information']['instrument_parent_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'align-items-center', 'gap-2', 'mt-2'],
        'style' => 'width: 100%;margin-bottom:0!important;',
      ],
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
        '#attributes' => [
          'class' => ['open-tree-modal', 'flex-grow-1'],
          'style' => "min-width: 0;",
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
        '#disabled' => TRUE,
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];

    // Construção da URL
    $elementUri = Utils::namespaceUri($this->getInstrument()->superUri);
    $elementUriEncoded = base64_encode($elementUri);
    $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

    // Botão para abrir nova janela
    $form['instrument_information']['instrument_parent_wrapper']['instrument_parent_wrapper_button'] = [
      '#type' => 'markup',
      '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-primary text-nowrap" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
    ];

    $form['instrument_information']['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getInstrument()->label,
      '#disabled' => TRUE,
    ];
    $form['instrument_information']['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
      '#default_value' => $this->getInstrument()->hasShortName,
      '#disabled' => TRUE,
    ];
    $form['instrument_information']['instrument_informant'] = [
      '#type' => 'select',
      '#title' => $this->t('Informant'),
      '#options' => $informants,
      '#default_value' => $hasInformant,
      '#disabled' => TRUE,
    ];
    $form['instrument_information']['instrument_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $hasLanguage,
      '#disabled' => TRUE,
    ];
    $form['instrument_information']['instrument_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getInstrument()->hasStatus === VSTOI::CURRENT || $this->getInstrument()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getInstrument()->hasVersion + 1 : $this->getInstrument()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['instrument_information']['instrument_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getInstrument()->comment,
      '#disabled' => TRUE,
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
      '#disabled' => TRUE,
    ];

    $form['instrument_information']['instrument_image_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'align-items-center', 'gap-2', 'mt-2', 'w-100'],
        'style' => 'width: 100%;margin-bottom:0!important;',
      ],
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['instrument_information']['instrument_image_wrapper']['instrument_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $instrument_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="instrument_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    if ($image_type === 'url')
      $form['instrument_information']['instrument_image_wrapper']['instrument_image_wrapper_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $instrument_image . '" target="_blank" class="btn btn-primary text-nowrap" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('View Image') . '</a>',
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
      '#disabled' => TRUE,
      '#default_value' => $webdocument_type,
    ];

    $form['instrument_information']['instrument_webdocument_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
      '#attributes' => [
        'class' => ['d-flex', 'align-items-center', 'gap-2', 'mt-2'],
        'style' => 'width: 100%;margin-bottom:0!important;',
      ],
    ];

    // Textfield for URL mode (only visible when type = 'url').
    $form['instrument_information']['instrument_webdocument_wrapper']['instrument_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $instrument_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="instrument_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    if ($webdocument_type === 'url')
      $form['instrument_information']['instrument_webdocument_wrapper']['instrument_webdocument_wrapper_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $instrument_webdocument . '" target="_blank" class="btn btn-primary text-nowrap" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Visit Web Document') . '</a>',
      ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['instrument_information']['instrument_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/webdoc',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'],
      ],
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_fid ? [$existing_fid] : NULL,
    ];

    $form['instrument_information']['instrument_hasSIRManagerEmail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getInstrument()->hasSIRManagerEmail,
      '#disabled' => TRUE,
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
    $form['instrument_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getInstrument()->hasReviewNote,
    ];

    $form['instrument_haseditoremail'] = [
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
      '#title' => t('<br><br>'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
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
    $api = \Drupal::service('rep.api_connector');
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    if ($button_name === 'review_reject' && strlen($form_state->getValue('instrument_hasreviewnote')) === 0) {
      \Drupal::messenger()->addError(t("To reject you must type a Review Note!"));
      return false;
    }

    try {

      //APROVE
      if ($button_name !== 'review_reject') {

        // Recursive APROVE os Instrument and Elements
        $api->reviewRecursive($this->getInstrumentUri(), VSTOI::CURRENT);

        \Drupal::messenger()->addMessage(t("Instrument has been APPROVED successfully."));
        self::backUrl();
        return;

      // REJECT
      } else {

        $useremail = \Drupal::currentUser()->getEmail();

        // WE MUST ADD REVIEW NOTES TO INSTRUMENT
        $instrumentJson = '{"uri":"'.$this->getInstrumentUri().'",'.
        '"superUri":"'.$this->getInstrument()->superUri.'",'.
        '"hascoTypeUri":"'.VSTOI::INSTRUMENT.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"label":"'.$this->getInstrument()->label.'",'.
        '"hasShortName":"'.$this->getInstrument()->hasShortName.'",'.
        '"hasInformant":"'.$this->getInstrument()->hasInformant.'",'.
        '"hasLanguage":"'.$this->getInstrument()->hasLanguage.'",'.
        '"hasVersion":"'.$this->getInstrument()->hasVersion.'",'.
        '"hasWebDocument":"' . $this->getInstrument()->hasWebDocument . '",' .
        '"hasImageUri":"' . $this->getInstrument()->hasImageUri . '",' .
        '"comment":"'.$this->getInstrument()->comment.'",'.
        '"hasReviewNote":"'.$form_state->getValue('instrument_hasreviewnote').'",'.
        '"hasEditorEmail":"'.$useremail.'",'.

        '"hasFirst":"'.$this->getInstrument()->hasFirst.'",'.
        '"belongsTo":"'.$this->getInstrument()->belongsTo.'",'.
        '"hasNext":"'.$this->getInstrument()->hasNext.'",'.
        '"hasPrevious":"'.$this->getInstrument()->hasPrevious.'",'.
        '"hasPriority":"'.$this->getInstrument()->hasPriority.'",'.
        // '"annotations":"' . ($this->getInstrument()->annotations ?? null).'",'.

        '"hasSIRManagerEmail":"'.$this->getInstrument()->hasSIRManagerEmail.'"}';

        // Must Delete OLD Instrument and Create NEW Instrument
        $api->elementDel('instrument', $this->getInstrumentUri());
        $api->elementAdd('instrument', $instrumentJson);

        // dpm($instrumentJson);

        // Recursive REJECT Instrument Elements recursivelly
        // Instrument must be made diferently because review note field
        $api->reviewRecursive($this->getInstrumentUri(), VSTOI::DRAFT);

        \Drupal::messenger()->addError(t("Instrument has been REJECTED."));
          self::backUrl();
          return;

      }

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
