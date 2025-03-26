<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\file\Entity\File;

class AddProcessStemForm extends FormBase {

  protected $processstemUri;

  public function setProcessStemUri() {
    $this->processstemUri = Utils::uriGen('processstem');
  }

  public function getProcessStemUri() {
    return $this->processstemUri;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_processstem_form';
  }

  protected $sourceProcessStemUri;

  protected $sourceProcessStem;

  public function getSourceProcessStemUri() {
    return $this->sourceProcessStemUri;
  }

  public function setSourceProcessStemUri($uri) {
    return $this->sourceProcessStemUri = $uri;
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
  public function buildForm(array $form, FormStateInterface $form_state, $sourceprocessstemuri = NULL) {

    // Check if the processstem URI already exists in the form state.
    // If not, generate a new URI and store it in the form state.
    if (!$form_state->has('processstem_uri')) {
      $this->setProcessStemUri();
      $form_state->set('processstem_uri', $this->getProcessStemUri());
    }
    else {
      // Retrieve the persisted URI from form state.
      $this->processstemUri = $form_state->get('processstem_uri');
    }

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $form['#attached']['library'][] = 'sir/sir_processstem';

    // ESTABLISH API SERVICE
    $api = \Drupal::service('rep.api_connector');

    // HANDLE SOURCE PROCESS STEM,  IF ANY
    $sourceuri = $sourceprocessstemuri;
    $this->setSourceProcessStemUri($sourceuri);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $derivations = $tables->getGenerationActivities();

    if ($sourceuri === 'DERIVED') unset($derivations[Constant::WGB_ORIGINAL]);

    //SELECT ONE
    if ($languages)
      $languages = ['' => $this->t('Select language please')] + $languages;
    if ($derivations)
      $derivations = ['' => $this->t('Select derivation please')] + $derivations;

    $sourceContent = '';
    if ($this->getSourceProcessStem() != NULL) {
      $sourceContent = Utils::fieldToAutocomplete($this->getSourceProcessStem()->uri,$this->getSourceProcessStem()->hasContent);
    }

    $form['processstem_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-3 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $sourceuri === 'EMPTY' ? $this->t('Parent Type') : $this->t('Derive From'),
        '#name' => 'processstem_type',
        '#default_value' => '',
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
          'autocomplete' => 'off',
        ],
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];
    $form['processstem_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['processstem_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
      '#attributes' => [
        'id' => 'processstem_language'
      ]
    ];
    $form['processstem_version_hidden'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['processstem_version'] = [
      '#type' => 'hidden',
      '#value' => '1',
    ];
    $form['processstem_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    $form['processstem_was_generated_by'] = [
      '#type' => 'select',
      '#title' => $this->t('Was Generated By'),
      '#options' => $derivations,
      '#default_value' => Constant::WGB_ORIGINAL,
      '#disabled' => $sourceuri === 'EMPTY' ? true:false,
      '#attributes' => [
        'id' => 'processstem_was_generated_by'
      ]
    ];

    // Add a hidden field to persist the processstem URI between form rebuilds.
    $form['processstem_uri'] = [
      '#type' => 'hidden',
      '#value' => $this->processstemUri,
    ];

    // Add a select box to choose between URL and Upload.
    $form['processstem_image_type'] = [
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
    $form['processstem_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="processstem_image_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted processstem URI for file uploads)
    $modUri = (explode(":/", utils::namespaceUri($this->processstemUri)))[1];
    $form['processstem_image_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="processstem_image_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['processstem_image_upload_wrapper']['processstem_image_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Image'),
      '#upload_location' => 'private://resources/' . $modUri . '/image',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'], // Adjust allowed extensions as needed.
        'file_validate_size' => [2097152],
      ],
    ];

    // Add a select box to choose between URL and Upload.
    $form['processstem_webdocument_type'] = [
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
    $form['processstem_webdocument_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#attributes' => [
        'placeholder' => 'http://',
      ],
      '#states' => [
        'visible' => [
          ':input[name="processstem_webdocument_type"]' => ['value' => 'url'],
        ],
      ],
    ];

    // Because File Upload Path (use the persisted processstem URI for file uploads)
    $form['processstem_webdocument_upload_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="processstem_webdocument_type"]' => ['value' => 'upload'],
        ],
      ],
    ];
    $form['processstem_webdocument_upload_wrapper']['processstem_webdocument_upload'] = [
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
      if(strlen($form_state->getValue('processstem_language')) < 1) {
        $form_state->setErrorByName('processstem_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('processstem_was_generated_by')) < 1) {
        $form_state->setErrorByName('processstem_was_generated_by', $this->t('Please select a derivation'));
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
    $sourceuri = $this->getSourceProcessStemUri();

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    $api = \Drupal::service('rep.api_connector');

    try {
      $useremail = \Drupal::currentUser()->getEmail();
      // $newProcessStemUri = Utils::uriGen('processstem');
      $newProcessStemUri = $form_state->getValue('processstem_uri');

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

      // CREATE A NEW PROCESS
      // #1 CENARIO - ADD PROCESS NO DERIVED FROM
      if ($sourceuri === 'EMPTY') {
        $processStemJson = '{"uri":"'.$newProcessStemUri.'",'.
          '"superUri":"'.UTILS::uriFromAutocomplete($form_state->getValue('processstem_type')).'",'.
          '"label":"'.$form_state->getValue('processstem_content').'",'.
          '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
          '"hasStatus":"'.VSTOI::DRAFT.'",'.
          '"hasContent":"'.$form_state->getValue('processstem_content').'",'.
          '"hasLanguage":"'.$form_state->getValue('processstem_language').'",'.
          '"hasVersion":"'.$form_state->getValue('processstem_version').'",'.
          '"comment":"'.$form_state->getValue('processstem_description').'",'.
          '"hasWebDocument":"' . $processstem_webdocument . '",' .
          '"hasImageUri":"' . $processstem_image . '",' .
          '"wasGeneratedBy":"'.$form_state->getValue('processstem_was_generated_by').'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';

        $api->elementAdd('processstem', $processStemJson);

      } else {
        // #2 CENARIO - ADD PROCESS THAT WAS DERIVED FROM
        // DERIVED FROM VALUES
        $parentResult = '';
        $rawresponse = $api->getUri(UTILS::uriFromAutocomplete($form_state->getValue('processstem_type')));
        $obj = json_decode($rawresponse);
        if ($obj->isSuccessful) {
          $parentResult = $obj->body;
        }

        //dpm($parentResult);
        /* NOTES:
          IF Derivation is Specialization the element is a CHILD of the Derivation
          IF Derivation is a Refinement the element keeps the same dependency has the previous version element
          IF Translation, must have a differente Language but keeps the same dependency of the previous/new element
        */
        if ($parentResult !== '') {

          $processStemJson = '{"uri":"'.$newProcessStemUri.'",'.
            '"superUri":"'.($form_state->getValue('processstem_was_generated_by') === Constant::WGB_SPECIALIZATION ? UTILS::uriFromAutocomplete($form_state->getValue('processstem_type')) : $parentResult->superUri).'",'.
            '"label":"'.$form_state->getValue('processstem_content').'",'.
            '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
            '"hasStatus":"'.VSTOI::DRAFT.'",'.
            '"hasContent":"'.$form_state->getValue('processstem_content').'",'.
            '"hasLanguage":"'.$form_state->getValue('processstem_language').'",'.
            '"hasVersion":"'.$form_state->getValue('processstem_version').'",'.
            '"comment":"'.$form_state->getValue('processstem_description').'",'.
            '"hasWebDocument":"' . $processstem_webdocument . '",' .
            '"hasImageUri":"' . $processstem_image . '",' .
            '"wasDerivedFrom":"'.UTILS::uriFromAutocomplete($form_state->getValue('processstem_type')).'",'.
            '"wasGeneratedBy":"'.$form_state->getValue('processstem_was_generated_by').'",'.
            '"hasSIRManagerEmail":"'.$useremail.'"}';

          $api->elementAdd('processstem', $processStemJson);

        } else {
          \Drupal::messenger()->addError(t("An error occurred while getting Derived From element"));
          self::backUrl();
          return;
        }
      }

      \Drupal::messenger()->addMessage(t("Added a new Process Stem with URI: ".$newProcessStemUri));
      self::backUrl();
      return;

    } catch(\Exception $e) {
        \Drupal::messenger()->addError(t("An error occurred while adding the Process Stem: ".$e->getMessage()));
        self::backUrl();
        return;
      }
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_processstem');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

}
