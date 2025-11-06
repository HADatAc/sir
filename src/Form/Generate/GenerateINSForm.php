<?php

namespace Drupal\sir\Form\Generate;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\HASCO;
use Drupal\rep\Constant;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Provides a form for registering an INS generation request (GRAXIOM project).
 *
 * NEW WORKFLOW (as requested):
 * - No immediate file download from the external API.
 * - On submit:
 *   1) Create a placeholder Drupal File entity (no physical XLSX yet).
 *   2) Create DATAFILE (DFL) entry referencing that File entity and filename.
 *   3) Create INS (MT element) entry referencing the DATAFILE.
 *   4) Trigger the appropriate generateMT* API call so the backend can
 *      asynchronously produce the real file later.
 *
 * The actual binary file will only exist when the user explicitly requests it
 * through a separate flow/endpoint.
 */
class GenerateInsForm extends FormBase {

  /**
   * Machine element type (slug used by backend/services and directories).
   *
   * @var string
   */
  protected $elementType = 'ins';

  /**
   * Canonical type URI for INS in the HASCO vocabulary.
   *
   * @var string
   */
  protected $elementTypeUri = HASCO::INS;

  /**
   * Human-readable element name (for UI messages).
   *
   * @var string
   */
  protected $elementName = 'INS';

  /**
   * @param string $elementType
   */
  private function setElementType($elementType) {
    $this->elementType = $elementType;
  }

  /**
   * @return string
   */
  private function getElementType() {
    return $this->elementType;
  }

  /**
   * @param string $elementTypeUri
   */
  private function setElementTypeUri($elementTypeUri) {
    $this->elementTypeUri = $elementTypeUri;
  }

  /**
   * @return string
   */
  private function getElementTypeUri() {
    return $this->elementTypeUri;
  }

  /**
   * @return string
   */
  private function getElementName() {
    return $this->elementName;
  }

  /**
   * @param string $elementName
   */
  private function setElementName($elementName) {
    $this->elementName = $elementName;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'generate_ins_form';
  }

