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

class ReviewActuatorForm extends FormBase {

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
    return 'review_actuator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $actuatoruri = NULL) {

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

    //dpm($this->getActuator());

    $form['actuator_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'style' => 'max-width: 1280px;margin-bottom:15px!important;',
      ],
    ];

    $form['actuator_wrapper']['actuator_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('URI: '),
      '#markup' => t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($this->getActuatorUri()).'">'.$this->getActuatorUri().'</a>'),
    ];

    $form['actuator_wrapper']['actuator_stem'] = [
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
      '#disabled' => TRUE
    ];
    $form['actuator_wrapper']['actuator_codebook'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codebook'),
      '#default_value' => $codebookLabel,
      '#autocomplete_route_name' => 'sir.actuator_codebook_autocomplete',
      '#disabled' => TRUE
    ];
    $form['actuator_wrapper']['actuator_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getActuator()->hasStatus === VSTOI::CURRENT || $this->getActuator()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getActuator()->hasVersion + 1 : $this->getActuator()->hasVersion,
      '#disabled' => TRUE
    ];
    $form['actuator_wrapper']['actuator_isAttributeOf'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Attribute Of <small><i>(optional)</i></small>'),
        '#name' => 'actuator_isAttributeOf',
        '#default_value' => $this->getActuator()->isAttributeOf,
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
      '#disabled' => TRUE
    ];
    if ($this->getActuator()->wasDerivedFrom !== NULL) {
      $form['actuator_wrapper']['actuator_df_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'align-items-center', 'w-100'], // Flex container para alinhamento correto
          'style' => "width: 100%; gap: 10px;", // Garante espaçamento correto
        ],
      ];

      $form['actuator_wrapper']['actuator_df_wrapper']['actuator_wasderivedfrom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Derived From'),
        '#default_value' => $this->getActuator()->wasDerivedFrom,
        '#attributes' => [
          'class' => ['flex-grow-1'],
          'style' => "width: 100%; min-width: 0;",
          'disabled' => 'disabled',
        ],
      ];

      $elementUri = Utils::namespaceUri($this->getActuator()->wasDerivedFrom);
      $elementUriEncoded = base64_encode($elementUri);
      $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

      $form['actuator_wrapper']['actuator_df_wrapper']['actuator_wasderivedfrom_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap mt-2" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
      ];
    }

    $form['actuator_wrapper']['actuator_owner'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getActuator()->hasSIRManagerEmail,
      '#attributes' => [
        'disabled' => 'disabled',
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
    $form['actuator_wrapper']['actuator_information']['actuator_image_type'] = [
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
    $form['actuator_wrapper']['actuator_information']['actuator_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => ($image_type === 'url') ? $actuator_image : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="actuator_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['actuator_wrapper']['actuator_information']['actuator_image_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['actuator_wrapper']['actuator_information']['actuator_image_upload_wrapper']['actuator_image_upload'] = [
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
    $form['actuator_wrapper']['actuator_information']['actuator_webdocument_type'] = [
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
    $form['actuator_wrapper']['actuator_information']['actuator_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => ($webdocument_type === 'url') ? $actuator_webdocument : '',
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#disabled' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="actuator_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Container for the file upload elements (only visible when type = 'upload').
    $form['actuator_wrapper']['actuator_information']['actuator_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#disabled' => TRUE,
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
    $form['actuator_wrapper']['actuator_information']['actuator_webdocument_upload_wrapper']['actuator_webdocument_upload'] = [
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

    $form['actuator_wrapper']['actuator_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getActuator()->hasReviewNote,
    ];
    $form['actuator_wrapper']['actuator_haseditoremail'] = [
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

    if ($button_name != 'back') {
      // if ($button_name === 'review_reject') {
      //   if(strlen($form_state->getValue('actuator_hasreviewnote')) < 1) {
      //     $form_state->setErrorByName('actuator_hasreviewnote', $this->t('You must enter a Reject Note'));
      //   }
      // }
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

    if ($button_name === 'review_reject' && strlen($form_state->getValue('actuator_hasreviewnote')) === 0) {
      \Drupal::messenger()->addWarning(t("To reject you must type a Review Note!"));
      return false;
    }

    $api = \Drupal::service('rep.api_connector');

    try{

      $useremail = \Drupal::currentUser()->getEmail();
      $result = $this->getActuator();

      //APROVE
      if ($button_name !== 'review_reject') {

        $actuatorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
          '"hasActuatorStem":"'.$result->hasActuatorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('actuator_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasStatus":"'.VSTOI::CURRENT.'",'.
          '"hasImageUri":"'.$result->hasImageUri.'",'.
          '"hasWebDocument":"'.$result->hasWebDocument.'"'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('actuator', $result->uri);
        $api->elementAdd('actuator', $actuatorJson);

        //IF ITS A DERIVATION APROVAL PARENT MUST BECOME DEPRECATED
        if ($result->wasDerivedFrom !== null && $result->wasDerivedFrom !== '') {

          $rawresponse = $api->getUri($result->wasDerivedFrom);
          $obj = json_decode($rawresponse);
          $resultParent = $obj->body;

          $parentActuatorJson = '{'.
            '"uri":"'.$resultParent->uri.'",'.
            '"typeUri":"'.$resultParent->typeUri.'",'.
            '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
            '"hasActuatorStem":"'.$resultParent->hasActuatorStem.'",'.
            '"hasCodebook":"'.$resultParent->hasCodebook.'",'.
            '"hasContent":"'.$resultParent->label.'",'.
            '"hasSIRManagerEmail":"'.$resultParent->hasSIRManagerEmail.'",'.
            '"label":"'.$resultParent->label.'",'.
            '"hasVersion":"'.$resultParent->hasVersion.'",'.
            '"isAttributeOf":"'.$resultParent->isAttributeOf.'",'.
            '"wasDerivedFrom":"'.$resultParent->wasDerivedFrom.'",'.
            '"hasReviewNote":"'.$resultParent->hasReviewNote.'",'.
            '"hasEditorEmail":"'.$resultParent->hasEditorEmail.'",'.
            '"hasStatus":"'.VSTOI::DEPRECATED.'",'.
            '"hasImageUri":"'.$resultParent->hasImageUri.'",'.
            '"hasWebDocument":"'.$resultParent->hasWebDocument.'"'.
          '}';

          // UPDATE BY DELETING AND CREATING
          $api->elementDel('actuator', $resultParent->uri);
          $api->elementAdd('actuator', $parentActuatorJson);
        }

        \Drupal::messenger()->addMessage(t("Actuator has been APPROVED successfully."));
          self::backUrl();
          return;

      // REJECT
      } else {

        //MAIN BODY CODEBOOK
        $actuatorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
          '"hasActuatorStem":"'.$result->hasActuatorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->label.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$form_state->getValue('actuator_hasreviewnote').'",'.
          '"hasEditorEmail":"'.$useremail.'",'.
          '"hasImageUri":"'.$result->hasImageUri.'",'.
          '"hasWebDocument":"'.$result->hasWebDocument.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'"}';

        // \Drupal::messenger()->addWarning($actuatorJson);
        // return false;

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('actuator', $result->uri);
        $api->elementAdd('actuator', $actuatorJson);

        \Drupal::messenger()->addWarning(t("Actuator has been REJECTED."));
          self::backUrl();
          return;
      }

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
