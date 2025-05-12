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

class ReviewActuatorStemForm extends FormBase {

  protected $actuatorStemUri;

  protected $actuatorStem;

  protected $sourceActuatorStem;

  public function getActuatorStemUri() {
    return $this->actuatorStemUri;
  }

  public function setActuatorStemUri($uri) {
    return $this->actuatorStemUri = $uri;
  }

  public function getActuatorStem() {
    return $this->actuatorStem;
  }

  public function setActuatorStem($obj) {
    return $this->actuatorStem = $obj;
  }

  public function getSourceActuatorStem() {
    return $this->sourceActuatorStem;
  }

  public function setSourceActuatorStem($obj) {
    return $this->sourceActuatorStem = $obj;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_actuatorstem_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $actuatorstemuri = NULL) {

    // ROOT URL
    $root_url = \Drupal::request()->getBaseUrl();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $uri=$actuatorstemuri;
    $uri_decode=base64_decode($uri);
    $this->setActuatorStemUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    $sourceContent = '';
    $wasGeneratedBy = Constant::DEFAULT_WAS_GENERATED_BY;
    $this->setActuatorStem($this->retrieveActuatorStem($this->getActuatorStemUri()));
    if ($this->getActuatorStem() == NULL) {
      \Drupal::messenger()->addError(t("Failed to retrieve Actuator Stem."));
      self::backUrl();
      return;
    } else {
      $wasGeneratedBy = $this->getActuatorStem()->wasGeneratedBy;
      if ($this->getActuatorStem()->wasDerivedFrom != NULL) {
        $this->setSourceActuatorStem($this->retrieveActuatorStem($this->getActuatorStem()->wasDerivedFrom));
        if ($this->getSourceActuatorStem() != NULL && $this->getSourceActuatorStem()->hasContent != NULL) {
          $sourceContent = Utils::fieldToAutocomplete($this->getSourceActuatorStem()->uri,$this->getSourceActuatorStem()->hasContent);
        }
      }
    }

    //dpm($this->getActuator());

    $form['actuatorstem_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'max-width: 1280px;margin-bottom:15px!important;',
      ],
    ];

    $form['actuatorstem_wrapper']['actuatorstem_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getActuatorStemUri()).'">'.$this->getActuatorStemUri().'</a>'),
    ];

