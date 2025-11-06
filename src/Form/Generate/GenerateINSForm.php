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
use Psr\Http\Message\ResponseInterface;

/**
 * Provides a form for generating INS (GRAXIOM project).
 */
class GenerateInsForm extends FormBase {

  /**
   * Machine element type (slug used by backend/services and directories).
   * Use lowercase 'ins' for API calls and storage paths.
   *
   * @var string
   */
  var $elementType = 'ins';

  /**
   * Canonical type URI for INS in the HASCO vocabulary.
   *
   * @var string
   */
  var $elementTypeUri = HASCO::INS;

  /**
   * Human-readable element name (for UI messages).
   *
   * @var string
   */
  var $elementName = 'INS';

  private function setElementType($elementType) {
    $this->elementType = $elementType;
  }
  private function getElementType() {
    return $this->elementType;
  }
  private function setElementTypeUri($elementTypeUri) {
    $this->elementTypeUri = $elementTypeUri;
  }
  private function getElementTypeUri() {
    return $this->elementTypeUri;
  }
  private function getElementName() {
    return $this->elementName;
  }
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
   * - The left column (col-4) shows the dynamic fields.
   * - The right column (col-8) is left free for future use (e.g., help panel).
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Attach required libraries (modal and Drupal dialog).
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    // Wrap the form with a Bootstrap grid layout.
    $form['#prefix'] = '<div class="row justify-content-center"><div class="col-4">';
    $form['#suffix'] = '</div><div class="col-8"></div></div>';

    // Ensure type, URI and label are consistent for this form.
    $this->setElementType('ins');       // slug for backend/paths (lowercase)
    $this->setElementTypeUri(HASCO::INS);
    $this->setElementName('INS');       // label for UI

    // Main select box with three generation options.
    $form['option_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select option'),
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

