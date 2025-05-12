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

class AddResponseOptionForm extends FormBase {

  protected $codebookSlotUri;

  protected $codebookSlot;

  public function getCodebookSlotUri() {
    return $this->codebookSlotUri;
  }

  public function setCodebookSlotUri($uri) {
    return $this->codebookSlotUri = $uri;
  }

  public function getCodebookSlot() {
    return $this->codebookSlot;
  }

  public function setCodebookSlot($uri) {
    return $this->codebookSlot = $uri;
  }

  protected $responseoptionUri;

  public function setInstrumenUri() {
    $this->responseoptionUri = Utils::uriGen('responseoption');
  }

  public function getInstrumenUri() {
    return $this->responseoptionUri;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_responseoption_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $codebooksloturi = NULL) {

    // Check if the responseoption URI already exists in the form state.
    // If not, generate a new URI and store it in the form state.
    if (!$form_state->has('responseoption_uri')) {
      $this->setInstrumenUri();
      $form_state->set('responseoption_uri', $this->getInstrumenUri());
    }
    else {
      // Retrieve the persisted URI from form state.
      $this->responseoptionUri = $form_state->get('responseoption_uri');
    }

    // SAVE RESPONSEOPTION SLOT URI
    if ($codebooksloturi == "EMPTY") {
      $this->setCodebookSlotUri("");
      $this->setCodebookSlot(NULL);
    } else {
      $uri_decode=base64_decode($codebooksloturi);
      $this->setCodebookSlotUri($uri_decode);

      // RETRIEVE RESPONSEOPTION SLOT
      $api = \Drupal::service('rep.api_connector');
      $rawresponse = $api->getUri($this->getCodebookSlotUri());
      $obj = json_decode($rawresponse);
      if ($obj->isSuccessful) {
        $this->setCodebookSlot($obj->body);
      }
    }

    // RETRIEVE TABLES
    $tables = new Tables;
    $languages = $tables->getLanguages();

    if ($this->getCodebookSlotUri() != NULL && $this->getCodebookSlotUri() != "") {
      $form['responseoption_codebook_slot'] = [
        '#type' => 'textfield',
        '#title' => t('Being created in the context of the following Response Option Slot URI'),
        '#value' => $this->getCodebookSlotUri(),
        '#disabled' => TRUE,
      ];
    }
    $form['responseoption_content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
    ];
    $form['responseoption_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    // $form['responseoption_version'] = [
    //   '#type' => 'textfield',
    //   '#title' => $this->t('Version'),
    // ];
    $form['responseoption_version_display'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['responseoption_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['responseoption_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    // Add a hidden field to persist the responseoption URI between form rebuilds.
    $form['responseoption_uri'] = [
      '#type' => 'hidden',
      '#value' => $this->responseoptionUri,
    ];

    // Add a select box to choose between URL and Upload.
    $form['responseoption_image_type'] = [
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
    $form['responseoption_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="responseoption_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted responseoption URI for file uploads)
    $modUri = (explode(":/", utils::namespaceUri($this->responseoptionUri)))[1];
    $form['responseoption_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="responseoption_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['responseoption_image_upload_wrapper']['responseoption_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'], // Adjust allowed extensions as needed.
        'file_validate_size' => [2097152],
      ],
    ];

    // Add a select box to choose between URL and Upload.
    $form['responseoption_webdocument_type'] = [
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
    $form['responseoption_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="responseoption_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted responseoption URI for file uploads)
    $form['responseoption_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="responseoption_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['responseoption_webdocument_upload_wrapper']['responseoption_webdocument_upload'] = [
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
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('responseoption_content')) < 1) {
        $form_state->setErrorByName('responseoption_content', $this->t('Please enter a valid content'));
      }
      if(strlen($form_state->getValue('responseoption_language')) < 1) {
        $form_state->setErrorByName('responseoption_language', $this->t('Please enter a valid language'));
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
      if ($this->getCodebookSlotUri() == "") {
        self::backUrl();
        return;
      } else {
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }
    }

    try {
      $useremail = \Drupal::currentUser()->getEmail();

      // $newResponseOptionUri = Utils::uriGen('responseoption');
      $newResponseOptionUri = $form_state->getValue('responseoption_uri');

      // Determine the chosen document type.
      $doc_type = $form_state->getValue('responseoption_webdocument_type');
      $responseoption_webdocument = '';

      // If user selected URL, use the textfield value.
      if ($doc_type === 'url') {
        $responseoption_webdocument = $form_state->getValue('responseoption_webdocument_url');
      }
      // If user selected Upload, load the file entity and get its filename.
      elseif ($doc_type === 'upload') {
        // Get the file IDs from the managed_file element.
        $fids = $form_state->getValue('responseoption_webdocument_upload');
        if (!empty($fids)) {
          // Load the first file (file ID is returned, e.g. "374").
          $file = File::load(reset($fids));
          if ($file) {
            // Mark the file as permanent and save it.
            $file->setPermanent();
            $file->save();
            // Optionally register file usage to prevent cleanup.
            \Drupal::service('file.usage')->add($file, 'sir', 'responseoption', 1);
            // Now get the filename from the file entity.
            $responseoption_webdocument = $file->getFilename();
          }
        }
      }

      // Determine the chosen image type.
      $image_type = $form_state->getValue('responseoption_image_type');
      $responseoption_image = '';

      // If user selected URL, use the textfield value.
      if ($image_type === 'url') {
        $responseoption_image = $form_state->getValue('responseoption_image_url');
      }
      // If user selected Upload, load the file entity and get its filename.
      elseif ($image_type === 'upload') {
        // Get the file IDs from the managed_file element.
        $fids = $form_state->getValue('responseoption_image_upload');
        if (!empty($fids)) {
          // Load the first file (file ID is returned, e.g. "374").
          $file = File::load(reset($fids));
          if ($file) {
            // Mark the file as permanent and save it.
            $file->setPermanent();
            $file->save();
            // Optionally register file usage to prevent cleanup.
            \Drupal::service('file.usage')->add($file, 'sir', 'responseoption', 1);
            // Now get the filename from the file entity.
            $responseoption_image = $file->getFilename();
          }
        }
      }

      $responseOptionJSON = '{"uri":"'.$newResponseOptionUri.'",'.
        '"typeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
        '"hascoTypeUri":"'.VSTOI::RESPONSE_OPTION.'",'.
        '"hasStatus":"'.VSTOI::DRAFT.'",'.
        '"hasContent":"'.$form_state->getValue('responseoption_content').'",'.
        '"hasLanguage":"'.$form_state->getValue('responseoption_language').'",'.
        '"hasVersion":"'.$form_state->getValue('responseoption_version').'",'.
        '"comment":"'.$form_state->getValue('responseoption_description').'",'.
        '"hasWebDocument":"' . $responseoption_webdocument . '",' .
        '"hasImageUri":"' . $responseoption_image . '",' .
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      $api = \Drupal::service('rep.api_connector');
      $api->responseOptionAdd($responseOptionJSON);
      if ($this->getCodebookSlotUri() != NULL && $this->getCodebookSlot() != NULL && $this->getCodebookSlot()->belongsTo != NULL) {
        $api->responseOptionAttach($newResponseOptionUri,$this->getCodebookSlotUri());
      }

      \Drupal::messenger()->addMessage(t("Response Option has been added successfully."));
      if ($this->getCodebookSlotUri() == "") {
        self::backUrl();
        return;
      } else {
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while adding the Response Option: ".$e->getMessage()));
      if ($this->getCodebookSlotUri() == "") {
        self::backUrl();
        return;
      } else {
        $url = Url::fromRoute('sir.edit_codebook_slot');
        $url->setRouteParameter('codebooksloturi', base64_encode($this->getCodebookSlotUri()));
        $form_state->setRedirectUrl($url);
        return;
      }
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_response_option');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }



}
