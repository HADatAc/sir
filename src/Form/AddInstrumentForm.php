<?php

namespace Drupal\sir\Form;

use Abraham\TwitterOAuth\Util;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\file\Entity\File;

class AddInstrumentForm extends FormBase {

  protected $instrumentUri;

  public function setInstrumenUri() {
    $this->instrumentUri = Utils::uriGen('instrument');
  }

  public function getInstrumenUri() {
    return $this->instrumentUri;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_instrument_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Check if the instrument URI already exists in the form state.
    // If not, generate a new URI and store it in the form state.
    if (!$form_state->has('instrument_uri')) {
      $this->setInstrumenUri();
      $form_state->set('instrument_uri', $this->getInstrumenUri());
    }
    else {
      // Retrieve the persisted URI from form state.
      $this->instrumentUri = $form_state->get('instrument_uri');
    }

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $informants = $tables->getInformants();

    //SELECT ONE
    if ($languages)
      $languages = ['' => $this->t('Select language please')] + $languages;
    if ($informants)
      $informants = ['' => $this->t('Select Informant please')] + $informants;

    $form['instrument_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Parent Type'),
        '#name' => 'instrument_type',
        '#default_value' => '',
        '#id' => 'instrument_type',
        '#parents' => ['instrument_type'],
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
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
    ];
    $form['instrument_informant'] = [
      '#type' => 'select',
      '#title' => $this->t('Informant'),
      '#options' => $informants,
      '#default_value' => Constant::DEFAULT_INFORMANT,
    ];
    $form['instrument_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['instrument_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['instrument_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    // Add a hidden field to persist the instrument URI between form rebuilds.
    $form['instrument_uri'] = [
      '#type' => 'hidden',
      '#value' => $this->instrumentUri,
    ];

    // Add a select box to choose between URL and Upload.
    $form['instrument_image_type'] = [
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
    $form['instrument_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="instrument_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted instrument URI for file uploads)
    $modUri = (explode(":/", utils::namespaceUri($this->instrumentUri)))[1];
    $form['instrument_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="instrument_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['instrument_image_upload_wrapper']['instrument_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'], // Adjust allowed extensions as needed.
        'file_validate_size' => [2097152],
      ],
    ];

    // Add a select box to choose between URL and Upload.
    $form['instrument_webdocument_type'] = [
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
    $form['instrument_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="instrument_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted instrument URI for file uploads)
    $form['instrument_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="instrument_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['instrument_webdocument_upload_wrapper']['instrument_webdocument_upload'] = [
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

    if ($button_name != 'back') {
      if(empty($form_state->getValue('instrument_type'))) {
        $form_state->setErrorByName('instrument_type', $this->t('Please select a valid Instrument Parent type'));
      }
      if(strlen($form_state->getValue('instrument_name')) < 1) {
        $form_state->setErrorByName('instrument_name', $this->t('Please enter a valid Name'));
      }
      if(strlen($form_state->getValue('instrument_abbreviation')) < 1) {
        $form_state->setErrorByName('instrument_abbreviation', $this->t('Please enter a valid Abbreviation'));
      }
      if(strlen($form_state->getValue('instrument_language')) < 1) {
        $form_state->setErrorByName('instrument_language', $this->t('Please enter a valid Language'));
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

    try{

      // Get the current user email and generate a new instrument URI.
      $useremail = \Drupal::currentUser()->getEmail();
      // $newInstrumentUri = Utils::uriGen('instrument');
      $newInstrumentUri = $form_state->getValue('instrument_uri');

      // Determine the chosen document type.
      $doc_type = $form_state->getValue('instrument_webdocument_type');
      $instrument_webdocument = '';

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
      $instrument_image = '';

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

      // Build the JSON string with the computed web document value.
      $instrumentJson = '{"uri":"' . $newInstrumentUri . '",' .
        '"superUri":"' . Utils::uriFromAutocomplete($form_state->getValue('instrument_type')) . '",' .
        '"hascoTypeUri":"' . VSTOI::INSTRUMENT . '",' .
        '"hasStatus":"' . VSTOI::DRAFT . '",' .
        '"label":"' . $form_state->getValue('instrument_name') . '",' .
        '"hasShortName":"' . $form_state->getValue('instrument_abbreviation') . '",' .
        '"hasInformant":"' . $form_state->getValue('instrument_informant') . '",' .
        '"hasLanguage":"' . $form_state->getValue('instrument_language') . '",' .
        '"hasVersion":"' . $form_state->getValue('instrument_version') . '",' .
        '"hasWebDocument":"' . $instrument_webdocument . '",' .
        '"hasImageUri":"' . $instrument_image . '",' .
        '"comment":"' . $form_state->getValue('instrument_description') . '",' .
        '"hasSIRManagerEmail":"' . $useremail . '"}';

      // Call the API connector service with the JSON.
      $api = \Drupal::service('rep.api_connector');
      $api->instrumentAdd($instrumentJson);

      \Drupal::messenger()->addMessage($this->t("Instrument has been added successfully."));

      // UPLOAD IMAGE TO API
      if ($image_type === 'upload') {
        $fids = $form_state->getValue('instrument_image_upload');
        $msg = $api->parseObjectResponse($api->uploadFile($newInstrumentUri, reset($fids)), 'uploadFile');
        if ($msg == NULL) {
          \Drupal::messenger()->addError(t("The Uploaded Image FAILED to be submited to API."));
        }
      }

      if ($doc_type === 'upload') {
        $fids = $form_state->getValue('instrument_webdocument_upload');
        $msg = $api->parseObjectResponse($api->uploadFile($newInstrumentUri, reset($fids)), 'uploadFile');
        if ($msg == NULL) {
          \Drupal::messenger()->addError(t("The Uploaded WebDocument FAILED to be submited to API."));
        }
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding instrument: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_instrument');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }



}