  /**
   * Builds the form UI.
   *
   * Left column (col-4): main controls.
   * Right column (col-8): reserved for future use (help, logs, etc.).
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Attach required libraries (modal and Drupal dialog).
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    // Wrap the form with a Bootstrap-like grid layout.
    $form['#prefix'] = '<div class="row justify-content-center"><div class="col-4">';
    $form['#suffix'] = '</div><div class="col-8"></div></div>';

    // Ensure configuration for this form.
    $this->setElementType('ins');         // Slug for backend/paths (lowercase).
    $this->setElementTypeUri(HASCO::INS); // Canonical INS type URI.
    $this->setElementName('INS');         // Human-readable label.

    // Main select box with three generation modes.
    $form['option_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select generation mode'),
      '#options' => [
        'instrument'  => $this->t('INS per instrument'),
        'status'      => $this->t('INS by status'),
        'user_status' => $this->t('INS by user and by status'),
      ],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateForm',
        'wrapper' => 'additional-fields-wrapper',
        'event' => 'change',
      ],
      '#empty_option' => $this->t('- Select -'),
    ];

    // Container updated dynamically based on selected mode.
    $form['additional_fields'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'additional-fields-wrapper'],
      '#tree' => TRUE,
    ];

    $selected = $form_state->getValue('option_select');

    if (!empty($selected)) {
      // Common logical filename (no binary yet).
      $form['additional_fields']['filename'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Logical filename'),
        '#description' => $this->t('Enter the desired logical filename for the INS file (must end with .xlsx). The physical file will be created later when the generation job is processed.'),
        '#required' => TRUE,
      ];

      switch ($selected) {
        case 'instrument':
          // Instrument selector with modal tree.
          $form['additional_fields']['instrument'] = [
            'top' => [
              '#type' => 'markup',
              '#markup' => '<div class="col border border-white">',
            ],
            'main' => [
              '#type' => 'textfield',
              '#title' => $this->t('Select instrument'),
              '#default_value' => '',
              '#id' => 'instrument_type',
              '#required' => TRUE,
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
          break;

        case 'status':
          // Status selector.
          $form['additional_fields']['status'] = [
            '#type' => 'select',
            '#title' => $this->t('Status'),
            '#options' => [
              VSTOI::DRAFT        => $this->t('Draft'),
              VSTOI::UNDER_REVIEW => $this->t('Under review'),
              VSTOI::CURRENT      => $this->t('Current'),
              VSTOI::DEPRECATED   => $this->t('Deprecated'),
            ],
            '#required' => TRUE,
          ];
          break;

        case 'user_status':
          // Status selector.
          $form['additional_fields']['status'] = [
            '#type' => 'select',
            '#title' => $this->t('Status'),
            '#options' => [
              VSTOI::DRAFT        => $this->t('Draft'),
              VSTOI::UNDER_REVIEW => $this->t('Under review'),
              VSTOI::CURRENT      => $this->t('Current'),
              VSTOI::DEPRECATED   => $this->t('Deprecated'),
            ],
            '#required' => TRUE,
          ];

          // Active user emails.
          $user_options = [];
          $users = \Drupal::entityTypeManager()
            ->getStorage('user')
            ->loadByProperties(['status' => 1]);

          foreach ($users as $user) {
            $user_options[$user->getEmail()] = $user->getDisplayName() . ' [' . $user->getEmail() . ']';
          }

          $form['additional_fields']['user_email'] = [
            '#type' => 'select',
            '#title' => $this->t('User email'),
            '#options' => $user_options,
            '#required' => TRUE,
          ];
          break;
      }
    }

    // Media folder (logical grouping for generated artifacts).
    $form['mediafolder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Media folder name'),
      '#description' => $this->t('Enter the logical media folder name associated with this INS generation request.'),
      '#states' => [
        'visible' => [
          [':input[name="option_select"]' => ['value' => 'instrument']],
          'or',
          [':input[name="option_select"]' => ['value' => 'status']],
          'or',
          [':input[name="option_select"]' => ['value' => 'user_status']],
        ],
        'required' => [
          [':input[name="option_select"]' => ['value' => 'instrument']],
          'or',
          [':input[name="option_select"]' => ['value' => 'status']],
          'or',
          [':input[name="option_select"]' => ['value' => 'user_status']],
        ],
      ],
    ];

    // Verify URI flag.
    $form['verifyuri'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verify URI'),
      '#description' => $this->t('Check to ask the backend to validate the URI consistency for the selected elements.'),
      '#default_value' => FALSE,
      '#states' => [
        'visible' => [
          [':input[name="option_select"]' => ['value' => 'instrument']],
          'or',
          [':input[name="option_select"]' => ['value' => 'status']],
          'or',
          [':input[name="option_select"]' => ['value' => 'user_status']],
        ],
      ],
    ];

    // Actions.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Submit: enabled only when mandatory fields are filled.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Request INS generation'),
      '#states' => [
        'enabled' => [
          'or' => [
            [
              ':input[name="option_select"]' => ['value' => 'instrument'],
              ':input[name="additional_fields[instrument][main]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[filename]"]' => ['filled' => TRUE],
            ],
            [
              ':input[name="option_select"]' => ['value' => 'status'],
              ':input[name="additional_fields[status]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[filename]"]' => ['filled' => TRUE],
            ],
            [
              ':input[name="option_select"]' => ['value' => 'user_status'],
              ':input[name="additional_fields[status]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[user_email]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[filename]"]' => ['filled' => TRUE],
            ],
          ],
        ],
      ],
    ];

    // Cancel: redirect back, no validation.
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => 'cancel',
      '#submit' => ['::cancelForm'],
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback used when option_select changes.
   */
  public function updateForm(array &$form, FormStateInterface $form_state) {
    return $form['additional_fields'];
  }

