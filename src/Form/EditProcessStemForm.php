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

class EditProcessStemForm extends FormBase {

  protected $detectorStemUri;

  protected $detectorStem;

  protected $sourceProcessStem;

  public function getProcessStemUri() {
    return $this->detectorStemUri;
  }

  public function setProcessStemUri($uri) {
    return $this->detectorStemUri = $uri;
  }

  public function getProcessStem() {
    return $this->detectorStem;
  }

  public function setProcessStem($obj) {
    return $this->detectorStem = $obj;
  }

  public function getSourceProcessStem() {
    return $this->sourceProcessStem;
  }

  public function setSourceProcessStem($obj) {
    return $this->sourceProcessStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_processstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $processstemuri = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_processstem';

    $uri=$processstemuri;
    $uri_decode=base64_decode($uri);
    $this->setProcessStemUri($uri_decode);

    $this->setProcessStem($this->retrieveProcessStem($this->getProcessStemUri()));
    if ($this->getProcessStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Process."));
      self::backUrl();
      return;
    }

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    // IN CASE ITS A DERIVATION ORIGINAL MUST BE REMOVED ALSO
    if ($this->getProcessStem()->hasStatus === VSTOI::CURRENT || $this->getProcessStem()->hasVersion > 1) {
      unset($derivations[Constant::DEFAULT_WAS_GENERATED_BY]);
    }

    $languages = ['' => $this->t('Select one please')] + $languages;
    $derivations = ['' => $this->t('Select one please')] + $derivations;

    $form['processstem_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getProcessStemUri()).'">'.$this->getProcessStemUri().'</a>'),
    ];
    // dpm($this->getProcessStem());
    if ($this->getProcessStem()->superUri) {
      $form['processstem_type'] = [
        'top' => [
          '#type' => 'markup',
          '#markup' => '<div class="col border border-white">',
        ],
        'main' => [
          '#type' => 'textfield',
          '#title' => $this->t('Parent Type'),
          '#name' => 'processstem_type',
          '#default_value' => $this->getProcessStem()->superUri ? Utils::fieldToAutocomplete($this->getProcessStem()->superUri, $this->getProcessStem()->superClassLabel) : '',
          '#id' => 'processstem_type',
          '#parents' => ['processstem_type'],
          '#attributes' => [
            'class' => ['open-tree-modal'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 800]),
            'data-url' => Url::fromRoute('rep.tree_form', [
              'mode' => 'modal',
              'elementtype' => 'processstem',
            ], ['query' => ['field_id' => 'processstem_type']])->toString(),
            'data-field-id' => 'processstem_type',
            'data-elementtype' => 'processstem',
            'data-search-value' => $this->getProcessStem()->superUri ?? '',
          ],
        ],
        'bottom' => [
          '#type' => 'markup',
          '#markup' => '</div>',
        ],
      ];
    }

    $form['processstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getProcessStem()->hasContent,
    ];
    $form['processstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getProcessStem()->hasLanguage,
      '#attributes' => [
        'id' => 'processstem_language'
      ]
    ];
    $form['processstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getProcessStem()->hasStatus === VSTOI::CURRENT || $this->getProcessStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getProcessStem()->hasVersion + 1 : $this->getProcessStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['processstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getProcessStem()->comment,
    ];

    if ($this->getProcessStem()->wasDerivedFrom !== NULL) {
      $form['processstem_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      if ($this->getProcessStem()->wasDerivedFrom !== NULL) {
        $form['processstem_df_wrapper']['processstem_wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => $this->getProcessStem()->wasDerivedFrom,
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 0;",
            'disabled' => 'disabled',
          ],
        ];
      }

