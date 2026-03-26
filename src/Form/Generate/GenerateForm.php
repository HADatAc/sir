<?php

namespace Drupal\sir\Form\Generate;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\Hasco as HASCO;
use Drupal\rep\Constant;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;

/**
 * Generic form for registering a Metadata Template (MT) generation request.
 *
 * New workflow:
 * - No immediate file download from the external API.
 * - On submit:
 *   1) Create a placeholder Drupal File entity (no physical XLSX yet).
 *   2) Create DATAFILE (DFL) referencing that File entity and filename.
 *   3) Create MT element referencing the DATAFILE.
 *   4) Trigger the appropriate generateMT* call (async).
 *
 * Fields shown depend on the {elementtype} route param.
 * Supported types: instrument/ins, dsg, dd, sdd, dp2, str, kgr.
 *
 * For KGR:
 * - Specific modes are only shown if module "socialm" is enabled and
 *   elementtype = "kgr".
 */
class GenerateForm extends FormBase {

  /**
   * Machine element type (slug used by backend/services and directories).
   *
   * @var string
   */
  protected $elementType = 'ins';

  /**
   * Canonical type URI for this MT element in HASCO vocabulary (if available).
   *
   * @var string|null
   */
  protected $elementTypeUri = NULL;

  /**
   * Human-readable element name (for UI messages), e.g. "INS", "DSG", "KGR".
   *
   * @var string
   */
  protected $elementName = 'Instrument';

  /**
   * Resolve a HASCO URI constant if it exists; otherwise return NULL.
   */
  private function resolveHascoUri($code) {
    $const = HASCO::class . '::' . strtoupper($code);
    return defined($const) ? constant($const) : NULL;
  }

  /**
   * Initialize element definition from the route parameter.
   */
  private function initElementDefinition($elementTypeFromRoute = NULL) {
    $slug = strtolower($elementTypeFromRoute ?: 'Instrument');

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument') ?? 'instrument';

    switch ($slug) {
      case 'ins':
        $this->setElementType('ins');
        $this->setElementName(ucfirst($preferred_instrument));
        $this->setElementTypeUri($this->resolveHascoUri('INS'));
        break;

      case 'dsg':
        $this->setElementType('dsg');
        $this->setElementName('DSG');
        $this->setElementTypeUri($this->resolveHascoUri('DSG'));
        break;

      case 'dd':
        $this->setElementType('dd');
        $this->setElementName('DD');
        $this->setElementTypeUri($this->resolveHascoUri('DD'));
        break;

      case 'sdd':
        $this->setElementType('sdd');
        $this->setElementName('SDD');
        $this->setElementTypeUri($this->resolveHascoUri('SDD'));
        break;

      case 'dp2':
        $this->setElementType('dp2');
        $this->setElementName('DP2');
        $this->setElementTypeUri($this->resolveHascoUri('DP2'));
        break;

      case 'str':
        $this->setElementType('str');
        $this->setElementName('STR');
        $this->setElementTypeUri($this->resolveHascoUri('STR'));
        break;

      case 'kgr':
        // KGR generic MT element. If HASCO::KGR exists, use it; otherwise
        // it will fall back to HASCO::MT later on submit.
        $this->setElementType('kgr');
        $this->setElementName('KGR');
        $this->setElementTypeUri($this->resolveHascoUri('KGR'));
        break;

      case 'wkf':
        $this->setElementType('wkf');
        $this->setElementName('WKF');
        $this->setElementTypeUri($this->resolveHascoUri('WKF'));
        break;

      default:
        $this->setElementType($slug);
        $this->setElementName(strtoupper($slug));
        $this->setElementTypeUri(NULL);
        break;
    }
  }

  private function setElementType($elementType) { $this->elementType = $elementType; }
  private function getElementType() { return $this->elementType; }

  private function setElementTypeUri($elementTypeUri) { $this->elementTypeUri = $elementTypeUri; }
  private function getElementTypeUri() { return $this->elementTypeUri; }

  private function setElementName($elementName) { $this->elementName = $elementName; }
  private function getElementName() { return $this->elementName; }