  /**
   * {@inheritdoc}
   *
   * Validates that the logical filename is present and ends with ".xlsx".
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $selected = $form_state->getValue('option_select');
    if (!empty($selected)) {
      $filename = $form_state->getValue(['additional_fields', 'filename']);

      if (empty($filename)) {
        $form_state->setErrorByName('additional_fields][filename', $this->t('Logical filename is required.'));
      }
      elseif (strtolower(substr($filename, -5)) !== '.xlsx') {
        $form_state->setErrorByName('additional_fields][filename', $this->t('The logical filename must end with .xlsx.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * NEW BEHAVIOR:
   * - Create placeholder File entity (no real binary yet).
   * - Create DATAFILE (DFL) entry referencing that File entity and filename.
   * - Create INS entry referencing the DATAFILE.
   * - Trigger generateMT* API to start the backend generation process.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Cancel button handling is delegated to cancelForm().
    if ($form_state->getTriggeringElement()['#name'] === 'cancel') {
      return;
    }

    $selected = $form_state->getValue('option_select');
    $filename = $form_state->getValue(['additional_fields', 'filename']);
    $mediafolder = $form_state->getValue('mediafolder');
    $verifyuri = (bool) $form_state->getValue('verifyuri');

    if (empty($selected) || empty($filename)) {
      \Drupal::messenger()->addError($this->t('A valid generation mode and logical filename are required.'));
      return;
    }

    $api = \Drupal::service('rep.api_connector');
    $element_type = $this->getElementType();   // 'ins'
    $element_label = $this->getElementName();  // 'INS'
    $useremail = \Drupal::currentUser()->getEmail();

    try {
      // Normalize filename (keep .xlsx, sanitize unsafe chars).
      $safe_filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
      if (strtolower(substr($safe_filename, -5)) !== '.xlsx') {
        $safe_filename .= '.xlsx';
      }

      // Build a logical URI for the (future) file.
      $destination_dir = 'private://' . $element_type;

      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      // Prepare directory (this only creates the folder, not the file).
      $file_system->prepareDirectory($destination_dir, FileSystemInterface::CREATE_DIRECTORY);

      $uri = $destination_dir . '/' . $safe_filename;

      // 1) Create a placeholder File entity WITHOUT writing the physical file.
      //    This acts as a reference holder. The actual binary will be created
      //    by the generation/download process executed later.
      /** @var \Drupal\file\Entity\File $file_entity */
      $file_entity = File::create([
        'uri' => $uri,
        'filename' => $safe_filename,
        // Status can remain temporary; set to 0 so it is not treated as a
        // fully available managed file until the real content exists.
        'status' => 0,
      ]);
      $file_entity->save();

      $file_id = $file_entity->id();

      // 2) Build a context-aware label.
      switch ($selected) {
        case 'instrument':
          $context_label = 'INS generation requested per instrument';
          break;
        case 'status':
          $context_label = 'INS generation requested by status';
          break;
        case 'user_status':
          $context_label = 'INS generation requested by user & status';
          break;
        default:
          $context_label = 'INS generation request';
      }

      $basename_no_ext = pathinfo($safe_filename, PATHINFO_FILENAME);
      $label = $context_label . ' - ' . ucfirst($basename_no_ext);

      // 3) Create DATAFILE URI and JSON (DFL).
      $newDataFileUri = Utils::uriGen('datafile');

      $datafileJSON = json_encode([
        'uri' => $newDataFileUri,
        'typeUri' => HASCO::DATAFILE,
        'hascoTypeUri' => HASCO::DATAFILE,
        'label' => $label,
        'filename' => $safe_filename,
        // Mark as "requested" or similar. Adjust constant if you have a specific one.
        'fileStatus' => Constant::FILE_STATUS_WORKING,
        'hasSIRManagerEmail' => $useremail,
        'id' => $file_id,
      ]);

      // 4) Create INS (MT element) URI and JSON, linked to DATAFILE.
      $newMTUri = str_replace('DFL', Utils::elementPrefix($element_type), $newDataFileUri);