      $elementUri = Utils::namespaceUri($this->getProcessStem()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['processstem_df_wrapper']['processstem_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-primary text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }
    $form['processstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $this->getProcessStem()->wasGeneratedBy,
      '#attributes' => [
        'id' => 'processstem_was_generated_by'
      ],
      '#disabled' => ($this->getProcessStem()->wasGeneratedBy === Constant::WGB_ORIGINAL ? true:false)
    ];

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current processstem and its image.
    $processstem = $this->getProcessStem();
    $processstem_uri = Utils::namespaceUri($this->getProcessStemUri());
    $processstem_image = $processstem->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($processstem_image) && stripos(trim($processstem_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($processstem_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($processstem_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $processstem_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['processstem_information']['processstem_image_type'] = [
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
    $form['processstem_information']['processstem_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($image_type === 'url') ? $processstem_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="processstem_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['processstem_information']['processstem_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="processstem_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($processstem_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $processstem_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['processstem_information']['processstem_image_upload_wrapper']['processstem_image_upload'] = [
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
    $processstem_webdocument = $processstem->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($processstem_webdocument) && stripos(trim($processstem_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($processstem_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['processstem_information']['processstem_webdocument_type'] = [
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
    $form['processstem_information']['processstem_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $processstem_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="processstem_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['processstem_information']['processstem_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="processstem_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($processstem_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $processstem_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['processstem_information']['processstem_webdocument_upload_wrapper']['processstem_webdocument_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Document'),
      '#upload_location' => 'private://resources/' . $modUri . '/webdoc',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf doc docx txt xls xlsx'],
      ],
      // If a file already exists, pass its ID so Drupal can display it.
      '#default_value' => $existing_fid ? [$existing_fid] : NULL,
    ];

    if ($this->getProcessStem()->hasReviewNote !== NULL && $this->getProcessStem()->hasStatus !== null) {
      $form['processstem_hasreviewnote'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Review Notes'),
        '#default_value' => $this->getProcessStem()->hasReviewNote,
        '#disabled' => TRUE
      ];
      $form['processstem_haseditoremail'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Reviewer Email'),
        '#default_value' => \Drupal::currentUser()->getEmail(),
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
      if(strlen($form_state->getValue('processstem_content')) < 1) {
        $form_state->setErrorByName('processstem_content', $this->t('Please enter a valid Name'));
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
      if ($this->getProcessStem()->hasStatus === VSTOI::CURRENT || $this->getProcessStem()->hasStatus === VSTOI::DEPRECATED) {

        $processStemJson = '{"uri":"'.Utils::uriGen('processstem').'",'.
          '"superUri":"'.Utils::uriFromAutocomplete($this->getProcessStem()->superUri).'",'.
          '"label":"'.$form_state->getValue('processstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('processstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('processstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('processstem_version').'",'.
          '"comment":"'.$form_state->getValue('processstem_description').'",'.
          '"hasWebDocument":"",'.
          '"hasImageUri":"",' .
          '"wasDerivedFrom":"'.$this->getProcessStem()->uri.'",'. //Previous Version is the New Derivation Value
          '"wasGeneratedBy":"'.$form_state->getValue('processstem_was_generated_by').'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->elementAdd('processtem', $processStemJson);
        \Drupal::messenger()->addMessage(t("New Version Process Stem has been created successfully."));

      } else {

        // Determine the chosen document type.
        $doc_type = $form_state->getValue('processstem_webdocument_type');
        $processstem_webdocument = '';

        // If user selected URL, use the textfield value.
        if ($doc_type === 'url') {
          $processstem_webdocument = $form_state->getValue('processstem_webdocument_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($doc_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('processstem_webdocument_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'processstem', 1);
              // Now get the filename from the file entity.
              $processstem_webdocument = $file->getFilename();
            }
          }
        }

        // Determine the chosen image type.
        $image_type = $form_state->getValue('processstem_image_type');
        $processstem_image = '';

        // If user selected URL, use the textfield value.
        if ($image_type === 'url') {
          $processstem_image = $form_state->getValue('processstem_image_url');
        }
        // If user selected Upload, load the file entity and get its filename.
        elseif ($image_type === 'upload') {
          // Get the file IDs from the managed_file element.
          $fids = $form_state->getValue('processstem_image_upload');
          if (!empty($fids)) {
            // Load the first file (file ID is returned, e.g. "374").
            $file = File::load(reset($fids));
            if ($file) {
              // Mark the file as permanent and save it.
              $file->setPermanent();
              $file->save();
              // Optionally register file usage to prevent cleanup.
              \Drupal::service('file.usage')->add($file, 'sir', 'processstem', 1);
              // Now get the filename from the file entity.
              $processstem_image = $file->getFilename();
            }
          }
        }

        $processStemJson = '{"uri":"'.$this->getProcessStem()->uri.'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($this->getProcessStem()->superUri).'",'.
        '"label":"'.$form_state->getValue('processstem_content').'",'.
        '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
        '"hasStatus":"'.$this->getProcessStem()->hasStatus.'",'.
        '"hasContent":"'.$form_state->getValue('processstem_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('processstem_language').'",'.
        '"hasVersion":"'.$form_state->getValue('processstem_version').'",'.
        '"comment":"'.$form_state->getValue('processstem_description').'",'.
        '"hasWebDocument":"' . $processstem_webdocument . '",' .
        '"hasImageUri":"' . $processstem_image . '",' .
        '"wasDerivedFrom":"'.$this->getProcessStem()->wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$form_state->getValue('processstem_was_generated_by').'",'.
        '"hasReviewNote":"'.($this->getProcessStem()->hasStatus !== null ? $this->getProcessStem()->hasReviewNote : '').'",'.
        '"hasEditorEmail":"'.($this->getProcessStem()->hasStatus !== null ? $this->getProcessStem()->hasEditorEmail : '').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->elementDel('processstem', $this->getProcessStemUri());
        $api->elementAdd('processstem', $processStemJson);
        \Drupal::messenger()->addMessage(t("Process Stem has been updated successfully."));
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Process Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveProcessStem($detectorStemUri) {
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
