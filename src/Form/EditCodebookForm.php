<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\REPGUI;
use Drupal\file\Entity\File;
use Drupal\Core\Render\Markup;

class EditCodebookForm extends FormBase {

  protected $codebookUri;

  protected $codebook;

  public function getCodebookUri() {
    return $this->codebookUri;
  }

  public function setCodebookUri($uri) {
    return $this->codebookUri = $uri;
  }

  public function getCodebook() {
    return $this->codebook;
  }

  public function setCodebook($cb) {
    return $this->codebook = $cb;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_codebook_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebookuri = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    $uri=$codebookuri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setCodebookUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getCodebookUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setCodebook($obj->body);
      #dpm($this->getCodebook());
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Codebook."));
      self::backUrl();
      return;
    }
    $form['codebook_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getCodebookUri()).'">'.$this->getCodebookUri().'</a>'),
    ];
    $form['codebook_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getCodebook()->label,
    ];
    $form['codebook_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getCodebook()->hasLanguage,
    ];
    $form['codebook_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getCodebook()->hasStatus === VSTOI::CURRENT || $this->getCodebook()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getCodebook()->hasVersion + 1 : $this->getCodebook()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['codebook_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getCodebook()->comment,
    ];

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current codebook and its image.
    $codebook = $this->getCodebook();
    $codebook_uri = Utils::namespaceUri($this->getCodebookUri());
    $codebook_image = $codebook->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($codebook_image) && stripos(trim($codebook_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($codebook_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($codebook_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $codebook_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['codebook_information']['codebook_image_type'] = [
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
    $form['codebook_information']['codebook_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $codebook_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="codebook_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['codebook_information']['codebook_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="codebook_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($codebook_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $codebook_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['codebook_information']['codebook_image_upload_wrapper']['codebook_image_upload'] = [
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
    $codebook_webdocument = $codebook->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($codebook_webdocument) && stripos(trim($codebook_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($codebook_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['codebook_information']['codebook_webdocument_type'] = [
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
    $form['codebook_information']['codebook_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $codebook_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="codebook_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['codebook_information']['codebook_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="codebook_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($codebook_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $codebook_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['codebook_information']['codebook_webdocument_upload_wrapper']['codebook_webdocument_upload'] = [
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

    if ($this->getCodebook()->hasReviewNote !== NULL && $this->getCodebook()->hasSatus !== null) {
      $form['responseoption_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getCodebook()->hasReviewNote,
        '#attributes' => [
          'disabled' => 'disabled',
        ]
      ];
      $form['responseoption_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => $this->getCodebook()->hasEditorEmail,
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
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('codebook_name')) < 1) {
        $form_state->setErrorByName('codebook_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('codebook_language')) < 1) {
        $form_state->setErrorByName('codebook_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('codebook_version')) < 1) {
        $form_state->setErrorByName('codebook_version', $this->t('Please enter a valid version'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{
      $useremail = \Drupal::currentUser()->getEmail();
      $api = \Drupal::service('rep.api_connector');

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getCodebook()->hasStatus === VSTOI::CURRENT || $this->getCodebook()->hasStatus === VSTOI::DEPRECATED) {

        $newCodeBookUri = Utils::uriGen('codebook');
        $codebookJson = '{"uri":"'. $newCodeBookUri .'",'.
          '"typeUri":"'.VSTOI::CODEBOOK.'",'.
          '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"label":"'.$form_state->getValue('codebook_name').'",'.
          '"hasLanguage":"'.$form_state->getValue('codebook_language').'",'.
          '"hasVersion":"'.$form_state->getValue('codebook_version').'",'.
          '"comment":"'.$form_state->getValue('codebook_description').'",'.
          '"hasWebDocument":"",'.
          '"hasImageUri":"",' .
          '"hasReviewNote":"'.($this->getCodebook()->hasSatus !== null ? $this->getCodebook()->hasReviewNote : '').'",'.
          '"hasEditorEmail":"'.($this->getCodebook()->hasSatus !== null ? $this->getCodebook()->hasEditorEmail : '').'",'.
          '"wasDerivedFrom":"'.$this->getCodebook()->uri.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->elementAdd('codebook', $codebookJson);

        // ADD SLOTS AND RO TO V++ CODEBOOK
        if (!empty($this->getCodeBook()->codebookSlots)){

          //MUST CREATE SAME NUMBER SLOTS ON CLONE
          $api->codebookSlotAdd($newCodeBookUri,count($this->getCodebook()->codebookSlots));

          //LOOP TO ASSIGN RO TO CB
          $slot_list = $api->codebookSlotList($newCodeBookUri);
          $obj = json_decode($slot_list);
          $slots = [];
          if ($obj->isSuccessful) {
            $slots = $obj->body;
            //dpm($slots);
          }
          $count = 1;
          foreach ($slots as $slot) {
            //GET RO->URI AND ATTACH TO SLOT
            if ($this->getCodebook()->codebookSlots[$count-1]->hasPriority === $slot->hasPriority) {
              $roURI = $this->getCodebook()->codebookSlots[$count-1]->responseOption->uri;
            }
            $api->responseOptionAttach($roURI,$slot->uri);
            $count++;
          }
        }

        \Drupal::messenger()->addMessage(t("New Version CodeBook has been created successfully."));

      } else {

        // Determine the chosen document type.
        $doc_type = $form_state->getValue('codebook_webdocument_type');
        $codebook_webdocument = $this->getCodeBook()->hasWebDocument;

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
        $codebook_image = $this->getCodebook()->hasImageUri;

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

        $codebookJson = '{"uri":"'. $this->getCodebook()->uri .'",'.
          '"typeUri":"'.VSTOI::CODEBOOK.'",'.
          '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"label":"'.$form_state->getValue('codebook_name').'",'.
          '"hasLanguage":"'.$form_state->getValue('codebook_language').'",'.
          '"hasVersion":"'.$form_state->getValue('codebook_version').'",'.
          '"comment":"'.$form_state->getValue('codebook_description').'",'.
          '"hasWebDocument":"' . $codebook_webdocument . '",' .
          '"hasImageUri":"' . $codebook_image . '",' .
          '"hasReviewNote":"'.$this->getCodebook()->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$this->getCodebook()->hasEditorEmail.'",'.
          '"wasDerivedFrom":"'.$this->getCodebook()->wasDerivedFrom.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

          // UPDATE BY DELETING AND CREATING
          $api->elementDel('codebook', $this->getCodebook()->uri);
          $api->elementAdd('codebook', $codebookJson);

          // UPLOAD IMAGE TO API
          if ($image_type === 'upload' && $codebook_image !== $this->getCodeBook()->hasImageUri) {
            $fids = $form_state->getValue('codebook_image_upload');
            $msg = $api->parseObjectResponse($api->uploadFile($this->getCodeBookUri(), reset($fids)), 'uploadFile');
            if ($msg == NULL) {
              \Drupal::messenger()->addError(t("The Uploaded Image FAILED to be submited to API."));
            }
          }

          // UPLOAD DOCUMENT TO API
          if ($doc_type === 'upload' && $codebook_webdocument !== $this->getCodeBook()->hasWebDocument) {
            $fids = $form_state->getValue('codebook_webdocument_upload');
            $msg = $api->parseObjectResponse($api->uploadFile($this->getCodeBookUri(), reset($fids)), 'uploadFile');
            if ($msg == NULL) {
              \Drupal::messenger()->addError(t("The Uploaded WebDocument FAILED to be submited to API."));
            }
          }

          \Drupal::messenger()->addMessage(t("Codebook has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating Codebook: ".$e->getMessage()));
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