  /**
   * Selector label to show in UI for the current element type.
   */
  private function getSelectorLabelForType() {
    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument') ?? 'instrument';
    $preferred_process = \Drupal::config('rep.settings')->get('preferred_process') ?? 'workflow';
    switch ($this->getElementType()) {
      case 'ins':        return ucfirst($preferred_instrument);
      case 'wkf':        return ucfirst($preferred_process) . ' Stem';
      case 'dsg':        return 'DSG';
      case 'dd':         return 'DD';
      case 'sdd':        return 'SDD';
      case 'dp2':        return 'DP2';
      case 'str':        return 'STR';
      case 'kgr':        return 'KGR';
      default:           return $this->getElementName();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sir_generate_form';
  }

  /**
   * Dynamic page title based on the {elementtype} parameter.
   */
  public static function pageTitle($elementtype) {
    return t('Generate @element file', ['@element' => strtoupper($elementtype)]);
  }

  /**
   * Build the form; field set varies by {elementtype}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype = NULL) {
    $this->initElementDefinition($elementtype);

    $element_label     = $this->getElementName();
    $selector_label    = $this->getSelectorLabelForType();
    $current_type_slug = $this->getElementType();
    $is_instrument     = ($current_type_slug === 'ins');
    $is_wkf            = ($current_type_slug === 'wkf');

    // KGR is only “active” if the socialm module is enabled.
    $is_kgr            = ($current_type_slug === 'kgr' && \Drupal::moduleHandler()->moduleExists('socialm'));

    // Attach required libraries (modal and Drupal dialog).
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    // Layout wrapper.
    $form['#prefix'] = '<div class="row justify-content-center"><div class="col-4">';
    $form['#suffix'] = '</div><div class="col-8"></div></div>';

    // Common submit states placeholder; will be defined by each element type
    // that is actually submittable.
    $submit_states = [];

    /**
     * SWITCH BY ELEMENT TYPE - control the exact fields rendered.
     *
     * - INSTRUMENT: show the "option_select" with modes and dynamic extras.
     * - KGR: show KGR-specific modes (funding scheme, project, org, etc.),
     *        only if module "socialm" is present.
     * - OTHER TYPES: show a simpler set (notice only), no actions.
     */
    if ($is_instrument || $is_wkf) {
      // --- Instrument-specific: with mode select ---
      $form['option_select'] = [
        '#type' => 'select',
        '#title' => $this->t('Select generation mode for @element', ['@element' => $element_label]),
        '#options' => [
          'by_element'  => $this->t('By @selector', ['@selector' => $selector_label]),
          'status'      => $this->t('By Status'),
          'user_status' => $this->t('By User and by Status'),
        ],
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::updateForm',
          'wrapper' => 'additional-fields-wrapper',
          'event' => 'change',
        ],
        '#empty_option' => $this->t('- Select -'),
      ];

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
          '#description' => $this->t('Enter the desired logical filename for the @element file (must end with .xlsx). The physical file will be created later when the generation job is processed.', ['@element' => $element_label]),
          '#required' => TRUE,
        ];

