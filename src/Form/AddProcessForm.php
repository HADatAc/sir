<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\REPGUI;

class AddProcessForm extends FormBase {

  protected $state;

  public function getState() {
    return $this->state;
  }
  public function setState($state) {
    return $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_process_form';
  }

  /**************************************************************
   **************************************************************
   ***                                                        ***
   ***                    BUILD FORM                          ***
   ***                                                        ***
   **************************************************************
   **************************************************************/

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $state=NULL) {

    // FOUR groups of values are preserved in state: basic, instruments, objects and codes.
    // for each group, we have render*, update*, save*, add*, remove* (basic has no add* and remove*)
    //   - render* is from $ELEMENT to $form
    //     (used in buildForm())
    //   - update* is from $form_state to $ELEMENT and save state
    //     (used in pills_card_callback())
    //   - save* is from $ELEMENT to triple store
    //     (used in save operation of submitForm())

    // SET STATE, INSTRUMENTS AND OBJECTS
    if (isset($state) && $state === 'init') {
      \Drupal::state()->delete('my_form_basic');
      \Drupal::state()->delete('my_form_instruments');
      //\Drupal::state()->delete('my_form_codes');
      $basic = [
        'processstem' => '',
        'name' => '',
        'language' => '',
        'version' => '1',
        'description' => '',
      ];
      $instruments = [];
      //$codes = [];
      $state = 'basic';
    } else {
      $basic = \Drupal::state()->get('my_form_basic') ?? [];
      $instruments = \Drupal::state()->get('my_form_instruments') ?? [];
      //$codes = \Drupal::state()->get('my_form_codes') ?? [];
    }
    $this->setState($state);

    // GET LANGUAGES
    $tables = new Tables;
    $languages = $tables->getLanguages();

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal'; // Biblioteca personalizada do módulo
    $form['#attached']['library'][] = 'core/drupal.dialog'; // Biblioteca do modal do Drupal

    // SET SEPARATOR
    $separator = '<div class="w-100"></div>';

    $form['process_title'] = [
      '#type' => 'markup',
      '#markup' => '<h3 class="mt-5">Add Process</h3><br>'
    ];

    $form['current_state'] = [
      '#type' => 'hidden',
      '#value' => $state,
    ];

    // Container for pills and content.
    $form['pills_card'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['nav', 'nav-pills', 'nav-justified', 'mb-3'],
        'id' => 'pills-card-container',
        'role' => 'tablist',
      ],

    ];

    // Define pills as links with AJAX callback.
    $states = [
      'basic' => 'Basic process properties',
      'instrument' => 'Instruments and detectors',
      //'codebook' => 'Detector mappings'
    ];

    foreach ($states as $key => $label) {
      $form['pills_card'][$key] = [
        '#type' => 'button',
        '#value' => $label,
        '#name' => 'button_' . $key,
        '#attributes' => [
          'class' => ['nav-link', $state === $key ? 'active' : ''],
          'data-state' => $key,
          'role' => 'presentation',
        ],
        '#ajax' => [
          'callback' => '::pills_card_callback',
          'event' => 'click',
          'wrapper' => 'pills-card-container',
          'progress' => ['type' => 'none'],
        ],
      ];
    }

    // Add a hidden field to capture the current state.
    $form['state'] = [
      '#type' => 'hidden',
      '#value' => $state,
    ];

    /* ========================== BASIC ========================= */

    if ($this->getState() == 'basic') {

      $processstem = '';
      if (isset($basic['processstem'])) {
        $processstem = $basic['processstem'];
      }
      $name = '';
      if (isset($basic['name'])) {
        $name = $basic['name'];
      }
      $language = '';
      if (isset($basic['language'])) {
        $language = $basic['language'];
      }
      $version = '1';
      if (isset($basic['version'])) {
        $version = $basic['version'];
      }
      $description = '';
      if (isset($basic['description'])) {
        $description = $basic['description'];
      }

      $form['process_processstem'] = [
        'top' => [
          '#type' => 'markup',
          '#markup' => '<div class="pt-3 col border border-white">',
        ],
        'main' => [
          '#type' => 'textfield',
          '#title' => $this->t('Process Stem'),
          '#name' => 'process_processstem',
          '#default_value' => $processstem,
          '#id' => 'process_processstem',
          '#parents' => ['process_processstem'],
          '#attributes' => [
            'class' => ['open-tree-modal'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => json_encode(['width' => 800]),
            'data-url' => Url::fromRoute('rep.tree_form', [
              'mode' => 'modal',
              'elementtype' => 'processstem',
            ], ['query' => ['field_id' => 'process_processstem']])->toString(),
            'data-field-id' => 'process_processstem',
            'data-elementtype' => 'processstem',
            'autocomplete' => 'off',
          ],
        ],
        'bottom' => [
          '#type' => 'markup',
          '#markup' => '</div>',
        ],
      ];
      $form['process_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $name,
      ];
      $form['process_language'] = [
        '#type' => 'select',
        '#title' => $this->t('Language'),
        '#options' => $languages,
        '#default_value' => 'en',
      ];
      $form['process_version_hid'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Version'),
        '#default_value' => $version,
        '#disabled' => true
      ];
      $form['process_version'] = [
        '#type' => 'hidden',
        '#default_value' => $version,
      ];
      $form['process_description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $description,
      ];

    }

    /* ======================= INSTRUMENT ======================= */

    if ($this->getState() == 'instrument') {

      /*
      *      INSTRUMENTS
      */

      $form['instruments_title'] = [
        '#type' => 'markup',
        '#markup' => 'Instruments',
      ];

      $form['instruments'] = array(
        '#type' => 'container',
        '#title' => $this->t('instruments'),
        '#attributes' => array(
          'class' => array('p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'),
          'id' => 'custom-table-wrapper',
        ),
      );

      $form['instruments']['header'] = array(
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Instrument</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Detectors</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' . $separator,
      );

      $form['instruments']['rows'] = $this->renderInstrumentRows($instruments);

      $form['instruments']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['instruments']['actions']['top'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="p-3 col">',
      );

      $form['instruments']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Instrument'),
        '#name' => 'new_instrument',
        '#attributes' => array('class' => array('btn', 'btn-sm', 'save-button')),
      ];

      $form['instruments']['actions']['bottom'] = array(
        '#type' => 'markup',
        '#markup' => '</div>' . $separator,
      );

    }

    /* ======================= CODEBOOK ======================= */

    /*
    if ($this->getState() == 'codebook') {

      $form['codes_title'] = [
        '#type' => 'markup',
        '#markup' => 'Codes',
      ];

      $form['codes'] = array(
        '#type' => 'container',
        '#title' => $this->t('codes'),
        '#attributes' => array(
          'class' => array('p-3', 'bg-light', 'text-dark', 'row', 'border', 'border-secondary', 'rounded'),
          'id' => 'custom-table-wrapper',
        ),
      );

      $form['codes']['header'] = array(
        '#type' => 'markup',
        '#markup' =>
          '<div class="p-2 col bg-secondary text-white border border-white">Column</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Code</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Label</div>' .
          '<div class="p-2 col bg-secondary text-white border border-white">Class</div>' .
          '<div class="p-2 col-md-1 bg-secondary text-white border border-white">Operations</div>' . $separator,
      );

      $form['codes']['rows'] = $this->renderCodeRows($codes);

      $form['codes']['space_3'] = [
        '#type' => 'markup',
        '#markup' => $separator,
      ];

      $form['codes']['actions']['top'] = array(
        '#type' => 'markup',
        '#markup' => '<div class="p-3 col">',
      );

      $form['codes']['actions']['add_row'] = [
        '#type' => 'submit',
        '#value' => $this->t('New Code'),
        '#name' => 'new_code',
        '#attributes' => array('class' => array('btn', 'btn-sm', 'add-element-button')),
      ];

      $form['codes']['actions']['bottom'] = array(
        '#type' => 'markup',
        '#markup' => '</div>' . $separator,
      );

    }
    */

    /* ======================= COMMON BOTTOM ======================= */

    $form['space'] = [
      '#type' => 'markup',
      '#markup' => '<br><br>',
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

    //$form['#attached']['library'][] = 'sir/sir_list';

    return $form;
  }

  /**************************************************************
   **************************************************************
   ***                                                        ***
   ***         VALIDATE FORM  AND AUXILIARY FUNCTIONS         ***
   ***                                                        ***
   **************************************************************
   **************************************************************/

   public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'save') {
      // TODO

      //In case no value inputed and state is null at this point
      if (\Drupal::state()->get('my_form_basic') === null) {
        $tmp_Obj = [
          'processstem' => $submitted_values['process_processstem'],
          'name' => $submitted_values['process_name'],
          'language' => $submitted_values['process_language'],
          'version' => $submitted_values['process_version'],
          'description' => $submitted_values['process_description']
        ];
        \Drupal::state()->set('my_form_basic',$tmp_Obj);
        $form_state->setValues($tmp_Obj);
        $this->updateBasic($form_state);
        //dpm(\Drupal::state()->get('my_form_basic'));
      }

      //$this->updateBasic($form_state);
      $basic = \Drupal::state()->get('my_form_basic');

      if(strlen($basic['name']) < 1) {
        $form_state->setErrorByName(
          'process_name',
          $this->t('Please enter a valid name for the Simulation Process')
        );
      }
      if(strlen($basic['processstem']) < 1) {
        $form_state->setErrorByName(
          'process_processstem',
          $this->t('Please select a valid Process Stem')
        );
      }
      if(strlen($basic['description']) < 1) {
        $form_state->setErrorByName(
          'process_description',
          $this->t('Please enter a description')
        );
      }
    }
  }

  public function pills_card_callback(array &$form, FormStateInterface $form_state) {

    // RETRIEVE CURRENT STATE AND SAVE IT ACCORDINGLY
    $currentState = $form_state->getValue('state');
    if ($currentState == 'basic') {
      $this->updateBasic($form_state);
    }
    if ($currentState == 'instrument') {
      $this->updateInstruments($form_state);
    }
    //if ($currentState == 'codebook') {
    //  $this->updateCodes($form_state);
    //}

    // RETRIEVE FUTURE STATE
    $triggering_element = $form_state->getTriggeringElement();
    $parts = explode('_', $triggering_element['#name']);
    $state = (isset($parts) && is_array($parts)) ? end($parts) : null;

    // BUILD NEW URL
    $root_url = \Drupal::request()->getBaseUrl();
    $newUrl = $root_url . REPGUI::ADD_PROCESS . $state;

    // REDIRECT TO NEW URL
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand($newUrl));

    return $response;
  }

  /******************************
   *
   *    BASIC'S FUNCTIONS
   *
   ******************************/


  /**
   * {@inheritdoc}
   */
  public function updateBasic(FormStateInterface $form_state) {
    $basic = \Drupal::state()->get('my_form_basic') ?? [
      'processstem' => '',
      'name' => '',
      'language' => '',
      'version' => '',
      'description' => '',
    ];
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($basic) && is_array($basic)) {

      $basic['processstem'] = $input['process_processstem'] ?? '';
      $basic['name']        = $input['process_name'] ?? '';
      $basic['language']    = $input['process_language'] ?? '';
      $basic['version']     = $input['process_version'] ?? '';
      $basic['description'] = $input['process_description'] ?? '';

    }
    \Drupal::state()->set('my_form_basic', $basic);
    $response = new AjaxResponse();
    return $response;
  }

  /******************************
   *
   *    instruments' FUNCTIONS
   *
   ******************************/

  protected function renderInstrumentRows(array $instruments) {
    $form_rows = [];
    $separator = '<div class="w-100"></div>';
    foreach ($instruments as $delta => $instrument) {

      $form_row = array(
        'instrument' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'instrument_instrument_'. $delta => array(
            '#type' => 'textfield',
            '#name' => 'instrument_instrument_' . $delta,
            '#value' => $instrument['instrument'],
            '#attributes' => [
             'class' => ['open-tree-modal'],
             'data-dialog-type' => 'modal',
             'data-dialog-options' => json_encode(['width' => 800]),
             'data-url' => Url::fromRoute(
               'rep.tree_form',
               [
                 'mode' => 'modal',
                 'elementtype' => 'instrument',
               ],
               [
                 'query' => ['field_id' => 'instrument_instrument_' . $delta]
               ])->toString(),
             'data-field-id' => 'instrument_instrument_' . $delta,
             'data-search-value' => $instrument['instrument'],
             'data-elementtype' => 'instrument',
            "autocomplete" => 'off',
            ],
            "#autocomplete" => 'off',
            '#ajax' => [
             'callback' => '::addDetectorCallback',
             'event' => 'change',
             'wrapper' => 'instrument_detectors_' . $delta,
             'method' => 'replaceWith',
             'effect' => 'fade',
            ],

          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        // 'detectors' => [
        //   'top' => [
        //     '#type' => 'markup',
        //     '#markup' => '<div class="pt-3 col border border-white">',
        //   ],
        //   'instrument_detectors_'. $delta => [
        //     '#type' => 'container',
        //     '#name' => 'instrument_detectors_' . $delta,
        //     '#value' => $instrument['detectors'],
        //     '#attributes' => [
        //       'id' => 'instrument_detectors_' . $delta,
        //     ]
        //     #'#detectorss' => [
        //     #  'class' => ['open-tree-modal'],
        //     #  'data-dialog-type' => 'modal',
        //     #  'data-dialog-options' => json_encode(['width' => 800]),
        //     #  'data-url' => Url::fromRoute('rep.tree_form', [
        //     #    'mode' => 'modal',
        //     #    'elementtype' => 'detectors',
        //     #  ], ['query' => ['field_id' => 'instrument_detectors_' . $delta]])->toString(),
        //     #  'data-field-id' => 'instrument_detectors_' . $delta,
        //     #  'data-search-value' => $instrument['detectors'],
        //     #  'data-elementtype' => 'detectors',
        //     #],
        //   ],
        //   'bottom' => [
        //     '#type' => 'markup',
        //     '#markup' => '</div>',
        //   ],
        // ],
        'detectors' => [
          'top' => [
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ],
          'instrument_detectors_' . $delta => [
            '#type' => 'container',
            '#attributes' => [
              'id' => 'instrument_detectors_' . $delta,
            ],
            // WILL BE THIS CODE PART
            // 'table' => [
            //   '#type' => 'table',
            //   '#header' => [
            //       $this->t('Name'),
            //       $this->t('URI'),
            //       $this->t('Status'),
            //   ],
            //   '#rows' => [], // Começa vazio e será preenchido pelo AJAX
            //   '#empty' => $this->t('No detectors yet.'),
            // ],
            '#value' => $instrument['detectors'],
          ],
          'bottom' => [
            '#type' => 'markup',
            '#markup' => '</div>',
          ],
        ],
        'operations' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col-md-1 border border-white">',
          ),
          'main' => array(
            '#type' => 'submit',
            '#name' => 'instrument_remove_' . $delta,
            '#value' => $this->t('Remove'),
            '#attributes' => array(
              'class' => array('remove-row', 'btn', 'btn-sm', 'btn-danger' , 'delete-element-button'),
              'id' => 'instrument-' . $delta,
            ),
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>' . $separator,
          ),
        ),
      );

      $rowId = 'row' . $delta;
      $form_rows[] = [
        $rowId => $form_row,
      ];

    }
    return $form_rows;
  }

  public function addDetectorCallback(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $delta = str_replace('instrument_instrument_', '', $triggering_element['#name']);
    $container_id = 'instrument_detectors_' . $delta;
    $instrument_uri = Utils::uriFromAutocomplete($form_state->getValue('instrument_instrument_' . $delta));

    // Verifica se o contêiner existe antes de modificar
    if (!isset($form['instruments']['rows'][$delta]['row'.$delta]['detectors'][$container_id])) {
        \Drupal::logger('custom_module')->error('Contêiner não encontrado para delta: @delta', ['@delta' => $delta]);
        return [
            '#markup' => $this->t('Error: Container not found for delta @delta.', ['@delta' => $delta]),
        ];
    }

    // Obtém os detectores a partir da API
    $detectors = $this->getDetectors($instrument_uri);

    //dpm($detectors);

    // Renderiza os detectores como uma tabela e atualiza o container no formulário
    $form['instruments']['rows'][$delta]['row'.$delta]['detectors'][$container_id] = $this->buildDetectorTable($detectors, $container_id);

    return $form['instruments']['rows'][$delta]['row'.$delta]['detectors'][$container_id];
}




  protected function updateInstruments(FormStateInterface $form_state) {
    $instruments = \Drupal::state()->get('my_form_instruments');
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($instruments) && is_array($instruments)) {

      foreach ($instruments as $instrument_id => $instrument) {
        if (isset($instrument_id) && isset($instrument)) {
          $instruments[$instrument_id]['instrument'] = $input['instrument_instrument_' . $instrument_id] ?? '';
          $instruments[$instrument_id]['detectors'] = $input['instrument_detectors_' . $instrument_id] ?? '';
        }
      }
    }
    \Drupal::state()->set('my_form_instruments', $instruments);
    return;
  }

  protected function saveInstruments($processUri, array $instruments) {
    if (!isset($processUri)) {
      \Drupal::messenger()->addError(t("No process URI have been provided to save instruments."));
      return;
    }
    if (!isset($instruments) || !is_array($instruments)) {
      \Drupal::messenger()->addWarning(t("Process has no instrument to be saved."));
      return;
    }

    $api = \Drupal::service('rep.api_connector');
    //dpm($instruments);
    foreach ($instruments as $instrument_id => $instrument) {

      //dpm(Utils::uriFromAutocomplete($instrument['instrument']));
      if (isset($instrument_id) && isset($instrument)) {
        //dpm($processUri." | ".$instrument['instrument']);
        try {
          $api->processInstrumentAdd($processUri,Utils::uriFromAutocomplete($instrument['instrument']));

          //$useremail = \Drupal::currentUser()->getEmail();

          //$instrument = ' ';
          //if ($instruments[$instrument_id]['instrument'] != NULL && $instruments[$instrument_id]['instrument'] != '') {
          //  $instrument = $instruments[$instrument_id]['instrument'];
          //}

          //$detectorsUri = ' ';
          //if ($instruments[$instrument_id]['detectors'] != NULL && $instruments[$instrument_id]['detectors'] != '') {
          //  $detectorsUri = $instruments[$instrument_id]['detectors'];
          //}

          //dpm($instrumentJSON);

        } catch(\Exception $e){
          \Drupal::messenger()->addError(t("An error occurred while saving process's instruments: ".$e->getMessage()));
        }
      }
    }
    return;
  }

  public function addInstrumentRow() {
    $instruments = \Drupal::state()->get('my_form_instruments') ?? [];

    // Add a new row to the table.
    $instruments[] = [
      'instrument' => '',
      'detectors' => '',
    ];
    \Drupal::state()->set('my_form_instruments', $instruments);

    // Rebuild the table rows.
    $form['instruments']['rows'] = $this->renderInstrumentRows($instruments);
    return;
  }

  public function removeInstrumentRow($button_name) {
    $instruments = \Drupal::state()->get('my_form_instruments') ?? [];

    // from button name's value, determine which row to remove.
    $parts = explode('_', $button_name);
    $instrument_to_remove = (isset($parts) && is_array($parts)) ? (int) (end($parts)) : null;

    if (isset($instrument_to_remove) && $instrument_to_remove > -1) {
      unset($instruments[$instrument_to_remove]);
      $instruments = array_values($instruments);
      \Drupal::state()->set('my_form_instruments', $instruments);
    }
    return;
  }

  /******************************
   *
   *    CODE'S FUNCTIONS
   *
   ******************************/

  /*
   protected function renderCodeRows(array $codes) {
    $form_rows = [];
    $separator = '<div class="w-100"></div>';
    foreach ($codes as $delta => $code) {

      $form_row = array(
        'column' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'code_column_' . $delta,
            '#value' => $code['column'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'code' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'code_code_' . $delta,
            '#value' => $code['code'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'label' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'code_label_' . $delta,
            '#value' => $code['label'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'class' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col border border-white">',
          ),
          'main' => array(
            '#type' => 'textfield',
            '#name' => 'code_class_' . $delta,
            '#value' => $code['class'],
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>',
          ),
        ),
        'operations' => array(
          'top' => array(
            '#type' => 'markup',
            '#markup' => '<div class="pt-3 col-md-1 border border-white">',
          ),
          'main' => array(
            '#type' => 'submit',
            '#name' => 'code_remove_' . $delta,
            '#value' => $this->t('Remove'),
            '#attributes' => array(
              'class' => array('remove-row', 'btn', 'btn-sm', 'delete-element-button'),
              'id' => 'code-' . $delta,
            ),
          ),
          'bottom' => array(
            '#type' => 'markup',
            '#markup' => '</div>' . $separator,
          ),
        ),
      );

      $rowId = 'row' . $delta;
      $form_rows[] = [
        $rowId => $form_row,
      ];

    }
    return $form_rows;
  }

  protected function updateCodes(FormStateInterface $form_state) {
    $codes = \Drupal::state()->get('my_form_codes');
    $input = $form_state->getUserInput();
    if (isset($input) && is_array($input) &&
        isset($codes) && is_array($codes)) {

      foreach ($codes as $code_id => $code) {
        if (isset($code_id) && isset($code)) {
          $codes[$code_id]['column']  = $input['code_column_' . $code_id] ?? '';
          $codes[$code_id]['code']    = $input['code_code_' . $code_id] ?? '';
          $codes[$code_id]['label']   = $input['code_label_' . $code_id] ?? '';
          $codes[$code_id]['class']   = $input['code_class_' . $code_id] ?? '';
        }
      }
    }
    \Drupal::state()->set('my_form_codes', $codes);
    return;
  }

  protected function saveCodes($processUri, array $codes) {
    if (!isset($processUri)) {
      \Drupal::messenger()->addError(t("No semantic data dictionary's URI have been provided to save possible values."));
      return;
    }
    if (!isset($codes) || !is_array($codes)) {
      \Drupal::messenger()->addWarning(t("Semantic data dictionary has no possible values to be saved."));
      return;
    }

    foreach ($codes as $code_id => $code) {
      if (isset($code_id) && isset($code)) {
        try {
          $useremail = \Drupal::currentUser()->getEmail();

          $column = ' ';
          if ($codes[$code_id]['column'] != NULL && $codes[$code_id]['column'] != '') {
            $column = $codes[$code_id]['column'];
          }

          $codeStr = ' ';
          if ($codes[$code_id]['code'] != NULL && $codes[$code_id]['code'] != '') {
            $codeStr = $codes[$code_id]['code'];
          }

          $codeLabel = ' ';
          if ($codes[$code_id]['label'] != NULL && $codes[$code_id]['label'] != '') {
            $codeLabel = $codes[$code_id]['label'];
          }

          $class = ' ';
          if ($codes[$code_id]['class'] != NULL && $codes[$code_id]['class'] != '') {
            $class = $codes[$code_id]['class'];
          }

          $codeUri = str_replace(
            Constant::PREFIX_PROCESS,
            Constant::PREFIX_POSSIBLE_VALUE,
            $processUri) . '/' . $code_id;
          $codeJSON = '{"uri":"'. $codeUri .'",'.
              '"superUri":"'.HASCO::POSSIBLE_VALUE.'",'.
              '"hascoTypeUri":"'.HASCO::POSSIBLE_VALUE.'",'.
              '"partOfSchema":"'.$processUri.'",'.
              '"listPosition":"'.$code_id.'",'.
              '"isPossibleValueOf":"'.$column.'",'.
              '"label":"'.$column.'",'.
              '"hasCode":"' . $codeStr . '",' .
              '"hasCodeLabel":"' . $codeLabel . '",' .
              '"hasClass":"' . $class . '",' .
              '"comment":"Possible value ' . $column . ' of ' . $column . ' of SDD ' . $processUri . '",'.
              '"hasSIRManagerEmail":"'.$useremail.'"}';
          $api = \Drupal::service('rep.api_connector');
          $api->elementAdd('possiblevalue',$codeJSON);

          //dpm($codeJSON);

        } catch(\Exception $e){
          \Drupal::messenger()->addError(t("An error occurred while saving possible value(s): ".$e->getMessage()));
        }
      }
    }
    return;
  }

  public function addCodeRow() {
    $codes = \Drupal::state()->get('my_form_codes') ?? [];

    // Add a new row to the table.
    $codes[] = [
      'column' => '',
      'code' => '',
      'label' => '',
      'class' => '',
    ];
    \Drupal::state()->set('my_form_codes', $codes);

    // Rebuild the table rows.
    $form['codes']['rows'] = $this->renderCodeRows($codes);
    return;
  }

  public function removeCodeRow($button_name) {
    $codes = \Drupal::state()->get('my_form_codes') ?? [];

    // from button name's value, determine which row to remove.
    $parts = explode('_', $button_name);
    $code_to_remove = (isset($parts) && is_array($parts)) ? (int) (end($parts)) : null;

    if (isset($code_to_remove) && $code_to_remove > -1) {
      unset($codes[$code_to_remove]);
      $codes = array_values($codes);
      \Drupal::state()->set('my_form_codes', $codes);
    }
    return;
  }
  */

  /**************************************************************
   **************************************************************
   ***                                                        ***
   ***                    SUBMIT FORM                         ***
   ***                                                        ***
   **************************************************************
   **************************************************************/

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // IDENTIFY NAME OF BUTTON triggering submitForm()
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      \Drupal::state()->delete('my_form_basic');
      \Drupal::state()->delete('my_form_instruments');
      self::backUrl();
      return;
    }

    // If not leaving then UPDATE STATE OF INSTRUMENTS, OBJECTS AND CODES
    // according to the current state of the editor
    if ($this->getState() === 'basic') {
      $this->updateBasic($form_state);
    }

    if ($this->getState() === 'instrument') {
      $this->updateInstruments($form_state);
    }

    //if ($this->getState() === 'codebook') {
    //  $this->updateCodes($form_state);
    //}

    // Get the latest cached versions of values in the editor
    $basic = \Drupal::state()->get('my_form_basic');
    $instruments = \Drupal::state()->get('my_form_instruments');
    //$codes = \Drupal::state()->get('my_form_codes');

    if ($button_name === 'new_instrument') {
      $this->addInstrumentRow();
      return;
    }

    if (str_starts_with($button_name,'instrument_remove_')) {
      $this->removeInstrumentRow($button_name);
      return;
    }

    /*
    if ($button_name === 'new_code') {
      $this->addCodeRow();
      return;
    }

    if (str_starts_with($button_name,'code_remove_')) {
      $this->removeCodeRow($button_name);
      return;
    }
    */

    if ($button_name === 'save') {
      try {
        $useremail = \Drupal::currentUser()->getEmail();

        // Prepare data to be sent to the external service
        $newProcessUri = Utils::uriGen('process');
        $processJSON = '{"uri":"' . $newProcessUri . '",'
          . '"typeUri":"' .Utils::uriFromAutocomplete($basic['processstem']) . '",'
          . '"hascoTypeUri":"' . VSTOI::PROCESS . '",'
          . '"hasStatus":"' . VSTOI::DRAFT . '",'
          . '"label":"' . $basic['name'] . '",'
          . '"hasLanguage":"' . $basic['language'] . '",'
          . '"hasVersion":"' . $basic['version'] . '",'
          . '"comment":"' . $basic['description'] . '",'
          . '"hasSIRManagerEmail":"' . $useremail . '"}';
        $api = \Drupal::service('rep.api_connector');
        $api->elementAdd('process',$processJSON);
        if (isset($instruments)) {
          $this->saveInstruments($newProcessUri,$instruments);
        }
        /*
        if (isset($codes)) {
          $this->saveCodes($newProcessUri,$codes);
        }
        */

        \Drupal::state()->delete('my_form_basic');
        \Drupal::state()->delete('my_form_instruments');
        //\Drupal::state()->delete('my_form_codes');

        \Drupal::messenger()->addMessage(t("Process has been added successfully."));
        self::backUrl();
        return;

      } catch(\Exception $e){
        \Drupal::messenger()->addMessage(t("An error occurred while adding process: ".$e->getMessage()));
        self::backUrl();
        return;
      }
    }

  }

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
   * get Detectors From Instrument
   */
  public function getDetectors($instrumentUri) {

    // Call to get Detectors
    $api = \Drupal::service('rep.api_connector');
    $response = $api->detectorListFromInstrument($instrumentUri);

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

  protected function buildDetectorTable(array $detectors, $container_id) {
    $header = [
        $this->t('Name'),
        $this->t('URI'),
        $this->t('Status'),
    ];

    $rows = [];
    foreach ($detectors as $detector) {
        $rows[] = [
            'data' => [
                ['#markup' => $detector['name']],
                ['#markup' => $detector['uri']],
                ['#markup' => $detector['status']],
            ],
        ];
    }

    return [
        '#type' => 'container',
        '#attributes' => ['id' => $container_id],
        'table' => [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No detectors found.'),
        ],
    ];
}


  /*
  $form['process_instruments']['wrapper']['detectors_table_'.$i] = [
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
      '#value' => TRUE
    ];
    $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i]['detectors_table_'.$i][$index]['label'] = [
      '#plain_text' => $item['name'] ?: $this->t('Unknown'),
    ];
    $form['process_instruments']['wrapper']['instrument_information_'.$i]["instrument_$i"]['fieldset_'.$i]['instrument_detector_wrapper_'.$i]['detectors_table_'.$i][$index]['status'] = [
      '#plain_text' => $item['status'] ?: $this->t('Unknown'),
    ];
  }
  */

}
