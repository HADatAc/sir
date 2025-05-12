<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\file\Entity\File;

class AddCodebookForm extends FormBase {

  protected $codebookUri;

  public function setInstrumenUri() {
    $this->codebookUri = Utils::uriGen('codebook');
  }

  public function getInstrumenUri() {
    return $this->codebookUri;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_codebook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Check if the codebook URI already exists in the form state.
    // If not, generate a new URI and store it in the form state.
    if (!$form_state->has('codebook_uri')) {
      $this->setInstrumenUri();
      $form_state->set('codebook_uri', $this->getInstrumenUri());
    }
    else {
      // Retrieve the persisted URI from form state.
      $this->codebookUri = $form_state->get('codebook_uri');
    }

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $form['codebook_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['codebook_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['codebook_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['codebook_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    // Add a hidden field to persist the codebook URI between form rebuilds.
    $form['codebook_uri'] = [
      '#type' => 'hidden',
      '#value' => $this->codebookUri,
    ];

    // Add a select box to choose between URL and Upload.
    $form['codebook_image_type'] = [
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
    $form['codebook_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="codebook_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted codebook URI for file uploads)
    $modUri = (explode(":/", utils::namespaceUri($this->codebookUri)))[1];
    $form['codebook_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="codebook_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['codebook_image_upload_wrapper']['codebook_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'], // Adjust allowed extensions as needed.
        'file_validate_size' => [2097152],
      ],
    ];

    // Add a select box to choose between URL and Upload.
    $form['codebook_webdocument_type'] = [
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
    $form['codebook_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="codebook_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted codebook URI for file uploads)
    $form['codebook_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="codebook_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['codebook_webdocument_upload_wrapper']['codebook_webdocument_upload'] = [
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

    if ($button_name === 'save') {
      if(strlen($form_state->getValue('codebook_name')) < 1) {
        $form_state->setErrorByName('codebook_name', $this->t('Please enter a valid name for the Codebook'));
      }
      if(strlen($form_state->getValue('codebook_description')) < 1) {
        $form_state->setErrorByName('codebook_description', $this->t('Please enter a valid description of the Codebook'));
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

    try {
      $uemail = \Drupal::currentUser()->getEmail();

      // $newCodebookUri = Utils::uriGen('codebook');
      $newCodebookUri = $form_state->getValue('codebook_uri');

      // Determine the chosen document type.
      $doc_type = $form_state->getValue('codebook_webdocument_type');
      $codebook_webdocument = '';

      // If user selected URL, use the textfield value.
      if ($doc_type === 'url') {
        $codebook_webdocument = $form_state->getValue('codebook_webdocument_url');
      }
      // If user selected Upload, load the file entity and get its filename.
      elseif ($doc_type === 'upload') {
        // Get the file IDs from the managed_file element.
        $fids = $form_state->getValue('codebook_webdocument_upload');
        if (!empty($fids)) {
          // Load the first file (file ID is returned, e.g. "374").
          $file = File::load(reset($fids));
          if ($file) {
            // Mark the file as permanent and save it.
            $file->setPermanent();
            $file->save();
            // Optionally register file usage to prevent cleanup.
            \Drupal::service('file.usage')->add($file, 'sir', 'codebook', 1);
            // Now get the filename from the file entity.
            $codebook_webdocument = $file->getFilename();
          }
        }
      }

      // Determine the chosen image type.
      $image_type = $form_state->getValue('codebook_image_type');
      $codebook_image = '';

      // If user selected URL, use the textfield value.
      if ($image_type === 'url') {
        $codebook_image = $form_state->getValue('codebook_image_url');
      }
      // If user selected Upload, load the file entity and get its filename.
      elseif ($image_type === 'upload') {
        // Get the file IDs from the managed_file element.
        $fids = $form_state->getValue('codebook_image_upload');
        if (!empty($fids)) {
          // Load the first file (file ID is returned, e.g. "374").
          $file = File::load(reset($fids));
          if ($file) {
            // Mark the file as permanent and save it.
            $file->setPermanent();
            $file->save();
            // Optionally register file usage to prevent cleanup.
            \Drupal::service('file.usage')->add($file, 'sir', 'codebook', 1);
            // Now get the filename from the file entity.
            $codebook_image = $file->getFilename();
          }
        }
      }

      $codebookJSON = '{"uri":"'.$newCodebookUri.'",' .
        '"typeUri":"'.VSTOI::CODEBOOK.'",'.
        '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"label":"' . $form_state->getValue('codebook_name') . '",' .
        '"hasLanguage":"' . $form_state->getValue('codebook_language') . '",' .
        '"hasVersion":"' . $form_state->getValue('codebook_version') . '",' .
        '"comment":"' . $form_state->getValue('codebook_description') . '",' .
        '"hasWebDocument":"' . $codebook_webdocument . '",' .
        '"hasImageUri":"' . $codebook_image . '",' .
        '"hasSIRManagerEmail":"' . $uemail . '"}';

      $api = \Drupal::service('rep.api_connector');
      $api->elementAdd('codebook', $codebookJSON);

      // UPLOAD IMAGE TO API
      if ($image_type === 'upload') {
        $fids = $form_state->getValue('codebook_image_upload');
        $msg = $api->parseObjectResponse($api->uploadFile($newCodebookUri, reset($fids)), 'uploadFile');
        if ($msg == NULL) {
          \Drupal::messenger()->addError(t("The Uploaded Image FAILED to be submited to API."));
        }
      }
      // UPLOAD DOCUMENT TO API
      if ($doc_type === 'upload') {
        $fids = $form_state->getValue('codebook_webdocument_upload');
        $msg = $api->parseObjectResponse($api->uploadFile($newCodebookUri, reset($fids)), 'uploadFile');
        if ($msg == NULL) {
          \Drupal::messenger()->addError(t("The Uploaded Document FAILED to be submited to API."));
        }
      }

      \Drupal::messenger()->addMessage(t("Codebook has been added successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addMessage(t("An error occurred while adding an codebook: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_codebook');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
