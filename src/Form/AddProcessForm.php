<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;

/**
 * Class AddProcessForm
 *
 * Provides a form to add a new process, with dynamic instruments.
 */
class AddProcessForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_process_form';
  }

  /**
   * {@inheritdoc}
   *
   * Builds the main form and handles dynamic addition of instruments.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Disable caching on this form.
    $form_state->setCached(FALSE);

    $form['#attached']['drupalSettings']['sir_process_form']['base_url'] = \Drupal::request()->getSchemeAndHttpHost() . base_path();


    // In buildForm(), before getting $instrument_count:
    if (!$form_state->has('instrument_count')) {
      // If the key does not exist, initialize it at 0.
      $form_state->set('instrument_count', 0);
    }
    $instrument_count = $form_state->get('instrument_count');

    // Libraries
    // $form['#attached']['library'][] = 'rep/rep_modal';
    // $form['#attached']['library'][] = 'core/drupal.dialog';
    // $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'sir/sir_process';


    $tables = new Tables;
    $languages = $tables->getLanguages();

    // Vertical tabs
    $form['information'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'process_information',
    ];

    // TAB 1: Process Information
    $form['process_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Process Main Form'),
      '#group' => 'information',
    ];
    $form['process_information']['process_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
    ];
    $form['process_information']['process_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => 'en',
    ];
    $form['process_information']['process_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => '1',
      '#disabled' => TRUE,
    ];
    $form['process_information']['process_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
    ];

    // TAB 2: Instruments
    $form['process_instruments'] = [
      '#type' => 'details',
      '#title' => $this->t('Instruments'),
      '#group' => 'information',
    ];

    $form['process_instruments']['add_instrument'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Instrument'),
      '#name' => 'add_instrument',
      '#ajax' => [
        'callback' => '::addInstrumentCallback',
        // Matches the ID you forced above.
        'wrapper' => 'wrapper',
        'method' => 'replaceWith',
      ],
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-instrument-button', 'mb-3', 'mt-2'],
      ],
    ];



    // Vertical tabs
    $form['process_instruments']['instruments'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'instrument_0',
    ];

    $form['process_instruments']['instruments'] = [
      '#type' => 'details',
      '#title' => $this->t('Instruments'),
      '#group' => 'instruments',
    ];

    $form['process_instruments']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'wrapper'],
    ];

    // Loop to create text fields (1 for each instrument)
    $instrument_count = $form_state->get('instrument_count');
    for ($i = 0; $i < $instrument_count; $i++) {
      $form['process_instruments']['wrapper']["instrument_$i"]['instrument_label_'.$i] = [
        '#type' => 'markup',
        '#markup' => '<strong class="mb-2">' . $this->t('Instrument @num', ['@num' => $i + 1]) . '</strong>',
        '#attributes' => [
          'class' => ['form-control', 'mb-2'],
          'style' => 'font-weight: bold; padding: 5px;',
          'id' => 'instrument_label_'.$i,
        ],
      ];
      $form['process_instruments']['wrapper']["instrument_$i"]['instrument_selected_'.$i] = [
        '#type' => 'textfield',
        '#title' => '',
        '#size' => 15,
        '#default_value' => $form_state->getValue("instrument_$i") ?? '',
        '#autocomplete_route_name' => 'sir.process_instrument_autocomplete',
        '#attributes' => [
          'class' => ['form-control', 'mt-2'],
          'id' => 'instrument_selected_'.$i,
        ],
      ];

      $form['process_instruments']['wrapper']["instrument_$i"]['instrument_detector_wrapper_'.$i] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'instrument_detector_wrapper_'.$i],
        '#markup' => $form_state->get("instrument_detector_wrapper_$i") ?? '',
      ];
    }

    // Save and Cancel buttons
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

    // Some extra space
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   *
   * Custom form validation for mandatory fields before saving.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // Only validate if user clicked on the 'Save' button
    if ($button_name === 'save') {
      if (strlen($form_state->getValue('process_name')) < 1) {
        $form_state->setErrorByName('process_name', $this->t('Please enter a valid name for the Process'));
      }
      if (strlen($form_state->getValue('process_description')) < 1) {
        $form_state->setErrorByName('process_description', $this->t('Please enter a valid description of the Process'));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Handles form submissions for both saving and canceling.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If Cancel
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      // Cancel button
      self::backUrl();
      return;
    }
    elseif ($button_name === 'save') {
      // Otherwise, it's a Save action
      try {
        $uemail = \Drupal::currentUser()->getEmail();

        // Prepare data to be sent to the external service
        $newProcessUri = Utils::uriGen('process');
        $processJSON = '{"uri":"' . $newProcessUri . '",'
          . '"typeUri":"' . VSTOI::PROCESS . '",'
          . '"hascoTypeUri":"' . VSTOI::PROCESS . '",'
          . '"hasStatus":"' . VSTOI::DRAFT . '",'
          . '"label":"' . $form_state->getValue('process_name') . '",'
          . '"hasLanguage":"' . $form_state->getValue('process_language') . '",'
          . '"hasVersion":"' . $form_state->getValue('process_version') . '",'
          . '"comment":"' . $form_state->getValue('process_description') . '",'
          . '"hasSIRManagerEmail":"' . $uemail . '"}';

        // Example: here you could also retrieve the instrument names from $form_state
        // if needed to send them to the API. Example:
        // $instrument_count = $form_state->get('instrument_count');
        // for ($i = 0; $i < $instrument_count; $i++) {
        //   $instrument_name = $form_state->getValue('instrument_name_' . $i);
        //   // Do something with $instrument_name
        // }

        // Call to external service
        $api = \Drupal::service('rep.api_connector');
        $api->processAdd($processJSON);

        \Drupal::messenger()->addMessage($this->t("Process has been added successfully."));
        self::backUrl();
        return;

      } catch (\Exception $e) {
        \Drupal::messenger()->addMessage($this->t("An error occurred while adding a process: " . $e->getMessage()));
        self::backUrl();
        return;
      }
    } elseif ($button_name === 'add_instrument') {
      $count = $form_state->get('instrument_count') ?? 0;
      $form_state->set('instrument_count', $count + 1);
      $form_state->setRebuild(TRUE);
      return;
    }
  }

  /**
   * Redirects the user back to a previously tracked URL, if available.
   */
  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.add_process');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }

  /**
   * AJAX callback that increments the instrument count and rebuilds the form.
   */
  public function addInstrumentCallback(array &$form, FormStateInterface $form_state) {
    // Return exactly the same array that contains the wrapper.
    return $form['process_instruments']['wrapper'];
  }

}
