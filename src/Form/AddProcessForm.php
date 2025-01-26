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

    //dpm($form_state->getValues());
    // Disable caching on this form.
    //$form_state->setCached(FALSE);

    // FOR Javascript file
    //$form['#attached']['drupalSettings']['sir_process_form']['base_url'] = \Drupal::request()->getSchemeAndHttpHost() . base_path();

    // Start counter
    if (!$form_state->has('instrument_count')) {
      $form_state->set('instrument_count', 0);
    }
    $instrument_count = $form_state->get('instrument_count');

    // Libraries
    //$form['#attached']['library'][] = 'sir/sir_process';
    $form['#attached']['library'][] = 'core/drupal.accordion';

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
        'wrapper' => 'wrapper',
        'method' => 'replaceWith',
        'effect' => 'fade',
      ],
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-instrument-button', 'mb-3', 'mt-2'],
      ],
    ];

    // Wrapper for instruments
    $form['process_instruments']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'wrapper'],
    ];

    $form['process_instruments']['wrapper']['instrument_tabs'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 0,
    ];

    $detectors = [];
    // Loop to create fields for each instrument
    for ($i = 0; $i < $instrument_count; $i++) {

      $instrument = '';
      // Get Detectors from Instrument
      if ($form_state->getValue('instrument_selected_'.$i) !== '')
        if (preg_match('/\[(https?:\/\/|)([^\]]+)\]/', $form_state->getValue('instrument_selected_'.$i) , $matches)) {
          $instrument = $matches[1].$matches[2];
      }

      //dpm($i."=".$instrument."-".$form_state->getValue('instrument_selected_'.$i));

      $detectors = $this->getDetectors( $instrument);

      $form['process_instruments']['wrapper']['instrument_information_'.$i] = [
        '#type' => 'details',
        '#title' => $this->t('<b>Instrument ['.$form_state->getValue("instrument_selected_$i").']</b>'),
        '#open' => ($i < $instrument_count-1) ? FALSE:TRUE,
        '#attributes' => [
          'class' => ['accordion-tabs-wrapper'],
          'id' => 'instrument_information_'.$i,
        ],
        '#group' => 'instruments'
      ];

      $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i] = [
        '#type' => 'fieldset',
        '#title' => $this->t(''),
        '#name' => 'fieldset_'.$i,
        // '#description' => count($detectors) > 0 ? $this->t('Select the ones you want to include'):'',
        '#attributes' => [
          'class' => ['fieldset-class'],
          'id' => 'fieldset_'.$i
        ],
      ];

      $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['insURI'] = [
        '#type' => 'container'
      ];

      $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['insURI']['instrument_selected_'.$i] = [
        '#type' => 'textfield',
        '#title' => '',
        '#size' => 15,
        '#default_value' => $form_state->getValue('instrument_selected_'.$i),
        '#autocomplete_route_name' => 'sir.process_instrument_autocomplete',
        '#attributes' => [
          'class' => ['form-control', 'mt-2', 'w-75', 'me-3'],
          'id' => 'instrument_selected_'.$i,
          'style' => 'float:left;'
        ]
      ];


      // Load detectors button
      $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['insURI']['load_detectors_'.$i] = [
        '#type' => 'submit',
        '#value' => $this->t('Load Detectors'),
        '#name' => 'load_detectors_'.$i,
        '#ajax' => [
          'callback' => '::loadDetectorsCallback',
          'wrapper' => 'instrument_detector_wrapper_'.$i,
          'event' => 'click',
          'method' => 'replaceWith',
          'effect' => 'fade',
        ],
        '#attributes' => [
          'class' => ['btn', 'btn-secondary', 'load-detectors-button', 'mt-2', 'w-13'],
        ],
      ];

      $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['insURI']['remove_instrument_'.$i] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => 'remove_instrument_'.$i,
        '#ajax' => [
          'callback' => '::removeInstrumentCallback',
          'wrapper' => 'wrapper',
          'event' => 'click',
          'method' => 'replaceWith',
          'effect' => 'fade',
        ],
        '#attributes' => [
          'class' => ['btn', 'btn-danger', 'load-detectors-button', 'mt-2', 'w-10'],
        ],
      ];

      $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'instrument_detector_wrapper_'.$i
        ]
      ];

      if (empty($detectors)) {
        $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i]['message'] = [
          '#markup' => '<p style="font-weight:bold;" class="mt-3"><b>That Instrument has no detectors available.</b></p>',
        ];
      } else {

        $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i]['detectors_table_'.$i] = [
          '#type' => 'table',
          '#header' => [
            '#',
            $this->t('Detector Label'),
            $this->t('Detector Status'),
          ],
          '#rows' => [],
          '#attributes' => ['class' => ['detectors-table']],
        ];

        // Add line to table
        foreach ($detectors as $index => $item) {
          $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i]['detectors_table_'.$i][$index]['checkbox'] = [
            '#type' => 'checkbox',
            '#attributes' => [
              'value' => $item['uri'],
              'disabled' => $item['status'] !== 'Draft',
            ],
          ];
          $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i]['detectors_table_'.$i][$index]['label'] = [
            '#plain_text' => $item['name'] ?: $this->t('Unknown'),
          ];
          $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i]['detectors_table_'.$i][$index]['status'] = [
            '#plain_text' => $item['status'] ?: $this->t('Unknown'),
          ];
        }
      }
    }

    // TAB 3: Mapping
    $form['process_mapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Mapper'),
      '#group' => 'information',
    ];

    $form['process_mapper']['message'] = [
      '#markup' => '<p style="font-weight:bold;" class="mt-3"><b>Under Development...</b></p>',
    ];

    // Botões de salvar e cancelar
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

    // Espaço extra
    $form['bottom_space'] = [
      '#type' => 'item',
      '#markup' => '<br><br>',
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
        $api = \Drupal::service('rep.api_connector');
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

        // Save Process on API
        $api->processAdd($processJSON);

        // HARDCODED FOR DEBUG
        // In my Scenario https://cienciapt.org/PC1737858644787541 = "Xuxu com camarao" AND https://cienciapt.org/INS1737852506198591 = "Nuno Instrument"
        //$api->processInstrumentAdd('https://cienciapt.org/PC1737858644787541','https://cienciapt.org/INS1737852506198591');

        // Loop to add Instruments
        $instrument_count = $form_state->get('instrument_count');
        for ($i = 0; $i < $instrument_count; $i++) {
          // Check if there is no "Add instrument Empty"
          if ($form_state->getValue('instrument_selected_'.$i) !== '') {
            $instrument = $api->processInstrumentAdd($newProcessUri,$form_state->getValue('instrument_selected_'.$i));
            \Drupal::messenger()->addWarning($this->t("Instrument: "), $instrument);

            // Loop to Add Detectors
            $detectors = $form_state->getValue('detectors_table_'.$i);

            // Filter elements where value is not 0
            $filtered = array_filter($detectors, function ($item) {
              return !empty($item['checkbox']) && $item['checkbox'] !== 0;
            });

            foreach ($filtered as $key => $value) {
              if (isset($value['checkbox'])) {
                $detector = $api->processDetectorAdd($newProcessUri,$value['checkbox']);

                \Drupal::messenger()->addWarning($this->t("Detector: "), $detector);
              }
            }
          }
        }

        //Return to Select List
        \Drupal::messenger()->addMessage($this->t("Process has been added successfully."));
        self::backUrl();
        return;
        //return false; //DEBUG

      } catch (\Exception $e) {
        \Drupal::messenger()->addError($this->t("An error occurred while adding a process: " . $e->getMessage()));
        self::backUrl();
        return;
      }
    }
    elseif ($button_name === 'add_instrument') {

      // PREVENT NULL INSTRUMENTS
      if ($form_state->getValue('instrument_selected_'.($form_state->get('instrument_count')-1)) !== '') {
        $count = $form_state->get('instrument_count') ?? 0;
        $form_state->set('instrument_count', $count + 1);
      }

      $form_state->setRebuild(TRUE);
      //return false;

    } elseif (preg_match('/load_detectors_(\d+)/', $button_name, $matches)) {
      $i = $matches[1];
      //dpm("Instrument: ".$i);

        if (preg_match('/\[(https?:\/\/|)([^\]]+)\]/', $form_state->getValue('instrument_selected_'.$i), $matches)) {
          $instrument = $matches[1].$matches[2];
          // dpm("Instrument: ".$instrument);
          //$form_state->setValue('detectors_selected_'.$i, $this->getDetectors($instrument));
          $form_state->setValue('instrument_information_'.$i, $this->t('<b>Instrument ['.$form_state->getValue("instrument_selected_$i").']</b>'));
        }

      $form_state->setRebuild(TRUE);
      return $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i];

    } elseif (preg_match('/remove_instrument_(\d+)/', $button_name, $matches)) {
      $index_to_remove = $matches[1]; // Get the index from the match.

      // Remove the instrument with the extracted index.
      $instrument_count = $form_state->get('instrument_count');
      for ($i = $index_to_remove; $i < $instrument_count - 1; $i++) {
        $form_state->setValue("instrument_selected_$i", $form_state->getValue("instrument_selected_" . ($i + 1)));
      }
      // Remove the last instrument (now a duplicate due to shifting).
      $form_state->unsetValue("instrument_selected_" . $index_to_remove);
      $form_state->set('instrument_count', $instrument_count > 0 ? $instrument_count - 1 : 0);
      $form_state->setRebuild(TRUE);
      return $form['process_instruments']['wrapper'];
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
   * AJAX addInstrument callback
   */
  public function addInstrumentCallback(array &$form, FormStateInterface $form_state) {
    // If it's not possible to determine the index, return the complete wrapper
    return $form['process_instruments']['wrapper'];
  }


  public function loadDetectorsCallback(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();

    if (preg_match('/load_detectors_(\d+)/', $trigger['#name'], $matches)) {
      $i = $matches[1];
      if (preg_match('/\[(https?:\/\/|)([^\]]+)\]/', $form_state->getValue('instrument_selected_'.$i), $matches)) {
        $instrument = $matches[1].$matches[2];
        $form_state->set('detectors_selected_'.$i, $this->getDetectors($instrument));
      }

      $form_state->setRebuild(TRUE);
      return $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i];
    }
  }

  /**
   * get Detectors From Instrument
   */
  public function getDetectors($instrument_uri) {

    // Call to get Detectors
    $api = \Drupal::service('rep.api_connector');
    $response = $api->detectorListFromInstrument($instrument_uri);

    // Decode JSON reply
    $data = json_decode($response, true);
    if (!$data || !isset($data['body'])) {
      return [];
    }

    // Decode Body
    $urls = json_decode($data['body'], true);

    // Process detectors
    $detectors = [];
    foreach ($urls as $url) {
      $detectorData = $api->getUri($url);
      $obj = json_decode($detectorData);
      $detectors[] = [
        'name' => isset($obj->body->label) ? $obj->body->label : '',
        'uri' => isset($obj->body->uri) ? $obj->body->uri : '',
        'status' => isset($obj->body->hasStatus) ? Utils::plainStatus($obj->body->hasStatus) : '',
        'hasStatus' => isset($obj->body->hasStatus) ? $obj->body->hasStatus : null,
      ];
    }
    return $detectors;
  }

  /**
   * AJAX callback to remove an instrument.
   */
  public function removeInstrumentCallback(array &$form, FormStateInterface $form_state) {
    // // Get the triggering element.
    // $trigger = $form_state->getTriggeringElement();

    // // Extract the instrument index from the triggering element's name.
    // if (preg_match('/remove_instrument_(\d+)/', $trigger['#name'], $matches)) {
    //   $index_to_remove = $matches[1]; // Get the index from the match.

    //   // Remove the instrument with the extracted index.
    //   $instrument_count = $form_state->get('instrument_count');
    //   for ($i = $index_to_remove; $i < $instrument_count - 1; $i++) {
    //     $form_state->set("instrument_selected_$i", $form_state->get("instrument_selected_" . ($i + 1)));
    //   }
    //   $form_state->setRebuild(TRUE);
    // }
    // Return the updated wrapper.
    return $form['process_instruments']['wrapper'];

  }

}