        switch ($selected) {
          case 'by_element':
            $tree_elementtype = $is_wkf ? 'workflowstem' : strtolower($this->getElementName());
            // Instrument selector with modal tree.
            $form['additional_fields']['selector'] = [
              'top' => [
                '#type' => 'markup',
                '#markup' => '<div class="col border border-white">',
              ],
              'main' => [
                '#type' => 'textfield',
                '#title' => $this->t('Select @selector', ['@selector' => $selector_label]),
                '#default_value' => '',
                '#id' => 'selector_type',
                '#required' => TRUE,
                '#attributes' => [
                  'class' => ['open-tree-modal'],
                  'data-dialog-type' => 'modal',
                  'data-dialog-options' => json_encode(['width' => 800]),
                  'data-url' => Url::fromRoute('rep.tree_form', [
                    'mode' => 'modal',
                    'elementtype' => $tree_elementtype,
                  ], ['query' => ['field_id' => 'selector_type']])->toString(),
                  'data-field-id' => 'selector_type',
                  'data-elementtype' => $tree_elementtype,
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

      // Media folder (instrument/WKF: visible in all modes).
      $form['mediafolder'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Media folder name'),
        '#description' => $this->t('Enter the logical media folder name associated with this generation request.'),
        '#states' => [
          'visible' => [
            [':input[name="option_select"]' => ['value' => 'by_element']],
            'or',
            [':input[name="option_select"]' => ['value' => 'status']],
            'or',
            [':input[name="option_select"]' => ['value' => 'user_status']],
          ],
          'required' => [
            [':input[name="option_select"]' => ['value' => 'by_element']],
            'or',
            [':input[name="option_select"]' => ['value' => 'status']],
            'or',
            [':input[name="option_select"]' => ['value' => 'user_status']],
          ],
        ],
      ];

      $form['verifyuri'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Verify URI'),
        '#description' => $this->t('Ask the backend to validate URI consistency for the selected elements.'),
        '#default_value' => FALSE,
        '#states' => [
          'visible' => [
            [':input[name="option_select"]' => ['value' => 'by_element']],
            'or',
            [':input[name="option_select"]' => ['value' => 'status']],
            'or',
            [':input[name="option_select"]' => ['value' => 'user_status']],
          ],
        ],
      ];

      // Submit button states for instrument/WKF.
      $submit_states = [
        'enabled' => [
          'or' => [
            [
              ':input[name="option_select"]' => ['value' => 'by_element'],
              ':input[name="additional_fields[selector][main]"]' => ['filled' => TRUE],
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
      ];
    }
    elseif ($is_kgr) {
      // --- KGR-specific: only if socialm module is enabled ---
      $form['option_select'] = [
        '#type' => 'select',
        '#title' => $this->t('Select KGR generation mode'),
        '#options' => [
          'fundingscheme' => $this->t('KGR per Funding Scheme'),
          'project'       => $this->t('KGR per Project'),
          'organization'  => $this->t('KGR per Organization'),
          'status'        => $this->t('KGR by Status'),
          'user_status'   => $this->t('KGR by User and by Status'),
        ],
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::updateForm',
          'wrapper' => 'additional-fields-wrapper',
          'event' => 'change',
        ],
        '#empty_option' => $this->t('- Select -'),
      ];

      // Additional dynamic fields.
      $form['additional_fields'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'additional-fields-wrapper'],
        '#tree' => TRUE,
      ];

      $selected = $form_state->getValue('option_select');

      if (!empty($selected)) {
        // Logical filename (no binary yet).
        $form['additional_fields']['filename'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Logical filename'),
          '#description' => $this->t('Enter the logical filename for the KGR file (must end with .xlsx). The physical file will be created when the generator finishes.'),
          '#required' => TRUE,
        ];

        switch ($selected) {
          case 'fundingscheme':
            $form['additional_fields']['fundingscheme'] = [
              '#type' => 'textfield',
              '#title' => $this->t('Select Funding Scheme'),
              '#default_value' => '',
              '#autocomplete_route_name' => 'social.autocomplete_fundingscheme',
              '#required' => TRUE,
            ];
            break;

          case 'project':
            $form['additional_fields']['project'] = [
              '#type' => 'textfield',
              '#title' => $this->t('Select Project'),
              '#default_value' => '',
              '#autocomplete_route_name' => 'social.autocomplete_project',
              '#required' => TRUE,
            ];
            break;

          case 'organization':
            $form['additional_fields']['organization'] = [
              '#type' => 'textfield',
              '#title' => $this->t('Select Organization'),
              '#default_value' => '',
              '#autocomplete_route_name' => 'social.autocomplete_organization',
              '#required' => TRUE,
            ];
            break;

          case 'status':
            $form['additional_fields']['status'] = [
              '#type' => 'select',
              '#title' => $this->t('Status'),
              '#options' => [
                VSTOI::DRAFT        => $this->t('Draft'),
                VSTOI::UNDER_REVIEW => $this->t('Under Review'),
                VSTOI::CURRENT      => $this->t('Current'),
                VSTOI::DEPRECATED   => $this->t('Deprecated'),
              ],
              '#required' => TRUE,
            ];
            break;

          case 'user_status':
            $form['additional_fields']['status'] = [
              '#type' => 'select',
              '#title' => $this->t('Status'),
              '#options' => [
                VSTOI::DRAFT        => $this->t('Draft'),
                VSTOI::UNDER_REVIEW => $this->t('Under Review'),
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
              $email = $user->getEmail();
              if (!empty($email)) {
                $user_options[$email] = $user->getDisplayName() . ' [' . $email . ']';
              }
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

      // Media folder for KGR (always visible/required when elementtype = kgr).
      $form['mediafolder'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Media folder name'),
        '#description' => $this->t('Enter the logical media folder name associated with this KGR generation request.'),
        '#required' => TRUE,
      ];

      $form['verifyuri'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Verify URI'),
        '#description' => $this->t('Ask the backend to validate URI consistency for the selected elements.'),
        '#default_value' => FALSE,
      ];

      // Submit states for KGR.
      $submit_states = [
        'enabled' => [
          'or' => [
            [
              ':input[name="option_select"]' => ['value' => 'fundingscheme'],
              ':input[name="additional_fields[fundingscheme]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[filename]"]' => ['filled' => TRUE],
            ],
            [
              ':input[name="option_select"]' => ['value' => 'project'],
              ':input[name="additional_fields[project]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[filename]"]' => ['filled' => TRUE],
            ],
            [
              ':input[name="option_select"]' => ['value' => 'organization'],
              ':input[name="additional_fields[organization]"]' => ['filled' => TRUE],
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
      ];
    }
    else {
      // --- Other element types: simple placeholder, no submit ---
      $form['additional_fields'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'additional-fields-wrapper'],
        '#tree' => TRUE,
      ];

      $form['additional_fields']['notice'] = [
        '#type' => 'markdown',
        '#markup' => $this->t('Element has not fields yet to be presented.'),
        '#attributes' => [
          'class' => ['mb-5'],
        ],
        '#weight' => 10,
      ];
    }

    // Build actions only for element types that are actually submittable.
    if ($is_instrument || $is_wkf || $is_kgr) {
      $form['actions'] = ['#type' => 'actions'];

      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Request @element generation', ['@element' => $element_label]),
        '#states' => $submit_states,
      ];

      $form['actions']['cancel'] = [
        '#type' => 'submit',
        '#value' => $this->t('Cancel'),
        '#name' => 'cancel',
        '#submit' => ['::cancelForm'],
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['btn', 'btn-primary', 'cancel-button']],
      ];
    }

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
   * Validates that the logical filename is present and ends with ".xlsx".
   * Instrument and KGR have their own mode-specific validations.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $element_type = $this->getElementType();
    $selected = $form_state->getValue('option_select');

    // INSTRUMENT/WKF VALIDATION.
    if ($element_type === 'ins' || $element_type === 'wkf') {
      // If option_select is missing (unexpected), assume 'by_element'.
      $selected = $selected ?: 'by_element';

      $filename = $form_state->getValue(['additional_fields', 'filename']);
      if (empty($filename)) {
        $form_state->setErrorByName('additional_fields][filename', $this->t('Logical filename is required.'));
      }
      elseif (strtolower(substr($filename, -5)) !== '.xlsx') {
        $form_state->setErrorByName('additional_fields][filename', $this->t('The logical filename must end with .xlsx.'));
      }

      if ($selected === 'by_element') {
        $selector = $form_state->getValue(['additional_fields', 'selector', 'main']);
        if (empty($selector)) {
          $form_state->setErrorByName('additional_fields][selector][main', $this->t('A valid selector is required.'));
        }
      }
      elseif ($selected === 'status') {
        $status = $form_state->getValue(['additional_fields', 'status']);
        if (empty($status)) {
          $form_state->setErrorByName('additional_fields][status', $this->t('Status is required.'));
        }
      }
      elseif ($selected === 'user_status') {
        $status = $form_state->getValue(['additional_fields', 'status']);
        $user   = $form_state->getValue(['additional_fields', 'user_email']);
        if (empty($status) || empty($user)) {
          $form_state->setErrorByName('additional_fields][user_email', $this->t('Both status and user email are required.'));
        }
      }
    }
    // KGR VALIDATION (only when socialm is enabled).
    elseif ($element_type === 'kgr' && \Drupal::moduleHandler()->moduleExists('socialm')) {
      if (empty($selected)) {
        $form_state->setErrorByName('option_select', $this->t('Please select a KGR generation mode.'));
        return;
      }

      $filename = $form_state->getValue(['additional_fields', 'filename']);
      if (empty($filename)) {
        $form_state->setErrorByName('additional_fields][filename', $this->t('Logical filename is required.'));
      }
      elseif (strtolower(substr($filename, -5)) !== '.xlsx') {
        $form_state->setErrorByName('additional_fields][filename', $this->t('The logical filename must end with .xlsx.'));
      }

      if ($selected === 'fundingscheme') {
        $fs = $form_state->getValue(['additional_fields', 'fundingscheme']);
        if (empty($fs)) {
          $form_state->setErrorByName('additional_fields][fundingscheme', $this->t('Funding Scheme is required.'));
        }
      }
      elseif ($selected === 'project') {
        $proj = $form_state->getValue(['additional_fields', 'project']);
        if (empty($proj)) {
          $form_state->setErrorByName('additional_fields][project', $this->t('Project is required.'));
        }
      }
      elseif ($selected === 'organization') {
        $org = $form_state->getValue(['additional_fields', 'organization']);
        if (empty($org)) {
          $form_state->setErrorByName('additional_fields][organization', $this->t('Organization is required.'));
        }
      }
      elseif ($selected === 'status') {
        $status = $form_state->getValue(['additional_fields', 'status']);
        if (empty($status)) {
          $form_state->setErrorByName('additional_fields][status', $this->t('Status is required.'));
        }
      }
      elseif ($selected === 'user_status') {
        $status = $form_state->getValue(['additional_fields', 'status']);
        $user   = $form_state->getValue(['additional_fields', 'user_email']);
        if (empty($status) || empty($user)) {
          $form_state->setErrorByName('additional_fields][user_email', $this->t('Both status and user email are required.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   * Creates placeholder entities and triggers the generation job.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle cancel button immediately.
    if ($form_state->getTriggeringElement()['#name'] === 'cancel') {
      $this->backUrl();
      return;
    }

    $element_type = $this->getElementType();
    $selected     = $form_state->getValue('option_select') ?: 'by_element';
    $filename     = $form_state->getValue(['additional_fields', 'filename']);
    $mediafolder  = $form_state->getValue('mediafolder');
    $verifyuri    = (bool) $form_state->getValue('verifyuri');
    
    // Convert boolean to string for API endpoints that expect 'true'/'false'.
    $verifyuri_value = $verifyuri ? 'true' : 'false';

    if (empty($filename)) {
      \Drupal::messenger()->addError($this->t('A valid logical filename is required.'));
      return;
    }

    /** @var \Drupal\rep\ApiConnectorServiceInterface $api */
    $api              = \Drupal::service('rep.api_connector');
    $element_label    = $this->getElementName();
    $element_type_uri = $this->getElementTypeUri();
    $useremail        = \Drupal::currentUser()->getEmail();

    try {
      // Normalize filename and ensure .xlsx extension.
      $safe_filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
      if (strtolower(substr($safe_filename, -5)) !== '.xlsx') {
        $safe_filename .= '.xlsx';
      }

      // Target directory based on element type (e.g. private://ins, private://kgr).
      $destination_dir = 'private://' . $element_type;

      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      $file_system->prepareDirectory($destination_dir, FileSystemInterface::CREATE_DIRECTORY);

      $uri = $destination_dir . '/' . $safe_filename;

      // 1) Placeholder File entity (no binary yet).
      /** @var \Drupal\file\Entity\File $file_entity */
      $file_entity = File::create([
        'uri' => $uri,
        'filename' => $safe_filename,
        'status' => 0,
      ]);
      $file_entity->save();
      $file_id = $file_entity->id();

      // 2) Simple label from filename (without extension).
      $basename_no_ext = pathinfo($safe_filename, PATHINFO_FILENAME);
      $label = ucfirst($basename_no_ext);

      // 3) DATAFILE JSON.
      $newDataFileUri = Utils::uriGen('datafile');

      $datafileJSON = json_encode([
        'uri' => $newDataFileUri,
        'typeUri' => HASCO::DATAFILE,
        'hascoTypeUri' => HASCO::DATAFILE,
        'label' => $label,
        'filename' => $safe_filename,
        'fileStatus' => Constant::FILE_STATUS_WORKING,
        'hasSIRManagerEmail' => $useremail,
        'id' => $file_id,
      ]);

      // 4) MT element JSON (prefix depends on element type).
      $newMTUri = str_replace('DFL', Utils::elementPrefix($element_type), $newDataFileUri);
      $resolved_type_uri = $element_type_uri ?: HASCO::MT;

      $mtData = [
        'uri' => $newMTUri,
        'typeUri' => $resolved_type_uri,
        'hascoTypeUri' => $resolved_type_uri,
        'label' => $label,
        'hasDataFileUri' => $newDataFileUri,
        'hasVersion' => '1',
        'comment' => $element_label . ' generation requested via generic GenerateForm.',
        'hasSIRManagerEmail' => $useremail,
      ];
      $mtJSON = json_encode($mtData);

      // 5) Persist metadata.
      $raw_datafile = $api->datafileAdd($datafileJSON);
      $msg_datafile = $api->parseObjectResponse($raw_datafile, 'datafileAdd');
      $err_datafile = trim((string) $api->getErrorMessage());

      $raw_element = $api->elementAdd($element_type, $mtJSON);
      $msg_element = $api->parseObjectResponse($raw_element, 'elementAdd');
      $err_element = trim((string) $api->getErrorMessage());

      if ($msg_datafile === NULL || $msg_element === NULL) {
        $error_parts = [];
        if ($msg_datafile === NULL) {
          $error_parts[] = 'datafileAdd failed' . ($err_datafile ? (': ' . $err_datafile) : '');
        }
        if ($msg_element === NULL) {
          $error_parts[] = 'elementAdd(' . $element_type . ') failed' . ($err_element ? (': ' . $err_element) : '');
        }
        $error = trim(implode(' | ', $error_parts));
        \Drupal::messenger()->addError($this->t('Failed to register the metadata (DATAFILE/element). @err', [
          '@err' => $error ?: 'No detailed error message returned.',
        ]));
        $this->backUrl();
        return;
      }

      // 6) Trigger generator (fire-and-forget).
      $generateResponse = NULL;

      // INSTRUMENT: keep previous behavior.
      if ($element_type === 'ins') {
        if ($selected === 'by_element') {
          $selector_uri = Utils::uriFromAutocomplete(
            $form_state->getValue(['additional_fields', 'selector', 'main'])
          );
          // API method name is historical; it accepts any $element_type.
          $generateResponse = $api->generateMTPerElement(
            'instrument',
            $newDataFileUri,
            $selector_uri,
            $safe_filename,
            $mediafolder,
            $verifyuri_value
          );
        }
        elseif ($selected === 'status') {
          $status = $form_state->getValue(['additional_fields', 'status']);
          $generateResponse = $api->generateMTPerStatus(
            $element_type,
            $newDataFileUri,
            $status,
            $safe_filename,
            $mediafolder,
            $verifyuri_value
          );
        }
        elseif ($selected === 'user_status') {
          $status = $form_state->getValue(['additional_fields', 'status']);
          $user_email = $form_state->getValue(['additional_fields', 'user_email']);
          $generateResponse = $api->generateMTPerUserStatus(
            $element_type,
            $newDataFileUri,
            $user_email,
            $status,
            $safe_filename,
            $mediafolder,
            $verifyuri_value
          );
        }
      }
      // WKF: same modes as INS, but selector comes from workflow stems.
      elseif ($element_type === 'wkf') {
        if ($selected === 'by_element') {
          $selector_uri = Utils::uriFromAutocomplete(
            $form_state->getValue(['additional_fields', 'selector', 'main'])
          );
          $generateResponse = $api->generateMTPerElement(
            $element_type,
            $newDataFileUri,
            $selector_uri,
            $safe_filename,
            $mediafolder,
            $verifyuri_value
          );
        }
        elseif ($selected === 'status') {
          $status = $form_state->getValue(['additional_fields', 'status']);
          $generateResponse = $api->generateMTPerStatus(
            $element_type,
            $newDataFileUri,
            $status,
            $safe_filename,
            $mediafolder,
            $verifyuri_value
          );
        }
        elseif ($selected === 'user_status') {
          $status = $form_state->getValue(['additional_fields', 'status']);
          $user_email = $form_state->getValue(['additional_fields', 'user_email']);
          $generateResponse = $api->generateMTPerUserStatus(
            $element_type,
            $newDataFileUri,
            $user_email,
            $status,
            $safe_filename,
            $mediafolder,
            $verifyuri_value
          );
        }
      }
      // KGR: use the generic generateMTPerElement() with a filter value,
      // only if socialm is enabled.
      elseif ($element_type === 'kgr' && \Drupal::moduleHandler()->moduleExists('socialm')) {
        $filterValue = NULL;

        if ($selected === 'fundingscheme') {
          $fundingscheme_uri = Utils::uriFromAutocomplete(
            $form_state->getValue(['additional_fields', 'fundingscheme'])
          );
          $filterValue = $fundingscheme_uri;
        }
        elseif ($selected === 'project') {
          $project_uri = Utils::uriFromAutocomplete(
            $form_state->getValue(['additional_fields', 'project'])
          );
          $filterValue = $project_uri;
        }
        elseif ($selected === 'organization') {
          $organization_uri = Utils::uriFromAutocomplete(
            $form_state->getValue(['additional_fields', 'organization'])
          );
          $filterValue = $organization_uri;
        }
        elseif ($selected === 'status') {
          $status = $form_state->getValue(['additional_fields', 'status']);
          $filterValue = $status;
          $generateResponse = $api->generateMTPerStatus(
            $element_type,
            $newDataFileUri,
            $status,
            $safe_filename,
            $mediafolder,
            $verifyuri_value
          );
        }
        elseif ($selected === 'user_status') {
          $status     = $form_state->getValue(['additional_fields', 'status']);
          $user_email = $form_state->getValue(['additional_fields', 'user_email']);
          // Encode both status and user in a single string; the backend
          // must parse this convention.
          $filterValue = 'status=' . $status . ';user=' . $user_email;
          $generateResponse = $api->generateMTPerUserStatus(
            $element_type,
            $newDataFileUri,
            $user_email,
            $status,
            $safe_filename,
            $mediafolder,
            $verifyuri_value
          );
        }

        if ($filterValue === NULL) {
          \Drupal::messenger()->addError($this->t('Unable to determine the filter value for KGR generation.'));
          $this->backUrl();
          return;
        }

        $generateResponse = $api->generateMTPerElement(
          $element_type,
          $newDataFileUri,
          $filterValue,
          $safe_filename,
          $mediafolder,
          $verifyuri_value
        );
      }

      // Log the generation response for debugging.
      \Drupal::logger('sir.generate')->info('Generation API called for @type: response=@resp', [
        '@type' => $element_type,
        '@resp' => print_r($generateResponse, TRUE),
      ]);

      if ($generateResponse) {
        // Parse and check if the API returned success.
        $parsed = $api->parseObjectResponse($generateResponse, 'generateMT');
        if ($parsed && isset($parsed->isSuccessful) && $parsed->isSuccessful) {
          \Drupal::messenger()->addMessage($this->t('@element generation request has been registered and sent to the generator service.', ['@element' => $element_label]));
        } else {
          \Drupal::messenger()->addWarning($this->t('@element metadata was registered, but the generator service returned an error or unexpected response.', ['@element' => $element_label]));
          \Drupal::logger('sir.generate')->warning('Generation response not successful: @resp', [
            '@resp' => print_r($parsed, TRUE),
          ]);
        }
      }
      else {
        \Drupal::messenger()->addWarning($this->t('@element metadata was registered, but the generator service did not return a confirmation. Please verify the generator logs or configuration.', ['@element' => $element_label]));
      }

    } catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('An unexpected error occurred while processing the generation request: @msg', ['@msg' => $e->getMessage()]));
      $this->backUrl();
      return;
    }

    // Redirect to listing/table.
    $parameters = [
      'elementtype' => $element_type,
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
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $this->backUrl();
  }

  /**
   * Redirects to the previously tracked URL or home as a fallback.
   */
  public function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $route_name = \Drupal::routeMatch()->getRouteName();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, $route_name);

    $response = $previousUrl ? new RedirectResponse($previousUrl) : new RedirectResponse(Url::fromRoute('rep.home')->toString());
    $response->send();
  }

}
