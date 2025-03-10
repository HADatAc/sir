<?php

namespace Drupal\sir\Form\Generate;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;
use Symfony\Component\HttpFoundation\Response;


/**
 * Provides a form for generating INS (GRAXIOM project).
 */
class GenerateInsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'generate_ins_form';
  }

  /**
   * Builds the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Attach any required libraries.
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    // Wrap the form in a Bootstrap row with a centered col-4 container.
    $form['#prefix'] = '<div class="row justify-content-center"><div class="col-4">';
    $form['#suffix'] = '</div><div class="col-8"></div></div>';

    // Main select box with three options.
    $form['option_select'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Option'),
      '#options' => [
        'instrument' => $this->t('INS per Instrument'),
        'status' => $this->t('INS by Status'),
        'user_status' => $this->t('INS by User and by Status'),
      ],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateForm',
        'wrapper' => 'additional-fields-wrapper',
        'event' => 'change',
      ],
      '#empty_option' => $this->t('- Select -'),
    ];

    // Container for additional fields.
    $form['additional_fields'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'additional-fields-wrapper'],
      '#tree' => TRUE,
    ];

    // Determine which option is selected.
    $selected = $form_state->getValue('option_select');

    // Only show additional fields if an option is selected.
    if (!empty($selected)) {
      // Common filename field, always visible when an option is selected.
      $form['additional_fields']['filename'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Filename'),
        '#description' => $this->t('Enter the desired filename for download (must end with .xlsx).'),
        '#required' => TRUE,
      ];

      switch ($selected) {
        case 'instrument':
          // Nested array: the real textfield name is additional_fields[instrument][main]
          $form['additional_fields']['instrument'] = [
            'top' => [
              '#type' => 'markup',
              '#markup' => '<div class="col border border-white">',
            ],
            'main' => [
              '#type' => 'textfield',
              '#title' => $this->t('Select Instrument'),
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
          // The name is additional_fields[status]
          $form['additional_fields']['status'] = [
            '#type' => 'select',
            '#title' => $this->t('Status'),
            '#options' => [
              VSTOI::DRAFT => $this->t('Draft'),
              VSTOI::UNDER_REVIEW => $this->t('Under Review'),
              VSTOI::CURRENT => $this->t('Current'),
              VSTOI::DEPRECATED => $this->t('Deprecated'),
            ],
            '#required' => TRUE,
          ];
          break;

        case 'user_status':
          // additional_fields[status]
          $form['additional_fields']['status'] = [
            '#type' => 'select',
            '#title' => $this->t('Status'),
            '#options' => [
              VSTOI::DRAFT => $this->t('Draft'),
              VSTOI::UNDER_REVIEW => $this->t('Under Review'),
              VSTOI::CURRENT => $this->t('Current'),
              VSTOI::DEPRECATED => $this->t('Deprecated'),
            ],
            '#required' => TRUE,
          ];
          // additional_fields[user_email]
          $user_options = [];
          $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['status' => 1]);
          foreach ($users as $user) {
            $user_options[$user->getEmail()] = $user->getDisplayName() . ' [' . $user->getEmail() . ']';
          }
          $form['additional_fields']['user_email'] = [
            '#type' => 'select',
            '#title' => $this->t('User Email'),
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

    // Submit button: must reference the full names, including additional_fields[...] paths.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
      '#states' => [
        'enabled' => [
          'or' => [
            // For INS per Instrument: references additional_fields[instrument][main] and additional_fields[filename]
            [
              ':input[name="option_select"]' => ['value' => 'instrument'],
              ':input[name="additional_fields[instrument][main]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[filename]"]' => ['filled' => TRUE],
            ],
            // For INS by Status: references additional_fields[status] and additional_fields[filename]
            [
              ':input[name="option_select"]' => ['value' => 'status'],
              ':input[name="additional_fields[status]"]' => ['filled' => TRUE],
              ':input[name="additional_fields[filename]"]' => ['filled' => TRUE],
            ],
            // For INS by User and by Status: references additional_fields[status], additional_fields[user_email], and additional_fields[filename]
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

    // Cancel button: separate callback, skips validation.
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
   * AJAX callback to update additional fields.
   */
  public function updateForm(array &$form, FormStateInterface $form_state) {
    return $form['additional_fields'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Check that the filename ends with '.xlsx'.
    $selected = $form_state->getValue('option_select');
    if (!empty($selected)) {
      // Because the field is at additional_fields['filename']:
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
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the selected option.
    $selected = $form_state->getValue('option_select');
    // Retrieve the common filename from additional_fields.
    $filename = $form_state->getValue(['additional_fields', 'filename']);

    // Get the API service.
    $api_service = \Drupal::service('rep.api_connector');

    // Initialize result variable.
    $result = NULL;

    switch ($selected) {
      case 'instrument':
        // For "INS per Instrument", get the instrument value.
        $instrument = $form_state->getValue(['additional_fields', 'instrument', 'main']);
        $result = $api_service->generateINSPerInstrument($instrument, $filename);
        break;

      case 'status':
        // For "INS by Status", get the status.
        $status = $form_state->getValue(['additional_fields', 'status']);
        $result = $api_service->generateINSPerStatus($status, $filename);
        break;

      case 'user_status':
        // For "INS by User and by Status", get both status and user email.
        $status = $form_state->getValue(['additional_fields', 'status']);
        $user_email = $form_state->getValue(['additional_fields', 'user_email']);
        $result = $api_service->generateINSPerUserStatus($user_email, $status, $filename);
        break;

      default:
        \Drupal::messenger()->addWarning($this->t('No option was selected.'));
        return;
    }

    \Drupal::messenger()->addMessage($this->t('INS File Successfully generated'));
    // Provide the required route parameters.
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

    // // Stream the file content directly without saving to disk.
    // $response = new Response();
    // // Set the content type for XLSX files.
    // $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    // // Set headers to force download with the specified filename.
    // $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    // // Set the file content (the API result).
    // $response->setContent($result);
    // $response->send();
    // exit();
  }

  /**
   * Cancel button submit callback.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $this->backUrl();
  }

  /**
   * Redirects the user to the previously tracked URL or a fallback.
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