    if ($this->getActuatorStem()->superUri) {
      $form['actuatorstem_wrapper']['actuatorstem_type'] = [
        'top' => [
          '#type' => 'markup',
          '#markup' => '<div class="col border border-white">',
        ],
        'main' => [
          '#type' => 'textfield',
          '#title' => $this->t('Type'),
          '#name' => 'actuatorstem_type',
          '#default_value' => $this->getActuatorStem()->superUri ? Utils::fieldToAutocomplete($this->getActuatorStem()->superUri, $this->getActuatorStem()->superClassLabel) : '',
          '#disabled' => TRUE,
          '#id' => 'actuatorstem_type',
          '#parents' => ['actuatorstem_type'],
          '#attributes' => [
            'class' => ['open-tree-modal'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 800]),
            'data-url' => Url::fromRoute('rep.tree_form', [
              'mode' => 'modal',
              'elementtype' => 'actuatorstem',
            ], ['query' => ['field_id' => 'actuatorstem_type']])->toString(),
            'data-field-id' => 'actuatorstem_type',
            'data-elementtype' => 'actuatorstem',
            'data-search-value' => $this->getActuatorStem()->superUri ?? '',
          ],
        ],
        'bottom' => [
          '#type' => 'markup',
          '#markup' => '</div>',
        ],
      ];
    }
    $form['actuatorstem_wrapper']['actuatorstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getActuatorStem()->hasContent,
      '#disabled' => TRUE,
    ];
    $form['actuatorstem_wrapper']['actuatorstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $this->getActuatorStem()->hasLanguage,
      '#disabled' => TRUE,
    ];
    $form['actuatorstem_wrapper']['actuatorstem_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $this->getActuatorStem()->hasVersion,
      '#default_value' =>
        ($this->getActuatorStem()->hasStatus === VSTOI::CURRENT || $this->getActuatorStem()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getActuatorStem()->hasVersion + 1 : $this->getActuatorStem()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['actuatorstem_wrapper']['actuatorstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getActuatorStem()->comment,
      '#disabled' => TRUE,
    ];

    if ($this->getActuatorStem()->wasDerivedFrom !== NULL) {
      $api = \Drupal::service('rep.api_connector');
      $rawresponse = $api->getUri($this->getActuatorStem()->wasDerivedFrom);
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $result = $obj->body;

        $form['actuatorstem_wrapper']['actuatorstem__df_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
            'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
          ],
        ];

        $form['actuatorstem_wrapper']['actuatorstem__df_wrapper']['actuatorstem__wasderivedfrom'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Derived From'),
          '#default_value' => Utils::fieldToAutocomplete($this->getActuatorStem()->wasDerivedFrom, $result->label),
          '#attributes' => [
            'class' => ['flex-grow-1'],
            'style' => "width: 100%; min-width: 1045px;",
            'disabled' => 'disabled',
          ],
        ];

        $elementUri = Utils::namespaceUri($this->getActuatorStem()->wasDerivedFrom);
        $elementUriEncoded = base64_encode($elementUri);
        $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

        $form['actuatorstem_wrapper']['actuatorstem__df_wrapper']['actuatorstem__wasderivedfrom_button'] = [
          '#type' => 'markup',
          '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
        ];
      }
    }

    $form['actuatorstem_wrapper']['actuatorstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Derived By'),
      '#options' => $derivations,
      '#default_value' => $wasGeneratedBy,
      '#disabled' => TRUE,
    ];

    $form['actuatorstem_wrapper']['actuatorstem_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getActuatorStem()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    // **** IMAGE ****
    // Retrieve the current image value.
    // Retrieve the current actuatorstem and its image.
    $actuatorstem = $this->getActuatorStem();
    $actuatorstem_uri = Utils::namespaceUri($this->getActuatorStemUri());
    $actuatorstem_image = $actuatorstem->hasImageUri ?? '';

    // Determine if the existing web document is a URL or a file.
    $image_type = '';
    if (!empty($actuatorstem_image) && stripos(trim($actuatorstem_image), 'http') === 0) {
      $image_type = 'url';
    }
    elseif (!empty($actuatorstem_image)) {
      $image_type = 'upload';
    }

    $modUri = '';
    if (!empty($actuatorstem_uri)) {
      // Example of extracting part of the URI. Adjust or remove if not needed.
      $parts = explode(':/', $actuatorstem_uri);
      if (count($parts) > 1) {
        $modUri = $parts[1];
      }
    }

    // Image Type selector (URL or Upload).
    $form['actuatorstem_wrapper']['actuatorstem_information']['actuatorstem_image_type'] = [
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
    $form['actuatorstem_wrapper']['actuatorstem_information']['actuatorstem_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $actuatorstem_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['actuatorstem_wrapper']['actuatorstem_information']['actuatorstem_image_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_image_fid = NULL;
    if ($image_type === 'upload' && !empty($actuatorstem_image)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/image/' . $actuatorstem_image;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_image_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['actuatorstem_wrapper']['actuatorstem_information']['actuatorstem_image_upload_wrapper']['actuatorstem_image_upload'] = [
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
    $actuatorstem_webdocument = $actuatorstem->hasWebDocument ?? '';

    // Determine if the existing web document is a URL or a file.
    $webdocument_type = '';
    if (!empty($actuatorstem_webdocument) && stripos(trim($actuatorstem_webdocument), 'http') === 0) {
      $webdocument_type = 'url';
    }
    elseif (!empty($actuatorstem_webdocument)) {
      $webdocument_type = 'upload';
    }

    // Web Document Type selector (URL or Upload).
    $form['actuatorstem_wrapper']['actuatorstem_information']['actuatorstem_webdocument_type'] = [
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
    $form['actuatorstem_wrapper']['actuatorstem_information']['actuatorstem_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $actuatorstem_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['actuatorstem_wrapper']['actuatorstem_information']['actuatorstem_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="actuatorstem_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];

    // Attempt to load an existing file if the document is not a URL.
    $existing_fid = NULL;
    if ($webdocument_type === 'upload' && !empty($actuatorstem_webdocument)) {
      // Build the expected file URI in the private filesystem.
      $desired_uri = 'private://resources/' . $modUri . '/webdoc/' . $actuatorstem_webdocument;
      $files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $desired_uri]);
      $file = reset($files);
      if ($file) {
        $existing_fid = $file->id();
      }
    }

    // 5. Managed file element for uploading a new document.
    $form['actuatorstem_wrapper']['actuatorstem_information']['actuatorstem_webdocument_upload_wrapper']['actuatorstem_webdocument_upload'] = [
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

    $form['actuatorstem_wrapper']['actuatorstem_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getActuatorStem()->hasReviewNote,
    ];
    $form['actuatorstem_wrapper']['actuatorstem_haseditoremail'] = [
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
    //     if(strlen($form_state->getValue('actuator_hasreviewnote')) < 1) {
    //       $form_state->setErrorByName('actuator_hasreviewnote', $this->t('You must enter a Reject Note'));
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('actuatorstem_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getActuatorStem();

      //APROVE
      if ($button_name !== 'review_reject') {

        $actuatorStemJson = '{"uri":"'.$this->getActuatorStem()->uri.'",'.
          '"superUri":"'.$this->getActuatorStem()->superUri.'",'.
          '"label":"'.$this->getActuatorStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'",'.
          '"hasContent":"'.$this->getActuatorStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getActuatorStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getActuatorStem()->hasVersion.'",'.
          '"comment":"'.$this->getActuatorStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getActuatorStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getActuatorStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('actuatorstem_hasreviewnote').'",'.
          '"hasImageUri":"'.$this->getActuatorStem()->hasImageUri.'",'.
          '"hasWebDocument":"'.$this->getActuatorStem()->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getActuatorStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('actuatorstem', $this->getActuatorStemUri());
        $api->elementAdd('actuatorstem', $actuatorStemJson);

        // IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED, but in this case version must be also greater than 1, because
        // Actuator Stems can start to be like a derivation element by itself
        if (($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') && $result->hasVersion > 1) {
          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentActuatorStemJson = '{"uri":"'.$resultParent->uri.'",'.
          (!empty($resultParent->superUri) ? '"superUri":"'.$resultParent->superUri.'",' : '').
          '"label":"'.$resultParent->label.'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
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
          $api->elementDel('actuatorstem', $resultParent->uri);
          $api->elementAdd('actuatorstem', $parentActuatorStemJson);
        }

        \Drupal::messenger()->addMessage(t("Actuator Stem has been updated successfully."));
      // REJECT
      } else {

        $actuatorStemJson = '{"uri":"'.$this->getActuatorStem()->uri.'",'.
          '"superUri":"'.$this->getActuatorStem()->superUri.'",'.
          '"label":"'.$this->getActuatorStem()->label.'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$this->getActuatorStem()->hasContent.'",'.
          '"hasLanguage":"'.$this->getActuatorStem()->hasLanguage.'",'.
          '"hasVersion":"'.$this->getActuatorStem()->hasVersion.'",'.
          '"comment":"'.$this->getActuatorStem()->comment.'",'.
          '"wasDerivedFrom":"'.$this->getActuatorStem()->wasDerivedFrom.'",'.
          '"wasGeneratedBy":"'.$this->getActuatorStem()->wasGeneratedBy.'",'.
          '"hasReviewNote":"'.$form_state->getValue('actuatorstem_hasreviewnote').'",'.
          '"hasImageUri":"'.$this->getActuatorStem()->hasImageUri.'",'.
          '"hasWebDocument":"'.$this->getActuatorStem()->hasWebDocument.'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasSIRManagerEmail":"'.$this->getActuatorStem()->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('actuatorstem', $this->getActuatorStemUri());
        $api->elementAdd('actuatorstem', $actuatorStemJson);
      }

      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Actuator Stem: ".$e->getMessage()));
      self::backUrl();
      return;
    }
  }

  public function retrieveActuatorStem($actuatorStemUri) {
    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($actuatorStemUri);
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
