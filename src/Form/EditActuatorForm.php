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

class EditActuatorForm extends FormBase {

  protected $actuatorUri;

  protected $actuator;

  protected $sourceActuator;

  public function getActuatorUri() {
    return $this->actuatorUri;
  }

  public function setActuatorUri($uri) {
    return $this->actuatorUri = $uri;
  }

  public function getActuator() {
    return $this->actuator;
  }

  public function setActuator($obj) {
    return $this->actuator = $obj;
  }

  public function getSourceActuator() {
    return $this->sourceActuator;
  }

  public function setSourceActuator($obj) {
    return $this->sourceActuator = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_actuator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $actuatoruri = NULL) {

    // Does the repo have a social network?
    $socialEnabled = \Drupal::config('rep.settings')->get('social_conf');

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';


    $uri=$actuatoruri;
    $uri_decode=base64_decode($uri);
    $this->setActuatorUri($uri_decode);

    $sourceContent = '';
    $stemLabel = '';
    $codebookLabel = '';
    $this->setActuator($this->retrieveActuator($this->getActuatorUri()));
    if ($this->getActuator() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Actuator."));
      self::backUrl();
      return;
    } else {
      if ($this->getActuator()->actuatorStem != NULL) {
        $stemLabel = $this->getActuator()->actuatorStem->hasContent . ' [' . $this->getActuator()->actuatorStem->uri . ']';
      }
      if ($this->getActuator()->codebook != NULL) {
        $codebookLabel = $this->getActuator()->codebook->label . ' [' . $this->getActuator()->codebook->uri . ']';
      }
    }

    // dpm($this->getActuator());
    $form['actuator_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getActuatorUri()).'">'.$this->getActuatorUri().'</a>'),
    ];
    $form['actuator_stem'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => \Drupal::moduleHandler()->moduleExists('pmsr') ?
          $this->t('Simulation Technique Stem') :
          $this->t('Actuator Stem'),
        '#name' => 'actuator_stem',
        '#default_value' => Utils::fieldToAutocomplete($this->getActuator()->typeUri, $this->getActuator()->actuatorStem->label),
        '#id' => 'actuator_stem',
        '#parents' => ['actuator_stem'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'actuatorstem',
          ], ['query' => ['field_id' => 'actuator_stem']])->toString(),
          'data-field-id' => 'actuator_stem',
          'data-elementtype' => 'actuatorstem',
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['actuator_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.actuator_codebook_autocomplete',
    ];
    if ($socialEnabled) {
      $form['actuator_maker'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Maker'),
        '#default_value' => isset($this->getActuator()->hasMakerUri) ?
                              Utils::fieldToAutocomplete($this->getActuator()->hasMakerUri, $this->getActuator()->hasMaker->label) : '',
        // '#required' => TRUE,
        '#autocomplete_route_name'       => 'rep.social_autocomplete',
        '#autocomplete_route_parameters' => [
          'entityType' => 'organization',
        ],
      ];
    }
    $form['actuator_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => isset($this->getActuator()->hasVersion) && $this->getActuator()->hasVersion !== null
        ? (($this->getActuator()->hasStatus === VSTOI::CURRENT || $this->getActuator()->hasStatus === VSTOI::DEPRECATED)
            ? $this->getActuator()->hasVersion + 1
            : $this->getActuator()->hasVersion)
        : 1,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    if (isset($this->getActuator()->isAttributeOf)) {
      $api = \Drupal::service('rep.api_connector');
      $attributOf = $api->parseObjectResponse($api->getUri($this->getActuator()->isAttributeOf),'getUri');
    }
    $form['actuator_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'actuator_isAttributeOf',
        '#default_value' => (isset($attributOf) ? $attributOf->label . ' [' . $this->getActuator()->isAttributeOf . ']' : ''),
        '#id' => 'actuator_isAttributeOf',
        '#parents' => ['actuator_isAttributeOf'],
        '#attributes' => [
          'class' => ['open-tree-modal'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'actuatorattribute',
          ], ['query' => ['field_id' => 'actuator_isAttributeOf']])->toString(),
          'data-field-id' => 'actuator_isAttributeOf',
          'data-elementtype' => 'actuatorattribute',
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
    // Retrieve the current actuator and its image.
    $actuator = $this->getActuator();
    $actuator_uri = Utils::namespaceUri($this->getActuatorUri());
    $actuator_image = $actuator->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($actuator_image) && stripos(trim($actuator_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($actuator_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($actuator_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $actuator_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['actuator_information']['actuator_image_type'] = [
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
    $form['actuator_information']['actuator_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $actuator_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="actuator_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['actuator_information']['actuator_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="actuator_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($actuator_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $actuator_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['actuator_information']['actuator_image_upload_wrapper']['actuator_image_upload'] = [
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
    $actuator_webdocument = $actuator->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($actuator_webdocument) && stripos(trim($actuator_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($actuator_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['actuator_information']['actuator_webdocument_type'] = [
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
    $form['actuator_information']['actuator_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $actuator_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="actuator_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['actuator_information']['actuator_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="actuator_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($actuator_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $actuator_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['actuator_information']['actuator_webdocument_upload_wrapper']['actuator_webdocument_upload'] = [
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

    if ($this->getActuator()->hasReviewNote !== NULL && $this->getActuator()->hasSatus !== null) {
      $form['actuator_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getActuator()->hasReviewNote,
        '#attributes' => [
          'disabled' => 'disabled',
        ]
      ];
      $form['actuator_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => $this->getActuator()->hasEditorEmail,
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
      if(strlen($form_state->getValue('actuator_stem')) < 1) {
        $form_state->setErrorByName('actuator_stem', $this->t('Please enter a valid actuator stem'));
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
      $rawresponse = $api->getUri(Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')));
      $obj = json_decode($rawresponse);
      $result = $obj->body;

      $label = "";
      if ($result->hasContent !== NULL) {
        $label .= $result->hasContent;
      } else {
        $label .= $result->label;
      }

      if ($form_state->getValue('actuator_codebook') !== NULL && $form_state->getValue('actuator_codebook') != '') {
        $codebook = Utils::uriFromAutocomplete($form_state->getValue('actuator_codebook'));
        $rawresponseCB = $api->getUri($codebook);
        $objCB = json_decode($rawresponseCB);
        $resultCB = $objCB->body;
        $label .= '  -- CB:'.$resultCB->label;
      } else {
        $label = $result->label . '  -- CB:EMPTY';
      }

      $hasCodebook = '';
      if ($form_state->getValue('actuator_codebook') != NULL && $form_state->getValue('actuator_codebook') != '') {
        $hasCodebook = Utils::uriFromAutocomplete($form_state->getValue('actuator_codebook'));
      }

      // CHECK if Status is CURRENT OR DEPRECATED FOR NEW CREATION
      if ($this->getActuator()->hasStatus === VSTOI::CURRENT || $this->getActuator()->hasStatus === VSTOI::DEPRECATED) {

        $newActuatorUri = Utils::uriGen('actuator');
        $actuatorJson = '{"uri":"'.$newActuatorUri.'",'.
        '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
        '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
        '"hasActuatorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
        '"hasCodebook":"'.$hasCodebook.'",'.
        '"hasContent":"'.$label.'",'.
        '"hasSIRManagerEmail":"'.$useremail.'",'.
        '"label":"'.$label.'",'.
        '"hasWebDocument":"",'.
        '"hasImageUri":"",' .
        '"hasVersion":"'.$form_state->getValue('actuator_version').'",'.
        '"isAttributeOf":"'.$form_state->getValue('actuator_isAttributeOf').'",'.
        '"wasDerivedFrom":"'.$this->getActuator()->uri.'",'.
        '"hasReviewNote":"'.($this->getActuator()->hasSatus !== null ? $this->getActuator()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getActuator()->hasSatus !== null ? $this->getActuator()->hasEditorEmail : '').'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'"}';

        $api->elementAdd('actuator',$actuatorJson);
        \Drupal::messenger()->addMessage(t("New Version actuator has been created successfully."));

      } else {

        // Determine the chosen document type.
        $doc_type = $form_state->getValue('actuator_webdocument_type');
        $actuator_webdocument = $this->getActuator()->hasWebDocument;

        // If user selected URL, use the textfield value.
        if ($doc_type === 'url') {
          $actuator_webdocument = $form_state->getValue('actuator_webdocument_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($doc_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('actuator_webdocument_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'actuator', 1);
              // Now get the filename from the file entity.
              $actuator_webdocument = $file->getFilename();
            }
          }
        }

        // Determine the chosen image type.
        $image_type = $form_state->getValue('actuator_image_type');
        $actuator_image = $this->getActuator()->hasImageUri;

        // If user selected URL, use the textfield value.
        if ($image_type === 'url') {
          $actuator_image = $form_state->getValue('actuator_image_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($image_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('actuator_image_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'actuator', 1);
              // Now get the filename from the file entity.
              $actuator_image = $file->getFilename();
            }
          }
        }

        $actuatorJson = '{"uri":"'.$this->getActuator()->uri.'",'.
          '"typeUri":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
          '"hasActuatorStem":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_stem')).'",'.
          '"hasCodebook":"'.$hasCodebook.'",'.
          '"hasContent":"'.$label.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'",'.
          '"label":"'.$label.'",'.
          '"hasWebDocument":"' . $actuator_webdocument . '",' .
          '"hasImageUri":"' . $actuator_image . '",' .
          '"hasVersion":"'.$form_state->getValue('actuator_version').'",'.
          '"isAttributeOf":"'.$form_state->getValue('actuator_isAttributeOf').'",'.
          '"wasDerivedFrom":"'.$this->getActuator()->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$this->getActuator()->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$this->getActuator()->hasEditorEmail.'",'.
          '"hasMakerUri":"'.Utils::uriFromAutocomplete($form_state->getValue('actuator_maker')).'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        $api->elementDel('actuator',$this->getActuatorUri());
        $api->elementAdd('actuator',$actuatorJson);

        // UPLOAD IMAGE TO API
        if ($image_type === 'upload' && $actuator_image !== $this->getActuator()->hasImageUri) {
          $fids = $form_state->getValue('actuator_image_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getActuatorUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded Image FAILED to be submited to API."));
          }
        }

        // UPLOAD DOCUMENT TO API
        if ($doc_type === 'upload' && $actuator_webdocument !== $this->getActuator()->hasWebDocument) {
          $fids = $form_state->getValue('actuator_webdocument_upload');
          $msg = $api->parseObjectResponse($api->uploadFile($this->getActuatorUri(), reset($fids)), 'uploadFile');
          if ($msg == NULL) {
            \Drupal::messenger()->addError(t("The Uploaded WebDocument FAILED to be submited to API."));
          }
        }

        \Drupal::messenger()->addMessage(t("Actuator has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Actuator: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveActuator($actuatorUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($actuatorUri);
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