      $mtData = [
        'uri' => $newMTUri,
        'typeUri' => $this->getElementTypeUri(),
        'hascoTypeUri' => $this->getElementTypeUri(),
        'label' => $label,
        'hasDataFileUri' => $newDataFileUri,
        'hasVersion' => '',
        'comment' => 'INS generation requested via GenerateInsForm.',
        'hasSIRManagerEmail' => $useremail,
      ];
      $mtJSON = json_encode($mtData);

      // 5) Persist DATAFILE and INS entries via the API connector.
      $msg_datafile = $api->parseObjectResponse($api->datafileAdd($datafileJSON), 'datafileAdd');
      $msg_ins = $api->parseObjectResponse($api->elementAdd($element_type, $mtJSON), 'elementAdd');

      if ($msg_datafile === NULL || $msg_ins === NULL) {
        $error = trim(($msg_datafile ?? '') . ' ' . ($msg_ins ?? ''));
        \Drupal::messenger()->addError($this->t('Failed to register the INS metadata (DATAFILE/INS). @err', [
          '@err' => $error ?: 'No detailed error message returned.',
        ]));
        $this->backUrl();
        return;
      }

      // 6) Trigger the appropriate generateMT* call.
      // IMPORTANT:
      // This call is expected to be asynchronous / fire-and-forget.
      // It should use $newMTUri and/or $newDataFileUri so the backend
      // knows which records and filename to populate later.
      $generateResponse = NULL;

      switch ($selected) {
        case 'instrument':
          $instrument_uri = Utils::uriFromAutocomplete(
            $form_state->getValue(['additional_fields', 'instrument', 'main'])
          );

          $generateResponse = $api->generateMTPerInstrument(
            $element_type,
            $instrument_uri,
            $newMTUri,
            $newDataFileUri,
            $mediafolder,
            $verifyuri,
            $newMTUri
          );
          break;

        case 'status':
          $status = $form_state->getValue(['additional_fields', 'status']);
          $generateResponse = $api->generateMTPerStatus(
            $element_type,
            $status,
            $newMTUri,
            $newDataFileUri,
            $mediafolder,
            $verifyuri,
            $newMTUri
          );
          break;

        case 'user_status':
          $status = $form_state->getValue(['additional_fields', 'status']);
          $user_email = $form_state->getValue(['additional_fields', 'user_email']);
          $generateResponse = $api->generateMTPerUserStatus(
            $element_type,
            $user_email,
            $status,
            $newMTUri,
            $newDataFileUri,
            $mediafolder,
            $verifyuri,
            $newMTUri
          );
          break;
      }

      // 7) Handle generator response in a tolerant way (no file expected).
      if ($generateResponse) {
        \Drupal::messenger()->addMessage($this->t('INS generation request has been registered and sent to the generator service.'));
      }
      else {
        \Drupal::messenger()->addWarning($this->t('INS metadata was registered, but the generator service did not return a confirmation. Please verify the generator logs or configuration.'));
      }

    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('An unexpected error occurred while processing the INS generation request: @msg', [
        '@msg' => $e->getMessage(),
      ]));
      $this->backUrl();
      return;
    }

    // Redirect to the INS listing/table so the user can see the new entry.
    $parameters = [
      'elementtype' => 'ins',
      'mode' => 'table',
      'page' => '1',
      'pagesize' => '10',
      'studyuri' => 'none',
    ];

    $url = Url::fromRoute('rep.select_mt_element', $parameters);
    $response = new RedirectResponse($url->toString());
    $response->send();
  }

  /**
   * Cancel button submit callback.
   *
   * Redirects back without persisting anything.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $this->backUrl();
  }

  /**
   * Redirects to the previously tracked URL or home as a fallback.
   */
  public function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.generate_ins');

    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
    }
    else {
      $response = new RedirectResponse('/');
    }

    $response->send();
  }

}
