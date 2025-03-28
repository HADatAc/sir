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

class ReviewDetectorStemForm extends FormBase {

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
    return 'review_detectorstem_form';
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

    $uri=$detectorstemuri;
    $uri_decode=base64_decode($uri);
    $this->setDetectorStemUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    $wasGeneratedBy = Constant::DEFAULT_WAS_GENERATED_BY;
    $this->setDetectorStem($this->retrieveDetectorStem($this->getDetectorStemUri()));
    if ($this->getDetectorStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Detector Stem."));
      self::backUrl();
      return;
    } else {
      $wasGeneratedBy = $this->getDetectorStem()->wasGeneratedBy;
      if ($this->getDetectorStem()->wasDerivedFrom != NULL) {
        $this->setSourceDetectorStem($this->retrieveDetectorStem($this->getDetectorStem()->wasDerivedFrom));
        if ($this->getSourceDetectorStem() != NULL && $this->getSourceDetectorStem()->hasContent != NULL) {
          $sourceContent = Utils::fieldToAutocomplete($this->getSourceDetectorStem()->uri,$this->getSourceDetectorStem()->hasContent);
        }
      }
    }

    //dpm($this->getDetector());

    $form['detectorstem_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'max-width: 1280px;margin-bottom:15px!important;',
      ],
    ];

    $form['detectorstem_wrapper']['detectorstem_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getDetectorStemUri()).'">'.$this->getDetectorStemUri().'</a>'),
    ];

    if ($this->getDetectorStem()->superUri) {
      $form['detectorstem_wrapper']['detectorstem_type'] = [
        'top' => [
          '#type' => 'markup',
          '#markup' => '<div class="col border border-white">',
        ],
        'main' => [
          '#type' => 'textfield',
          '#title' => $this->t('Type'),
          '#name' => 'detectorstem_type',
          '#default_value' => $this->getDetectorStem()->superUri ? Utils::fieldToAutocomplete($this->getDetectorStem()->superUri, $this->getDetectorStem()->superClassLabel) : '',
          '#disabled' => TRUE,
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
    }
    $form['detectorstem_wrapper']['detectorstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getDetectorStem()->hasContent,
      '#disabled' => TRUE,
    ];
    $form['detectorstem_wrapper']['detectorstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getDetectorStem()->hasLanguage,
      '#disabled' => TRUE,
    ];
    $form['detectorstem_wrapper']['detectorstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getDetectorStem()->hasVersion,
      '#default_value' =>
        ($this->getDetectorStem()->hasStatus === VSTOI::CURRENT || $this->getDetectorStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getDetectorStem()->hasVersion + 1 : $this->getDetectorStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['detectorstem_wrapper']['detectorstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getDetectorStem()->comment,
      '#disabled' => TRUE,
    ];

    if ($this->getDetectorStem()->wasDerivedFrom !== NULL) {
      $api = \Drupal::service('rep.api_connector');
      $rawresponse = $api->getUri($this->getDetectorStem()->wasDerivedFrom);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $result = $obj->body;

        $form['detectorstem_wrapper']['detectorstem__df_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
            'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
          ],
        ];

        $form['detectorstem_wrapper']['detectorstem__df_wrapper']['detectorstem__wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => Utils::fieldToAutocomplete($this->getDetectorStem()->wasDerivedFrom, $result->label),
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 1045px;",
            'disabled' => 'disabled',
          ],
        ];

        $elementUri = Utils::namespaceUri($this->getDetectorStem()->wasDerivedFrom);
        $elementUriEncoded = base64_encode($elementUri);
        $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

        $form['detectorstem__df_wrapper']['detectorstem__wasderivedfrom_button'] = [
          '#type' => 'markup',
          '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-primary text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
        ];
      }
    }

    $form['detectorstem_wrapper']['detectorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
      '#disabled' => TRUE,
    ];

    $form['detectorstem_wrapper']['detectorstem_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getDetectorStem()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];

    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current detector and its image.
    $detector = $this->getDetectorStem();
    $detectorstem_uri = Utils::namespaceUri($this->getDetectorStemUri());
    $detectorstem_image = $detector->hasImageUri ?? '';

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
    $form['detectorstem_wrapper']['detectorstem_information']['detectorstem_image_type'] = [
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
    $form['detectorstem_wrapper']['detectorstem_information']['detectorstem_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $detectorstem_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="detectorstem_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['detectorstem_wrapper']['detectorstem_information']['detectorstem_image_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['detectorstem_wrapper']['detectorstem_information']['detectorstem_image_upload_wrapper']['detectorstem_image_upload'] = [
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
    $detectorstem_webdocument = $detector->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($detectorstem_webdocument) && stripos(trim($detectorstem_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($detectorstem_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['detectorstem_wrapper']['detectorstem_information']['detectorstem_webdocument_type'] = [
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
    $form['detectorstem_wrapper']['detectorstem_information']['detectorstem_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $detectorstem_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="detectorstem_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['detectorstem_wrapper']['detectorstem_information']['detectorstem_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['detectorstem_wrapper']['detectorstem_information']['detectorstem_webdocument_upload_wrapper']['detectorstem_webdocument_upload'] = [
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

    $form['detectorstem_wrapper']['detectorstem_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getDetectorStem()->hasReviewNote,
    ];
    $form['detectorstem_wrapper']['detectorstem_haseditoremail'] = [
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

    // if ($button_name != 'back') {
    //   if ($button_name === 'review_reject') {
    //     if(strlen($form_state->getValue('detectorstem_hasreviewnote')) < 1) {
    //       $form_state->setErrorByName('detectorstem_hasreviewnote', $this->t('You must enter a Reject Note'));
    //     }
    //   }
    // }
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('detectorstem_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getDetectorStem();

      //APROVE
      if ($button_name !== 'review_reject') {

        $detectorStemJson = '{"uri":"'.$this->getDetectorStem()->uri.'",'.
          '"superUri":"'.$this->getDetectorStem()->superUri.'",'.
          '"label":"'.$this->getDetectorStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'",'.
          '"hasContent":"'.$this->getDetectorStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getDetectorStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getDetectorStem()->hasVersion.'",'.
          '"comment":"'.$this->getDetectorStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getDetectorStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getDetectorStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('detectorstem_hasreviewnote').'",'.
          '"hasImageUri":"'.$this->getDetectorStem()->hasImageUri.'",'.
          '"hasWebDocument":"'.$this->getDetectorStem()->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getDetectorStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('detectorstem', $this->getDetectorStemUri());
        $api->elementAdd('detectorstem', $detectorStemJson);

        // IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED, but in this case version must be also greater than 1, because
        // Detector Stems can start to be like a derivation element by itself
        if (($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') && $result->hasVersion > 1) {
          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentDetectorStemJson = '{"uri":"'.$resultParent->uri.'",'.
          (!empty($resultParent->superUri) ? '"superUri":"'.$resultParent->superUri.'",' : '').
          '"label":"'.$resultParent->label.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DEPRECATED.'",'.
          '"hasContent":"'.$resultParent->hasContent.'",'.
          '"hasLanguage":"'.$resultParent->hasLanguage.'",'.
          '"hasVersion":"'.$resultParent->hasVersion.'",'.
          '"comment":"'.$resultParent->comment.'",'.
          (!empty($resultParent->wasDerivedFrom) ? '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",' : '').
          '"wasGeneratedBy":"'.$resultParent->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$resultParent->hasReviewNote.'",'.
          '"hasImageUri":"'.$resultParent->hasImageUri.'",'.
          '"hasWebDocument":"'.$resultParent->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'"}';

          // UPDATE BY DELETING AND CREATING
          $api->elementDel('detectorstem', $resultParent->uri);
          $api->elementAdd('detectorstem', $parentDetectorStemJson);
        }

        \Drupal::messenger()->addMessage(t("Detector Stem has been updated successfully."));
      // REJECT
      } else {

        $detectorStemJson = '{"uri":"'.$this->getDetectorStem()->uri.'",'.
          '"superUri":"'.$this->getDetectorStem()->superUri.'",'.
          '"label":"'.$this->getDetectorStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$this->getDetectorStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getDetectorStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getDetectorStem()->hasVersion.'",'.
          '"comment":"'.$this->getDetectorStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getDetectorStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getDetectorStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('detectorstem_hasreviewnote').'",'.
          '"hasImageUri":"'.$this->getDetectorStem()->hasImageUri.'",'.
          '"hasWebDocument":"'.$this->getDetectorStem()->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getDetectorStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('detectorstem', $this->getDetectorStemUri());
        $api->elementAdd('detectorstem', $detectorStemJson);
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