    // Container for dynamic fields depending on the selected option.
    $form['additional_fields'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'additional-fields-wrapper'],
      '#tree' => TRUE,
    ];

    // Determine which option is selected.
    $selected = $form_state->getValue('option_select');

    // Display additional fields only when an option is selected.
    if (!empty($selected)) {
      // Common filename field (always shown when an option is selected).
      $form['additional_fields']['filename'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Filename'),
        '#description' => $this->t('Enter the desired filename for the generated XLSX (must end with .xlsx).'),
        '#required' => TRUE,
      ];

      switch ($selected) {
        case 'instrument':
          // Nested structure: final element name is additional_fields[instrument][main]
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
              // These attributes trigger a modal tree selector (custom library).
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
          // Simple status dropdown: additional_fields[status]
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
          // Status dropdown for user+status: additional_fields[status]
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
          // Active users select: additional_fields[user_email]
          $user_options = [];
          $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['status' => 1]);
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

    // Actions container.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Submit button: enabled only when the required fields for each option are filled.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
      '#states' => [
        'enabled' => [
          'or' => [
            // INS per instrument requires instrument and filename.
            [
              ':input[name="option_select"]' => ['value' => 'instrument'],
              ':input[name="additional_fields[instrument][main]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[filename]"]' => ['filled' => TRUE],
            ],
            // INS by status requires status and filename.
            [
              ':input[name="option_select"]' => ['value' => 'status'],
              ':input[name="additional_fields[status]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[filename]"]' => ['filled' => TRUE],
            ],
            // INS by user and status requires status, user_email, and filename.
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

    // Cancel button: skips validation and redirects back.
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
   * AJAX callback to update dynamic fields when the option changes.
   */
  public function updateForm(array &$form, FormStateInterface $form_state) {
    return $form['additional_fields'];
  }

  /**
   * {@inheritdoc}
   *
   * Validates that the filename is provided and ends with ".xlsx".
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $selected = $form_state->getValue('option_select');
    if (!empty($selected)) {
      $filename = $form_state->getValue(['additional_fields', 'filename']);
      if (empty($filename)) {
        $form_state->setErrorByName('filename', $this->t('Filename is required.'));
      }
      elseif (strtolower(substr($filename, -5)) !== '.xlsx') {
        $form_state->setErrorByName('filename', $this->t('The filename must end with .xlsx.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Submits the generation request to the API, captures the file payload
   * (without forcing a browser download), persists it as a managed File entity,
   * and registers corresponding DATAFILE + INS entries via the API connector.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Read selected option and common filename.
    $selected = $form_state->getValue('option_select');
    $filename = $form_state->getValue(['additional_fields', 'filename']);

    // API service (custom connector).
    $api_service = \Drupal::service('rep.api_connector');

    $result = NULL;

    // Dispatch generation according to the selected option.
    switch ($selected) {
      case 'instrument':
        $instrument = Utils::uriFromAutocomplete($form_state->getValue(['additional_fields', 'instrument', 'main']));
        $result = $api_service->generateMTPerInstrument('ins', $instrument, $filename);
        break;

      case 'status':
        $status = $form_state->getValue(['additional_fields', 'status']);
        $result = $api_service->generateMTPerStatus('ins', $status, $filename, '', '');
        break;

      case 'user_status':
        $status = $form_state->getValue(['additional_fields', 'status']);
        $user_email = $form_state->getValue(['additional_fields', 'user_email']);
        $result = $api_service->generateMTPerUserStatus('ins', $user_email, $status, $filename);
        break;

      default:
        \Drupal::messenger()->addWarning($this->t('No option was selected.'));
        return;
    }

    // If the API returns a JSON envelope with an isSuccessful flag, proceed.
    $decoded = is_string($result) ? json_decode($result) : null;
    if ($decoded && isset($decoded->isSuccessful) && $decoded->isSuccessful === true) {

      try {
        $useremail = \Drupal::currentUser()->getEmail();
        $element_type = $this->getElementType();   // 'ins'
        $element_label = $this->getElementName();  // 'INS'

        // 1) Extract binary payload from the API result.
        //    Supported cases:
        //    - JSON { fileContentBase64, filename, ... }
        //    - PSR-7 ResponseInterface with binary body
        //    - Raw binary string (fallback)
        $binary = null;
        $api_meta_arr = json_decode($result, true);

        // Prefer API-provided filename; fallback to form filename or timestamped default.
        $final_filename = $filename ?: ('ins_export_' . \Drupal::time()->getRequestTime() . '.xlsx');

        if (is_array($api_meta_arr) && isset($api_meta_arr['fileContentBase64'])) {
          $binary = base64_decode($api_meta_arr['fileContentBase64']);
          if (!empty($api_meta_arr['filename'])) {
            $final_filename = $api_meta_arr['filename'];
          }
        }
        elseif ($result instanceof ResponseInterface) {
          $binary = (string) $result->getBody();
        }
        else {
          $binary = (string) $result;
        }

        if ($binary === null || $binary === '') {
          \Drupal::messenger()->addError($this->t('The API did not return a file payload.'));
          return;
        }

        // 2) Build a private destination directory: private://ins
        $destination_dir = 'private://' . $element_type;

        /** @var \Drupal\Core\File\FileSystemInterface $file_system */
        $file_system = \Drupal::service('file_system');

        if (!$file_system->prepareDirectory($destination_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY)) {
          \Drupal::messenger()->addError($this->t('The upload directory could not be prepared: @dir', ['@dir' => $destination_dir]));
          return;
        }

        // 3) Persist as a managed file (no temp file, no $_FILES).
        $safe_filename = $file_system->basename($final_filename);
        $uri = $destination_dir . '/' . $safe_filename;

        /** @var \Drupal\file\FileRepositoryInterface $file_repository */
        $file_repository = \Drupal::service('file.repository');

        /** @var \Drupal\file\Entity\File $file_entity */
        $file_entity = $file_repository->writeData($binary, $uri, \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
        if (!$file_entity) {
          \Drupal::messenger()->addError($this->t('Could not persist file to @uri.', ['@uri' => $uri]));
          return;
        }

        $file_entity->setPermanent();
        $file_entity->save();

        $file_id = $file_entity->id();
        $final_filename = $file_entity->getFilename();
        $drupal_uri = $file_entity->getFileUri();

        // 4) Build a context-aware label for the entries (no mt_* fields in this form).
        $context_label = 'INS export';
        switch ($selected) {
          case 'instrument':
            $context_label = 'INS per instrument';
            break;
          case 'status':
            $context_label = 'INS by status';
            break;
          case 'user_status':
            $context_label = 'INS by user & status';
            break;
        }

        // Remove the extension from the filename (handles multi-dot names like "report.v1.xlsx")
        $basename_no_ext = pathinfo($final_filename, PATHINFO_FILENAME);

        // Build label without extension
        $label = $context_label . ' - ' . $basename_no_ext;

        // 5) Create DATAFILE JSON entry.
        $newDataFileUri = Utils::uriGen('datafile');
        $datafileJSON = json_encode([
          "uri" => $newDataFileUri,
          "typeUri" => HASCO::DATAFILE,
          "hascoTypeUri" => HASCO::DATAFILE,
          "label" => $label,
          "filename" => $final_filename,
          "fileStatus" => Constant::FILE_STATUS_UNPROCESSED,
          "hasSIRManagerEmail" => $useremail,
          "id" => $file_id,
        ]);

        // 6) Create INS (MT element) JSON entry.
        $newMTUri = str_replace("DFL", Utils::elementPrefix($element_type), $newDataFileUri);
        $mtData = [
          "uri" => $newMTUri,
          // If your backend strictly requires canonical URIs, keep elementTypeUri below.
          "typeUri" => $this->getElementTypeUri(),
          "hascoTypeUri" => $this->getElementTypeUri(),
          "label" => $label,
          "hasDataFileUri" => $newDataFileUri,
          "hasVersion" => "",
          "comment" => "Generated via GenerateInsForm",
          "hasSIRManagerEmail" => $useremail,
        ];
        $mtJSON = json_encode($mtData);

        // 7) Send entries to the API connector.
        $api = \Drupal::service('rep.api_connector');
        $msg1 = $api->parseObjectResponse($api->datafileAdd($datafileJSON), 'datafileAdd');
        $msg2 = $api->parseObjectResponse($api->elementAdd($element_type, $mtJSON), 'elementAdd');

        if ($msg1 != NULL && $msg2 != NULL) {
          \Drupal::messenger()->addMessage($this->t('@name generated and registered successfully.', ['@name' => $element_label]));
        }
        else {
          $error = ($msg1 ?? '') . ' ' . ($msg2 ?? '');
          \Drupal::messenger()->addError($this->t('Something went wrong while registering @name: @err', [
            '@name' => $element_label,
            '@err' => $error,
          ]));
        }

      } catch (\Exception $e) {
        \Drupal::messenger()->addError($this->t('An error occurred while registering @name: @msg', [
          '@name' => $this->getElementName(),
          '@msg' => $e->getMessage(),
        ]));
        self::backUrl();
        return;
      }
    }

    // If we reach here, either the API did not flag success or returned a different shape.
    \Drupal::messenger()->addMessage($this->t('INS file successfully generated.'));
    // Redirect to the INS table view.
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
   * Redirects back without saving.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $this->backUrl();
  }

  /**
   * Redirects the user to the previously tracked URL or to '/' as a fallback.
   */
  public function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.generate_ins');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
    }
    else {
      $response = new RedirectResponse('/');
      $response->send();
    }
  }

}
