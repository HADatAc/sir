<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\Utils;
use Drupal\sir\Entity\Actuator;
use Drupal\sir\Entity\ActuatorStem;
use Drupal\sir\Entity\AnnotationStem;
use Drupal\sir\Entity\DetectorStem;
use Drupal\sir\Entity\ProcessStem;
use Drupal\sir\Entity\Detector;
use Drupal\sir\Entity\Codebook;
use Drupal\sir\Entity\Process;
use Drupal\sir\Entity\Instrument;
use Drupal\sir\Entity\ResponseOption;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Render\Markup;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Entity\Tables;
use Drupal\rep\ListKeywordPage;

class SIRSelectForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'list_form';
  }

  public $element_type;

  public $manager_email;

  public $manager_name;

  public $single_class_name;

  public $plural_class_name;

  protected $list;

  protected $list_size;

  public function getList() {
    return $this->list;
  }

  public function setList($list) {
    return $this->list = $list;
  }

  public function getListSize() {
    return $this->list_size;
  }

  public function setListSize($list_size) {
    return $this->list_size = $list_size;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype = NULL, $page = 1, $pagesize = 10) {

    // GET manager EMAIL
    $this->manager_email = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $this->manager_name = $user->name->value;

    // GET TOTAL NUMBER OF ELEMENTS
    $this->element_type = $elementtype;
    if ($this->element_type != NULL) {
      $this->setListSize(ListManagerEmailPage::total($this->element_type, $this->manager_email));
    }

    // Retrieve or set default view type
    $session = \Drupal::request()->getSession();
    $view_type = $session->get('sir_select_view_type', 'table');
    $form_state->set('view_type', $view_type);

    // Attach necessary libraries
    $form['#attached']['library'][] = 'core/drupal.bootstrap';

    $form['#attached']['library'][] = 'core/jquery';
    $form['#attached']['library'][] = 'core/jquery.once';
    $form['#attached']['library'][] = 'core/drupal';
    $form['#attached']['library'][] = 'core/drupalSettings';
    $form['#attached']['library'][] = 'sir/sir_js_css';


    $form['#attached']['drupalSettings']['sir_select_form']['base_url'] = \Drupal::request()->getSchemeAndHttpHost() . base_path();
    $form['#attached']['drupalSettings']['sir_select_form']['elementtype'] = $elementtype;

    // Get value `pagesize` (default 9)
    if ($form_state->get('page_size')) {
      $pagesize = $form_state->get('page_size');
    } else {
      $pagesize = $session->get('sir_select_form_pagesize', 9);
      $form_state->set('page_size', $pagesize);
    }

    // PUT FORM TOGETHER
    $this->prepareElementNames();

    $form['page_title'] = [
      '#type' => 'item',
      '#markup' => '<h3 class="mt-5">Manage ' . $this->plural_class_name . '</h3>',
    ];
    $form['page_subtitle'] = [
      '#type' => 'item',
      '#markup' => $this->t('<h4>@plural_class_name maintained by <font color="DarkGreen">@manager_name (@manager_email)</font></h4>', [
        '@plural_class_name' => $this->plural_class_name,
        '@manager_name' => $this->manager_name,
        '@manager_email' => $this->manager_email,
      ]),
    ];

    // Add view toggle icons
    $form['view_toggle'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['view-toggle', 'd-flex', 'justify-content-end']],
    ];

    $form['view_toggle']['table_view'] = [
      '#type' => 'submit',
      '#value' => '',
      '#name' => 'view_table',
      '#attributes' => [
        'style' => 'padding: 20px;',
        'class' => ['table-view-button', 'fa-xl', 'mx-1'],
        'title' => $this->t('Table View'),
      ],
      '#submit' => ['::viewTableSubmit'],
      '#limit_validation_errors' => [],
    ];

    $form['view_toggle']['card_view'] = [
      '#type' => 'submit',
      '#value' => '',
      '#name' => 'view_card',
      '#attributes' => [
        'style' => 'padding: 20px;',
        'class' => ['card-view-button', 'fa-xl'],
        'title' => $this->t('Card View'),
      ],
      '#submit' => ['::viewCardSubmit'],
      '#limit_validation_errors' => [],
    ];

    // Common buttons (only in table view)
    if ($view_type == 'table') {

      $form['actions_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
            'class' => ['d-flex', 'align-items-center', 'justify-content-between', 'mb-0'],
            'style' => 'margin-bottom:0!important;'
        ],
      ];

      $form['actions_wrapper']['buttons_container'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['d-flex', 'gap-2']],
      ];

      $status_filter = $form_state->getValue('status_filter') ?? 'all';
      $language_filter = $form_state->getValue('language_filter') ?? 'all';
      $text_filter = $form_state->getValue('text_filter') ?? '';

      $form['actions_wrapper']['buttons_container']['add_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add New ' . $this->single_class_name),
        '#name' => 'add_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'add-element-button'],
        ],
      ];
      if ($this->element_type == 'detectorstem') {
        $form['actions_wrapper']['buttons_container']['derive_detectorstem'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $this->single_class_name),
          '#name' => 'derive_detectorstem',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'derive-button'],
          ],
        ];
      }
      if ($this->element_type == 'actuatorstem') {
        $form['actions_wrapper']['buttons_container']['derive_actuatorstem'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $this->single_class_name),
          '#name' => 'derive_actuatorstem',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'derive-button'],
          ],
        ];
      }
      if ($this->element_type == 'processstem') {
        $form['actions_wrapper']['buttons_container']['derive_processstem'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $this->single_class_name . ' from Selected'),
          '#name' => 'derive_processstem',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'derive-button'],
          ],
        ];
      }
      $form['actions_wrapper']['buttons_container']['edit_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit Selected'),
        '#name' => 'edit_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'edit-element-button'],
        ],
      ];
      $form['actions_wrapper']['buttons_container']['delete_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete Selected'),
        '#name' => 'delete_element',
        '#attributes' => [
          'onclick' => 'if(!confirm("Really Delete?")){return false;}',
          'class' => ['btn', 'btn-primary', 'delete-element-button'],
        ],
      ];
      if ($this->element_type !== 'instrument'
        &&
          ( // TO DELETE HAS BEEING DONE
            $this->element_type !== 'process' &&
            $this->element_type !== 'processstem' &&
            $this->element_type !== 'annotationstem'
          )
        )
      {
        $form['actions_wrapper']['buttons_container']['review_selected_element'] = [
          '#type' => 'submit',
          '#value' => $this->t('Send for Review'),
          '#name' => 'review_element',
          '#attributes' => [
            'onclick' => 'if(!confirm("Are you sure you want to submit for Review selected entry?")){return false;}',
            'class' => ['btn', 'btn-primary', 'review-element-button'],
            'disabled' => 'disabled',
            'id' => 'review-selected-button',
          ],
        ];
      }

      if ($this->element_type == 'instrument' /*|| $this->element_type == 'codebook'*/) {
        $form['actions_wrapper']['buttons_container']['review_selected_element'] = [
          '#type' => 'submit',
          '#value' => $this->t('Send for R-Review'),
          '#name' => 'review_recursive_element',
          '#attributes' => [
            'onclick' => 'if(!confirm("Are you sure you want to submit for Review selected entry?")){return false;}',
            'class' => ['btn', 'btn-primary', 'review-element-button'],
            'disabled' => 'disabled',
            'id' => 'review-selected-button',
          ],
        ];
      }

      if ($this->element_type === 'instrument') {
        // $form['actions_wrapper']['buttons_container']['generate_ins_select_element'] = [
        //   '#type' => 'submit',
        //   '#value' => $this->t('Generate INS'),
        //   '#name' => 'generate_ins_element',
        //   '#attributes' => [
        //     'onclick' => 'if(!confirm("Are you sure you want to generate an INS file?")){return false;}',
        //     'class' => ['btn', 'btn-primary', 'generate-ins-element-button'],
        //   ],
        // ];
        $form['actions_wrapper']['buttons_container']['manage_slotelements'] = [
          '#type' => 'submit',
          '#value' => $this->t('Manage Structure of Selected'),
          '#name' => 'manage_slotelements',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'manage_slotelements-button'],
          ],
        ];
      }
      if ($this->element_type == 'codebook') {
        $form['actions_wrapper']['buttons_container']['manage_codebookslots'] = [
          '#type' => 'submit',
          '#value' => $this->t('Manage Response Option Slots of Selected Codebook'),
          '#name' => 'manage_codebookslots',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'manage_codebookslots-button'],
            'id' => 'manage-codebookslots-button'
          ],
        ];
      }

      $status_options = [
        'all' => $this->t('All Status'),
        'draft' => $this->t('Draft'),
        'underreview' => $this->t('Under Review'),
        'current' => $this->t('Current'),
        'deprecated' => $this->t('Deprecated'),
      ];

      $form['actions_wrapper']['filter_container'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'ms-auto', 'mb-0'],
          'style' => 'margin-bottom:0!important;'
        ],
      ];

      $form['actions_wrapper']['filter_container']['filter_label'] = [
        '#type' => 'label',
        '#title' => $this->t('Filter(s): '),
        '#attributes' => [
          'class' => ['pt-3', 'me-2', 'fw-bold'],
        ]
      ];

      $form['actions_wrapper']['filter_container']['text_filter'] = [
        '#type' => 'textfield',
        '#default_value' => $text_filter,
        '#ajax' => [
            'callback' => '::ajaxReloadTable',
            'wrapper' => 'element-table-wrapper',
            'event' => 'change',
        ],
        '#attributes' => [
            'class' => ['form-select', 'w-auto', 'mt-2', 'me-1'],
            'style' => 'margin-bottom:0!important;float:right;',
            'placeholder' => 'Type in your search criteria',
            // Ao pressionar Enter, previne o submit e dispara o evento "change"
            'onkeydown' => 'if (event.keyCode == 13) { event.preventDefault(); this.blur(); }',
        ],
      ];

      If ($this->element_type !== 'detector' && $this->element_type !== 'actuator'){
        $tables = new Tables;
        $languages = $tables->getLanguages();
        if ($languages)
          $languages = ['all' => $this->t('All Languages')] + $languages;
        $form['actions_wrapper']['filter_container']['language_filter'] = [
          '#type' => 'select',
          '#options' => $languages,
          '#default_value' => $language_filter,
          '#ajax' => [
              'callback' => '::ajaxReloadTable',
              'wrapper' => 'element-table-wrapper',
              'event' => 'change',
          ],
          '#attributes' => [
              'class' => ['form-select', 'w-auto', 'mt-2', 'me-1'],
              'style' => 'margin-bottom:0!important;float:right;'
              // 'style' => 'float:right;margin-top:10px!important;'
          ],
        ];
      }

      $form['actions_wrapper']['filter_container']['status_filter'] = [
          '#type' => 'select',
          '#options' => $status_options,
          '#default_value' => $status_filter,
          '#ajax' => [
              'callback' => '::ajaxReloadTable',
              'wrapper' => 'element-table-wrapper',
              'event' => 'change',
          ],
          '#attributes' => [
              'class' => ['form-select', 'w-auto', 'mt-2'],
              'style' => 'margin-bottom:0!important;float:right;'
              // 'style' => 'float:right;margin-top:10px!important;'
          ],
      ];

    } else {
      // In card view, add 'Add New' button at the top
      $form['actions_wrapper']['buttons_container']['add_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add New ' . $this->single_class_name),
        '#name' => 'add_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'add-element-button', 'mb-3'],
        ],
      ];
      if ($this->element_type == 'detectorstem') {
        $form['actions_wrapper']['buttons_container']['derive_detectorstem'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $this->single_class_name),
          '#name' => 'derive_detectorstem',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'derive-button', 'mb-3'],
          ],
        ];
      }
      if ($this->element_type == 'actuatorstem') {
        $form['actions_wrapper']['buttons_container']['derive_actuatorstem'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $this->single_class_name),
          '#name' => 'derive_actuatorstem',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'derive-button', 'mb-3'],
          ],
        ];
      }
    }

    // Render output based on view type
    if ($view_type == 'table') {
      $this->buildTableView($form, $form_state, $page, $pagesize);
    } elseif ($view_type == 'card') {
      $this->buildCardView($form, $form_state, $page, $pagesize);
    }

    $form['notes'] = [
      '#type' => 'markup',
      '#markup' => '<div class="info-label">Informative Notes:</div>
      <ul>
        <li>You cannot Delete nor Edit if the status is "Deprecated".</li>
        <li>You cannot Submit for Review if the status is different from "Draft".</li>
      </ul>',
      '#allowed_tags' => ['div', 'ul', 'li'],
    ];

    $form['space_1'] = [
      '#type' => 'item',
      '#markup' => '<br><br>',
    ];
    // Back button
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
    ];
    $form['space'] = [
      '#type' => 'item',
      '#markup' => '<br><br><br>',
    ];

    return $form;
  }

  /**
   * Prepare element names based on element type.
   */
  protected function prepareElementNames() {
    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');
    $preferred_detector = \Drupal::config('rep.settings')->get('preferred_detector');
    $preferred_actuator = \Drupal::config('rep.settings')->get('preferred_actuator') ?? 'Actuator';
    $preferred_process = \Drupal::config('rep.settings')->get('preferred_process');
    switch ($this->element_type) {

      // INSTRUMENT
      case "instrument":
        $this->single_class_name = $preferred_instrument;
        $this->plural_class_name = $preferred_instrument . "s";
        break;

      // ACTUATORSTEM
      case "actuatorstem":
        $this->single_class_name = $preferred_actuator . " Stem";
        $this->plural_class_name = $preferred_actuator . " Stems";
        break;

      // ACTUATOR
      case "actuator":
        $this->single_class_name = $preferred_actuator;
        $this->plural_class_name = $preferred_actuator . "s";
        break;

      // DETECTORSTEM
      case "detectorstem":
        $this->single_class_name = $preferred_detector . " Stem";
        $this->plural_class_name = $preferred_detector . " Stems";
        break;

      // DETECTOR
      case "detector":
        $this->single_class_name = $preferred_detector;
        $this->plural_class_name = $preferred_detector . "s";
        break;

      // CODEBOOK
      case "codebook":
        $this->single_class_name = "Codebook";
        $this->plural_class_name = "Codebooks";
        break;

      // RESPONSE OPTION
      case "responseoption":
        $this->single_class_name = "Response Option";
        $this->plural_class_name = "Response Options";
        break;

      // ANNOTATION STEM
      case "annotationstem":
        $this->single_class_name = "Annotation Stem";
        $this->plural_class_name = "Annotation Stems";
        break;

      // PROCESS STEM
      case "processstem":
        $this->single_class_name = $preferred_process . " Stem";
        $this->plural_class_name = $preferred_process . " Stems";
        break;

      // PROCESS
      case "process":
        $this->single_class_name = $preferred_process;
        $this->plural_class_name =  $preferred_process . "s";
        break;

      default:
        $this->single_class_name = "Object of Unknown Type";
        $this->plural_class_name = "Objects of Unknown Types";
    }
  }

  /**
   * Build the table view.
   */
  protected function buildTableView(array &$form, FormStateInterface $form_state, $page, $pagesize) {
    // Retrieve the filtered status
    $status_filter = $form_state->getValue('status_filter') ?? 'all';
    $language_filter = $form_state->getValue('language_filter') ?? 'all';
    $text_filter = $form_state->getValue('text_filter') ?? '';

    // Convert the text filter to lowercase for case-insensitive comparison
    $text_filter = strtolower($text_filter);

    // Get elements based on status
    if (strlen($text_filter) === 0 )
      $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));
    else
      $this->setList(ListKeywordPage::exec($this->element_type, $text_filter, $page, 99999999));
      // URGENT HAVE A API METHOD THAT RETURNS ONLY SEARCHED TEXT

    $header = $this->generateHeader();
    $results = $this->generateOutput();

    $output = $results['output'];

    $form['element_table_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'element-table-wrapper'],
    ];

    $form['element_table_wrapper']['element_table'] = [
        '#type' => 'table',
        '#header' => array_merge(['select' => ''], $header),
        '#empty' => $this->t('No ' . $this->plural_class_name . ' found'),
        '#attributes' => ['class' => ['table', 'table-striped']],
        '#js_select' => FALSE,
    ];

    foreach ($output as $key => $row) {
        $row_status = strtolower($row['element_hasStatus']);
        $row_language = strtolower($row['element_hasLanguage']);

        if ($this->element_type == 'instrument' || $this->element_type == 'codebook')
          $row_label = strtolower($row['element_name']);
        else if ($this->element_type == 'detector' || $this->element_type == 'detectorstem' || $this->element_type == 'responseoption' || $this->element_type == 'actuator' || $this->element_type == 'actuatorstem')
          $row_label = strtolower($row['element_content']);

        if ($status_filter !== 'all' && $row_status !== $status_filter) {
            continue;
        }

        if ($language_filter !== 'all' && $row_language !== $language_filter) {
            continue;
        }

        // Use strpos to check if the text filter is contained in the label.
        if ($text_filter !== '' && strpos($row_label, $text_filter) === false) {
            continue;
        }

        // Checkbox for selection
        $checkbox = [
            '#type' => 'checkbox',
            '#title' => $this->t('Select'),
            '#title_display' => 'invisible',
            '#return_value' => $key,
            '#attributes' => [
                'class' => ['element-select-checkbox', 'checkbox-status-' . $row_status],
            ],
        ];

        // Create the table row
        $form['element_table_wrapper']['element_table'][$key]['select'] = $checkbox;

        // Hide unnecessary columns
        foreach ($row as $field_key => $field_value) {
            if ($field_key !== 'element_hasStatus' && $field_key !== 'element_hasLanguage') {
                $form['element_table_wrapper']['element_table'][$key][$field_key] = [
                    '#markup' => $field_value,
                ];
            }
        }
    }

    // Add pagination
    $form['element_table_wrapper']['pager'] = [
        '#theme' => 'list-page',
        '#items' => [
            'page' => strval($page),
            'first' => ListManagerEmailPage::link($this->element_type, 1, $pagesize),
            'last' => ListManagerEmailPage::link($this->element_type, ceil($this->list_size / $pagesize), $pagesize),
            'previous' => ($page > 1) ? ListManagerEmailPage::link($this->element_type, $page - 1, $pagesize) : '',
            'next' => ($page < ceil($this->list_size / $pagesize)) ? ListManagerEmailPage::link($this->element_type, $page + 1, $pagesize) : '',
            'last_page' => strval(ceil($this->list_size / $pagesize)),
            'links' => null,
            'title' => ' ',
        ],
    ];

    return $form;
  }

  /**
   * Callback AJAX para recarregar a tabela quando um filtro for aplicado.
   */
  public function ajaxReloadTable(array &$form, FormStateInterface $form_state) {
      return $form['element_table_wrapper'];
  }


  /**
   * Build the card view.
   */
  protected function buildCardView(array &$form, FormStateInterface $form_state, $page, $pagesize, $addMore = false) {
    // Remove paginação na visualização de cartões
    $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));

    // Generate header and output
    $header = $this->generateHeader();
    //$output = $this->generateOutput();
    $results = $this->generateOutput();
    $output = $results['output'];
    $disabled_rows = $results['disabled_rows'];

    // Definir imagem placeholder
    $placeholder_image = base_path() . \Drupal::service('extension.list.module')->getPath('rep') . '/images/ins_placeholder.png';

    // Se não estiver adicionando mais, crie o wrapper principal
    if (!$addMore) {

      $form['loading_overlay'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'loading-overlay',
          'class' => ['loading-overlay'],
          'style' => 'display: none;', // Inicialmente escondido
        ],
        '#markup' => '<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>',
      ];

      $form['cards_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
              'id' => 'cards-wrapper',
              'class' => ['row'],
          ],
      ];
    }

    // Process each item to build the cards
    foreach ($output as $key => $item) {

        // Get item variables
        $item_vars = [];
        if (is_object($item)) {
            $item_vars = get_object_vars($item);
        } elseif (is_array($item)) {
            $item_vars = $item;
        } else {
            // If not object or array, skip this item
            continue;
        }

        $uri = $key;
        $content = '';
        $header_text = $item_vars['label'] ?? '';

        foreach ($header as $column_key => $column_label) {
            // Convert $column_label to string
            $column_label_string = (string) $column_label;

            // Get the corresponding value, or set to empty if it doesn't exist
            $value = $item_vars[$column_key] ?? '';

            //dpm("Column Key: $column_key, Value: $value"); // Debug para verificar correspondência da coluna e valor

            // Remove line breaks for the "Downloads" field
            if ($column_label_string == 'Downloads') {
                $value = str_replace(['<br>', '<br/>', '<br />'], '', $value);
            }

            // Update header text if it's the "Name" field
            if ($column_label_string == 'Name') {
                $header_text = preg_split('/<br\s*\/?>/i', $value)[0];
            }

            $content .= '<p class="mb-0 pb-0"><strong>' . $column_label_string . ':</strong> ' . $value . '</p>';
        }

        // Set image URL, use placeholder if no image in item
        $image_uri = !empty($item_vars['image']) ? $item_vars['image'] : $placeholder_image;

        // Build card structure
        $card = [
          '#type' => 'container',
          '#attributes' => [
              'class' => ['col-md-4', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
              'id' => 'card-item-' . $uri,
              'data-drupal-selector' => 'edit-card-' . str_replace([':', '/', '.'], '', $uri), // Removing special characters to keep the pattern consistent
          ],
        ];

        $card['card'] = [
          '#type' => 'container',
          '#attributes' => [
              'class' => ['card', 'mb-3', 'js-form-wrapper', 'form-wrapper'],
              'id' => 'card-item-' . $uri,
              'data-drupal-selector' => 'edit-card-' . str_replace([':', '/', '.'], '', $uri),
          ],
        ];

        // Card header
        if ($header_text != '')
          $card['card']['header'] = [
            '#type' => 'container',
            '#attributes' => [
                'style' => 'margin-bottom:0!important;',
                'class' => ['card-header', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
                'data-drupal-selector' => 'edit-header',
                'id' => 'edit-header--' . md5($uri), // Using md5 to ensure unique IDs
            ],
            '#markup' => '<h5 class="mb-0">' . $header_text . '</h5>',
          ];

        // Card body
        $card['card']['body'] = [
          '#type' => 'container',
          '#attributes' => [
              'style' => 'margin-bottom:0!important;',
              'class' => ['card-body', 'mb-0', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
              'data-drupal-selector' => 'edit-body',
              'id' => 'edit-body--' . md5($uri),
          ],
          'row' => [
              '#type' => 'container',
              '#attributes' => [
                  'style' => 'margin-bottom:0!important;',
                  'class' => ['row', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
                  'data-drupal-selector' => 'edit-row',
                  'id' => 'edit-row--' . md5($uri),
              ],
              'image_column' => [
                  '#type' => 'container',
                  '#attributes' => [
                      'style' => 'margin-bottom:0!important;',
                      'class' => ['col-md-5', 'text-center', 'mb-0', 'align-middle', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
                      'data-drupal-selector' => 'edit-image-column',
                      'id' => 'edit-image-column--' . md5($uri),
                  ],
                  'image' => [
                      '#theme' => 'image',
                      '#uri' => $image_uri,
                      '#alt' => $this->t('Image for @name', ['@name' => $item_vars['label'] ?? '']),
                      '#attributes' => [
                          'style' => 'width: 70%',
                          'class' => ['img-fluid', 'mb-0'],
                          'data-drupal-selector' => 'edit-image',
                      ],
                  ],
              ],
              'text_column' => [
                  '#type' => 'container',
                  '#attributes' => [
                      'style' => 'margin-bottom:0!important;',
                      'class' => ['col-md-7', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
                      'data-drupal-selector' => 'edit-text-column',
                      'id' => 'edit-text-column--' . md5($uri),
                  ],
                  'text' => [
                      '#markup' => '<p class="card-text">' . $content . '</p>',
                  ],
              ],
          ],
        ];

        // Card footer (Actions)
        $card['card']['footer'] = [
          '#type' => 'container',
          '#attributes' => [
              'style' => 'margin-bottom:0!important;',
              'class' => ['d-flex', 'card-footer', 'justify-content-end', 'mb-0', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
              'data-drupal-selector' => 'edit-footer',
              'id' => 'edit-footer--' . md5($uri),
          ],
        ];

        $card['card']['footer']['actions'] = [
          '#type' => 'actions',
          '#attributes' => [
              'style' => 'margin-bottom:0!important;',
              'class' => ['mb-0', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
              'data-drupal-selector' => 'edit-actions',
              'id' => 'edit-actions--' . md5($uri),
          ],
        ];

        // Edit button
        if ($item_vars['element_hasStatus'] !== VSTOI::UNDER_REVIEW && $item_vars['element_hasStatus'] !== VSTOI::DEPRECATED) {
          $card['card']['footer']['actions']['edit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Edit'),
              '#name' => 'edit_element_' . md5($uri),
              '#attributes' => [
                  'class' => ['btn', 'btn-primary', 'btn-sm', 'edit-element-button', 'button', 'js-form-submit', 'form-submit'],
                  'data-drupal-no-ajax' => 'true',
                  'formnovalidate' => 'formnovalidate',
                  'onclick' => 'this.form.submit();',
                  'data-drupal-selector' => 'edit-edit',
                  'id' => 'edit-edit--' . md5($uri),
              ],
              '#submit' => ['::editElementSubmit'],
              '#limit_validation_errors' => [],
              '#element_uri' => $uri,
            ];
        }

        // Delete button
        if ($item_vars['element_hasStatus'] !== VSTOI::DEPRECATED && $item_vars['element_hasStatus'] !== VSTOI::UNDER_REVIEW) {
          $card['card']['footer']['actions']['delete'] = [
            '#type' => 'submit',
            '#value' => $this->t('Delete'),
            '#name' => 'delete_element_' . md5($uri),
            '#attributes' => [
                'class' => ['btn', 'btn-danger', 'btn-sm', 'delete-element-button', 'button', 'js-form-submit', 'form-submit'],
                'onclick' => 'if(!confirm("Really Delete?")){return false;}',
                'data-drupal-selector' => 'edit-delete',
                'id' => 'edit-delete--' . md5($uri),
            ],
            '#submit' => ['::deleteElementSubmit'],
            '#limit_validation_errors' => [],
            '#element_uri' => $uri,
          ];
        }

        // Review button
        if ($item_vars['element_hasStatus'] === VSTOI::DRAFT) {
          $card['card']['footer']['actions']['review'] = [
            '#type' => 'submit',
            '#value' => $this->t('Send to Review'),
            '#name' => 'review_element_' . md5($uri),
            '#attributes' => [
                'class' => ['btn', 'btn-primary', 'btn-sm', 'review-element-button', 'button', 'js-form-submit', 'form-submit'],
                'onclick' => 'if(!confirm("Really submit to review?")){return false;}',
                'data-drupal-selector' => 'edit-review',
                'id' => 'edit-review--' . md5($uri),
            ],
            '#submit' => ['::reviewElementSubmit'],
            '#limit_validation_errors' => [],
            '#element_uri' => $uri,
          ];
        }

        // Add other buttons as needed (Manage, Derive)
        if ($this->element_type == 'instrument') {
            $card['card']['footer']['actions']['manage'] = [
              '#type' => 'submit',
              '#value' => $this->t('Manage Structure'),
              '#name' => 'manage_slotelements_' . md5($uri),
              '#attributes' => [
                  'class' => ['btn', 'btn-secondary', 'btn-sm', 'manage_slotelements-button', 'button', 'js-form-submit', 'form-submit'],
                  'data-drupal-selector' => 'edit-manage',
                  'id' => 'edit-manage--' . md5($uri),
              ],
              '#submit' => ['::manageSlotElementsSubmit'],
              '#limit_validation_errors' => [],
              '#element_uri' => $uri,
          ];
        }

        if ($this->element_type == 'processstem') {
          $card['card']['footer']['actions']['derive_processstem'] = [
            '#type' => 'submit',
            '#value' => $this->t('Derive New '),
            '#name' => 'derive_processstemelements_' . md5($uri),
            '#attributes' => [
                'class' => ['btn', 'btn-secondary', 'btn-sm', 'derive-button', 'button', 'js-form-submit', 'form-submit'],
                'data-drupal-selector' => 'edit-derive',
                'id' => 'edit-derive--' . md5($uri),
            ],
            '#submit' => ['::deriveProcessStemSubmit'],
            '#limit_validation_errors' => [],
            '#element_uri' => $uri,
          ];
        }

        if ($this->element_type == 'codebook') {
          $card['card']['footer']['actions']['manage_codebook'] = [
              '#type' => 'submit',
              '#value' => $this->t('Manage Response Option Slots '),
              '#name' => 'manage_codebookemelements_' . md5($uri),
              '#attributes' => [
                  'class' => ['btn', 'btn-secondary', 'btn-sm', 'manage_codebookslots-button', 'button', 'js-form-submit', 'form-submit'],
                  'data-drupal-selector' => 'edit-codebook',
                  'id' => 'edit-codebook--' . md5($uri),
              ],
              '#submit' => ['::manageCodebookSlotsSubmit'],
              '#limit_validation_errors' => [],
              '#element_uri' => $uri,
          ];
        }

        // Add card to wrapper container
        $form['cards_wrapper']['card_' . $uri] = $card;

        //get total items
        $total_items = $this->getListSize();

        //Pagesize
        $current_page_size = $form_state->get('page_size') ?? 9;

        //Prevent infinite scroll without new data
        if ($total_items > $current_page_size) {
          $form['load_more_button'] = [
            '#type' => 'submit',
            '#value' => $this->t('Load More'),
            '#name' => 'load_more_button',
            '#attributes' => [
              'id' => 'load-more-button',
              'class' => ['btn', 'btn-primary', 'load-more-button'],
              'style' => 'display: none;',
            ],
            '#submit' => ['::loadMoreSubmit'],
            '#limit_validation_errors' => [],
          ];

          $form['list_state'] = [
            '#type' => 'hidden',
            '#value' => ($total_items > $current_page_size ? 1:0),
            "#name" => 'list_state',
            '#attributes' => [
              'id' => 'list_state',
            ]
          ];
        }
    }

    // Final Form Debbug
    #\Drupal::logger('sir_select_form')->debug('Estado final do formulário após buildCardView: @form', ['@form' => print_r($form, TRUE)]);
  }

  /**
   * Submit handler for the Load More button.
   */
  public function loadMoreSubmit(array &$form, FormStateInterface $form_state) {

    //Pagesize
    $current_page_size = $form_state->get('page_size') ?? 9;

    $new_page_size = $current_page_size + 9;
    $form_state->set('page_size', $new_page_size);

    // Atualiza o valor de 'page_size' no estado do formulário e na sessão
    $form_state->set('page_size', $new_page_size);

    // Força a reconstrução do formulário para carregar mais elementos
    $form_state->setRebuild();
  }

  /**
   * Generate header based on element type.
   */
  protected function generateHeader() {
    switch ($this->element_type) {
      case "instrument":
        return Instrument::generateHeader();
      case "actuatorstem":
        return ActuatorStem::generateHeader();
      case "actuator":
        return Actuator::generateHeader();
      case "detectorstem":
        return DetectorStem::generateHeader();
      case "detector":
        return Detector::generateHeader();
      case "codebook":
        return Codebook::generateHeader();
      case "responseoption":
        return ResponseOption::generateHeader();
      case "annotationstem":
        return AnnotationStem::generateHeader();
      case "processstem":
        return ProcessStem::generateHeader();
      case "process":
        return Process::generateHeader();
      default:
        return [];
    }
  }

  /**
   * Generate output based on element type.
   */
  protected function generateOutput() {
    switch ($this->element_type) {
      case "instrument":
        return Instrument::generateOutput($this->getList());
      case "actuatorstem":
        return ActuatorStem::generateOutput($this->getList());
      case "actuator":
        return Actuator::generateOutput($this->getList());
      case "detectorstem":
        return DetectorStem::generateOutput($this->getList());
      case "detector":
        return Detector::generateOutput($this->getList());
      case "codebook":
        return Codebook::generateOutput($this->getList());
      case "responseoption":
        return ResponseOption::generateOutput($this->getList());
      case "annotationstem":
        return AnnotationStem::generateOutput($this->getList());
      case "processstem":
        return ProcessStem::generateOutput($this->getList());
      case "process":
        return Process::generateOutput($this->getList());
      default:
        return [];
    }
  }

  /**
   * Submit handler for table view toggle.
   */
  public function viewTableSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('view_type', 'table');
    // Update the view type in the session
    $session = \Drupal::request()->getSession();
    $session->set('sir_select_view_type', 'table');
    $form_state->setRebuild();
  }

  /**
   * Submit handler for card view toggle.
   */
  public function viewCardSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('view_type', 'card');
    // Update the view type in the session
    $session = \Drupal::request()->getSession();
    $session->set('sir_select_view_type', 'card');
    $form_state->setRebuild();
  }

  /**
   * Submit handler for editing an element in card view.
   */
  public function editElementSubmit(array &$form, FormStateInterface $form_state) {

    $triggering_element = $form_state->getTriggeringElement();

    if (isset($triggering_element['#element_uri'])) {
      $uri = $triggering_element['#element_uri'];

      // Obter o tipo de elemento
      $element_type = $this->element_type;

      // Definir o mapeamento de tipos de elementos para suas respectivas rotas
      $route_map = [
        'instrument' => 'sir.edit_instrument',
        'actuatorstem' => 'sir.edit_actuatorstem',
        'actuator' => 'sir.edit_actuator',
        'detectorstem' => 'sir.edit_detectorstem',
        'detector' => 'sir.edit_detector',
        'codebook' => 'sir.edit_codebook',
        'responseoption' => 'sir.edit_response_option',
        'annotationstem' => 'sir.edit_annotationstem',
        'processstem' => 'sir.edit_processstem',
        'process' => 'sir.edit_process',
      ];

      // Check if the element type has a defined route
      if (isset($route_map[$element_type])) {
        $route_name = $route_map[$element_type];

        // Call the function to perform the edit
        $this->performEdit($uri, $form_state);

        // Redirect to the appropriate route with the URI as a parameter
        $form_state->setRedirect($route_name, [$element_type . 'uri' => base64_encode($uri)]);
      } else {
        \Drupal::messenger()->addError($this->t('No edit route found for this element type.'));
      }
    } else {
      \Drupal::messenger()->addError($this->t('Cannot edit: URI is missing.'));
    }
  }

  /**
   * Submit handler for deleting an element in card view.
   */
  public function deleteElementSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performDelete([$uri], $form_state);
  }

  /**
   * Submit handler for deleting an element in card view.
   */
  public function reviewElementSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performReview([$uri], $form_state);
  }

  /**
   * Submit handler for managing slot elements in card view.
   */
  public function manageSlotElementsSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performManageSlotElements($uri, $form_state);
  }

  /**
   * Submit handler for managing codebook slots in card view.
   */
  public function manageCodebookSlotsSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performManageCodebookSlots($uri, $form_state);
  }

  /**
   * Submit handler for managing process slots in card view.
   */
  public function manageProcessSlotsSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];
    $this->performManageProcessSlots($uri, $form_state);
  }

  /**
   * Submit handler for deriving a detector stem in card view.
   */
  public function deriveDetectorStemSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performDeriveDetectorStem($form_state);
  }

  /**
   * Submit handler for deriving a actuator stem in card view.
   */
  public function deriveActuatorStemSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performDeriveActuatorStem($form_state);
  }

  /**
   * Submit handler for deriving a process stem in card view.
   */
  public function deriveProcessStemSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];
    $this->performDeriveProcessStem($uri, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // SET USER ID AND PREVIOUS URL FOR TRACKING STORE URLS
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    // $selected_rows = array_filter($form_state->getValue('element_table'));
    $element_table = $form_state->getValue('element_table');

    if ($element_table !== "" && $element_table !== NULL) {
      $selected_rows = array_filter($element_table, function($item) {
          return isset($item['select']) && $item['select'] !== 0;
      });
    }

    // Handle actions based on button name
    if (strpos($button_name, 'edit_element_') === 0) {
      // Certifique-se de que o URI está realmente presente
      if (isset($triggering_element['#element_uri'])) {
        // $uri = $triggering_element['#element_uri'];
        $uri = array_keys($selected_rows)[0];
        $this->performEdit($uri, $form_state);
      } else {
        \Drupal::messenger()->addError($this->t('Cannot edit: URI is missing.'));
      }
    } elseif (strpos($button_name, 'delete_element_') === 0) {
      // $uri = $triggering_element['#element_uri'];
      $uri = array_keys($selected_rows)[0];
      $this->performDelete([$uri], $form_state);
    } elseif (strpos($button_name, 'manage_slotelements_') === 0) {
      // $uri = $triggering_element['#element_uri'];
      $uri = array_keys($selected_rows)[0];
      $this->performManageSlotElements($uri, $form_state);
    } elseif (strpos($button_name, 'manage_codebookslots_') === 0) {
      // $uri = $triggering_element['#element_uri'];
      $uri = array_keys($selected_rows)[0];
      $this->performManageCodebookSlots($uri, $form_state);
    } elseif (strpos($button_name, 'derive_detectorstem_') === 0) {
      // // $uri = $triggering_element['#element_uri'];
      // $uri = array_keys($selected_rows)[0];
      // $this->performDeriveDetectorStem($uri, $form_state);
    } elseif (strpos($button_name, 'derive_actuatorstem_') === 0) {
      // // $uri = $triggering_element['#element_uri'];
      // $uri = array_keys($selected_rows)[0];
      // $this->performDeriveActuatorStem($uri, $form_state);
    } elseif ($button_name === 'add_element') {
      $this->performAdd($form_state);
    } elseif ($button_name === 'edit_element') {
      if (count($selected_rows) == 1) {
        $uri = array_keys($selected_rows)[0];
        //dpm($uri);
        $this->performEdit($uri, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select exactly one item to edit.'));
      }
    } elseif ($button_name === 'delete_element') {
      if (count($selected_rows) > 0) {
        $selected_uris = array_keys($selected_rows);
        $this->performDelete($selected_uris, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select item(s) to delete.'));
      }
    } elseif ($button_name === 'review_element') {
      // HAS ELEMENTS
      if ($form_state->getValue('element_table') !== "") {
        if (count($selected_rows) > 0) {
          $selected_uris = array_keys($selected_rows);
          $this->performReview($selected_uris, $form_state);
        } else {
          \Drupal::messenger()->addWarning($this->t('Please select item(s) to submit for review.'));
        }
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select item(s) to submit for review.'));
      }
    } elseif ($button_name === 'review_recursive_element') {
      // HAS ELEMENTS
      if (count($selected_rows) == 1) {
        $uri = array_keys($selected_rows)[0];
        $selected_rows = array_filter($form_state->getValue('element_table'));
        if (!empty($selected_rows)) {
          $selected_uris = array_keys($selected_rows);
          $this->performReviewRecursive($selected_uris, $form_state);
        } else {
          \Drupal::messenger()->addWarning($this->t('Please select item(s) to submit for recursive review.'));
        }
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select item(s) to submit for recursive review.'));
      }
    } elseif ($button_name === 'generate_ins_element') {

      \Drupal::messenger()->addWarning($this->t('Under Development'));

    } elseif ($button_name === 'manage_slotelements') {
      if (count($selected_rows) == 1) {
        $uri = array_keys($selected_rows)[0];
        $this->performManageSlotElements($uri, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select exactly one item to manage.'));
      }
    } elseif ($button_name === 'manage_codebookslots') {
      if (count($selected_rows) == 1) {
        $uri = array_keys($selected_rows)[0];
        $this->performManageCodebookSlots($uri, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select exactly one codebook to manage.'));
      }
    } elseif ($button_name === 'derive_detectorstem') {
      // $url = Url::fromRoute('sir.add_detectorstem');
      // $url->setRouteParameter('sourcedetectorstemuri', 'DERIVED');
      // $form_state->setRedirectUrl($url);
      $this->performDeriveDetectorStem($form_state);
    } elseif ($button_name === 'derive_actuatorstem') {
      // $url = Url::fromRoute('sir.add_actuatorstem');
      // $url->setRouteParameter('sourceactuatorstemuri', 'DERIVED');
      // $form_state->setRedirectUrl($url);
      $this->performDeriveActuatorStem($form_state);
    } elseif ($button_name === 'derive_processstem') {
      if (count($selected_rows) == 1) {
        $uri = array_keys($selected_rows)[0];
        $this->performDeriveProcessStem($uri, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select exactly one item stem to derive.'));
      }
    } elseif ($button_name === 'back') {
      $url = Url::fromRoute('sir.search');
      $form_state->setRedirectUrl($url);
    }
  }

  /**
   * Perform the add action.
   */
  protected function performAdd(FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    if ($this->element_type == 'instrument') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_instrument');
      $url = Url::fromRoute('sir.add_instrument');
    } elseif ($this->element_type == 'actuatorstem') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_actuatorstem');
      $url = Url::fromRoute('sir.add_actuatorstem');
      $url->setRouteParameter('sourceactuatorstemuri', 'EMPTY');
    } elseif ($this->element_type == 'actuator') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_actuator');
      $url = Url::fromRoute('sir.add_actuator');
      $url->setRouteParameter('sourceactuatoruri', 'EMPTY');
      $url->setRouteParameter('containersloturi', 'EMPTY');
    } elseif ($this->element_type == 'detectorstem') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_detectorstem');
      $url = Url::fromRoute('sir.add_detectorstem');
      $url->setRouteParameter('sourcedetectorstemuri', 'EMPTY');
    } elseif ($this->element_type == 'detector') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_detector');
      $url = Url::fromRoute('sir.add_detector');
      $url->setRouteParameter('sourcedetectoruri', 'EMPTY');
      $url->setRouteParameter('containersloturi', 'EMPTY');
    } elseif ($this->element_type == 'codebook') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_codebook');
      $url = Url::fromRoute('sir.add_codebook');
    } elseif ($this->element_type == 'responseoption') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_response_option');
      $url = Url::fromRoute('sir.add_response_option');
      $url->setRouteParameter('codebooksloturi', 'EMPTY');
    } elseif ($this->element_type == 'annotationstem') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_annotationstem');
      $url = Url::fromRoute('sir.add_annotationstem');
      $url->setRouteParameter('sourceannotationstemuri', 'EMPTY');
    } elseif ($this->element_type == 'processstem') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_processstem');
      $url = Url::fromRoute('sir.add_processstem');
      $url->setRouteParameter('sourceprocessstemuri', 'EMPTY');
    } elseif ($this->element_type == 'process') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_process');
      $url = Url::fromRoute('sir.add_process');
      $url->setRouteParameter('state', 'basic');
      //$url->setRouteParameter('sourceprocessuri', 'EMPTY');
    }
    $form_state->setRedirectUrl($url);
  }

  /**
   * Perform the edit action.
   */
  protected function performEdit($uri, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    if ($this->element_type == 'instrument') {
      $url = Url::fromRoute('sir.edit_instrument', ['instrumenturi' => base64_encode($uri)]);
    } elseif ($this->element_type == 'actuatorstem') {
      $url = Url::fromRoute('sir.edit_actuatorstem', ['actuatorstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'actuator') {
      $url = Url::fromRoute('sir.edit_actuator', ['actuatoruri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'detectorstem') {
      $url = Url::fromRoute('sir.edit_detectorstem', ['detectorstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'detector') {
      $url = Url::fromRoute('sir.edit_detector', ['detectoruri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'codebook') {
      $url = Url::fromRoute('sir.edit_codebook', ['codebookuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'responseoption') {
      $url = Url::fromRoute('sir.edit_response_option', ['responseoptionuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'annotationstem') {
      $url = Url::fromRoute('sir.edit_annotationstem', ['annotationstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'processstem') {
      $url = Url::fromRoute('sir.edit_processstem', ['processstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'process') {
      $url = Url::fromRoute('sir.edit_process', ['state' => 'init', 'processuri' => base64_encode($uri)]);
    } else {
      \Drupal::messenger()->addError($this->t('No edit route found for this element type.'));
      return;
    }

    // Definir redirecionamento explícito
    Utils::trackingStoreUrls($uid,$previousUrl,$url->toString());
    $form_state->setRedirectUrl($url);
  }

  /**
   * Perform the delete action.
   */
  protected function performDelete(array $uris, FormStateInterface $form_state) {
    $api = \Drupal::service('rep.api_connector');

    foreach ($uris as $shortUri) {
      $uri = Utils::plainUri($shortUri);
      if ($this->element_type == 'instrument') {
        $api->instrumentDel($uri);
      } elseif ($this->element_type == 'actuatorstem') {
        $api->actuatorStemDel($uri);
      } elseif ($this->element_type == 'actuator') {
        $api->actuatorDel($uri);
      } elseif ($this->element_type == 'detectorstem') {
        $api->detectorStemDel($uri);
      } elseif ($this->element_type == 'detector') {
        $api->detectorDel($uri);
      } elseif ($this->element_type == 'codebook') {
        $api->codebookDel($uri);
      } elseif ($this->element_type == 'responseoption') {
        $api->responseOptionDel($uri);
      } elseif ($this->element_type == 'annotationstem') {
        $api->annotationStemDel($uri);
      } elseif ($this->element_type == 'processstem') {
        $api->processStemDel($uri);
      } elseif ($this->element_type == 'process') {
        $api->processDel($uri);
      }
    }
    \Drupal::messenger()->addMessage($this->t('Selected @elements have been deleted successfully.', ['@elements' => $this->plural_class_name]));
    $form_state->setRebuild();
  }

  /**
   * Perform the review action.
   */
  protected function performReview(array $uris, FormStateInterface $form_state) {

    // dpm($this->element_type);
    $api = \Drupal::service('rep.api_connector');
    $useremail = \Drupal::currentUser()->getEmail();

    // DETECT ELEMENT
    foreach ($uris as $shortUri) {
      $uri = Utils::plainUri($shortUri);

      // GET OBJECT
      $rawresponse = $api->getUri($uri);
      $obj = json_decode($rawresponse);
      $result = $obj->body;

      //GLOBAL CHECKBOX STATUS
      if ($result->hasStatus !== VSTOI::DRAFT) {
        \Drupal::messenger()->addWarning($this->t('ATTENTION: Only draft elements can be submitted for review. Check the status of the elements and submit again. '),['@elements' => $this->plural_class_name]);
        return false;
      }

      if ($this->element_type == 'responseoption') {

        // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
        if ($result->wasDerivedFrom !== NULL
            && $this->checkDerivedElements($uri, $this->element_type)) {
            \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
            return false;

        // CENARIO #2: CHECK IF THERE ARE ANY OTHER R.O. WITH SAME CONTENT ALREADY IN REP
        } elseif ($result->wasDerivedFrom === NULL) {
          $response = $api->listByKeywordAndLanguage($this->element_type, $result->hasContent, $result->hasLanguage, 99999, 0);
          $json_string = (string) $response;
          $decoded_response = json_decode($json_string, true);

          if (is_array($decoded_response)) {
            $count = count($decoded_response['body']);
            if ($count > 1) {
              \Drupal::messenger()->addError($this->t('There is already a @element with the same content in the Repository.', ['@element' => $this->single_class_name]));
              return false;
            }
          }
        }

        // NO RESTRITIONS? SEND TO REVIEW
        $clonedObject = $result;
        $clonedObject->hasStatus = VSTOI::UNDER_REVIEW;

        unset($clonedObject->deletable);
        unset($clonedObject->count);
        unset($clonedObject->uriNamespace);
        unset($clonedObject->typeNamespace);
        unset($clonedObject->label);
        unset($clonedObject->nodeId);
        unset($clonedObject->field);
        unset($clonedObject->query);
        unset($clonedObject->namedGraph);
        unset($clonedObject->serialNumber);
        unset($clonedObject->image);
        unset($clonedObject->typeLabel);
        unset($clonedObject->hascoTypeLabel);

        $finalObject = json_encode($clonedObject, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        $api->responseOptionDel($uri);
        $api->responseOptionAdd($finalObject);

      } elseif ($this->element_type == 'codebook') {
        // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
        if ($result->wasDerivedFrom !== NULL
            && $this->checkDerivedElements($uri, $this->element_type)) {
            \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
            return false;

        // CENARIO #2: CHECK IF THERE ARE ANY OTHER R.O. WITH SAME CONTENT ALREADY IN REP
        } elseif ($result->wasDerivedFrom === NULL) {
          $response = $api->listByKeywordAndLanguage($this->element_type, $result->label, $result->hasLanguage, 99999, 0);
          $json_string = (string) $response;

          $decoded_response = json_decode($json_string, true);

          if (is_array($decoded_response)) {
            $count = count($decoded_response['body']);
            if ($count > 1) {
              \Drupal::messenger()->addError($this->t('There is already a @element with the same content in the Repository.', ['@element' => $this->single_class_name]));
              return false;
            }
          }
        }

        //MAIN BODY CODEBOOK
        $codebookJSON = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.VSTOI::CODEBOOK.'",'.
          '"hascoTypeUri":"'.VSTOI::CODEBOOK.'",'.
          '"label":"' . $result->label . '",' .
          '"comment":"'.$result->comment.'",' .
          '"hasStatus":"'.VSTOI::UNDER_REVIEW.'",'.
          '"hasLanguage":"'.$result->hasLanguage.'",' .
          '"hasVersion":"'.$result->hasVersion.'",' .
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"hasReviewNote": "'. $result->hasReviewNote .'",'.
          '"hasWebDocument": "'. $result->hasWebDocument .'",'.
          '"hasEditorEmail": "'. $useremail .'"'.
        '}';

          // ADD SLOTS
          if (!empty($reponse->codebookSlots)){
            $codebookJSON .= '"codebookSlots":[';
            $slot_list = $api->codebookSlotList($uri);
            $obj = json_decode($slot_list);
            $slots = [];
            if ($obj->isSuccessful) {
              $slots = $obj->body;
            }
            foreach ($slots as $slot) {
              $codebookJSON .= '{'.
                '"uri": "'.$slot->uri.'",'.
                '"typeUri": "'.$slot->typeUri.'",'.
                '"hascoTypeUri": "'.$slot->hascoTypeUri.'",'.
                '"label": "'.$slot->label.'",'.
                '"comment": "'.$slot->comment.'",'.
                '"hasResponseOption": "'.$slot->hasResponseOption.'",'.
                '"hasPriority": "'.$slot->hasPriority.'",'.
                '"responseOption": {'.
                  '"uri": "'.$slot->responseOption->uri.'",'.
                  '"typeUri": "'.$slot->responseOption->typeUri.'",'.
                  '"hascoTypeUri": "'.$slot->responseOption->hascoTypeUri.'",'.
                  '"label": "'.$slot->responseOption->label.'",'.
                  '"comment": "'.$slot->responseOption->comment.'",'.
                  '"hasStatus": "'.($slot->responseOption->hasStatus === VSTOI::DRAFT ? VSTOI::UNDER_REVIEW : $slot->responseOption->hasStatus).'",'.
                  '"hasContent": "'.$slot->responseOption->hasContent.'",'.
                  '"hasLanguage": "'.$slot->responseOption->hasLanguage.'",'.
                  '"hasVersion": "'.$slot->responseOption->hasVersion.'",'.
                  '"wasDerivedFrom": "'.($slot->responseOption->wasDerivedFrom ?? NULL).'",'.
                  '"hasSIRManagerEmail": "'.$slot->responseOption->hasSIRManagerEmail.'",'.
                  '"hasEditorEmail": "'.($slot->responseOption->hasEditorEmail ?? NULL).'",'.
                  '"typeLabel": "'.$slot->responseOption->typeLabel.'",'.
                  '"hasWebDocument": "'. $slot->responseOption->hasWebDocument .'",'.
                  '"hascoTypeLabel": "'.$slot->responseOption->hascoTypeLabel.'"'.
                '},'.
                '"typeLabel": "'.$slot->typeLabel.'",'.
                '"hascoTypeLabel": "'.$slot->hascoTypeLabel.'"'.
                '}';
              $codebookJSON .= $slot->hasPriority < sizeof($slots) ? ',' : '';
            }
            $codebookJSON .= '],';
          }

        // UPDATE BY DELETING AND CREATING
        $api->codebookDel($result->uri);
        $api->codebookAdd($codebookJSON);

      } elseif ($this->element_type == 'actuator') {
        // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
        if ($result->wasDerivedFrom !== NULL
            && $this->checkDerivedElements($uri, $this->element_type)) {
            \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
            return false;

        // CENARIO #2: CHECK IF THERE ARE ANY OTHER ACTUATOR WITH SAME CONTENT ALREADY IN REP, must have a new end-point for that
        }

        //MAIN BODY ACTUATOR
        $actuatorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::ACTUATOR.'",'.
          '"hasActuatorStem":"'.$result->hasActuatorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->hasContent.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$result->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$result->hasEditorEmail.'",'.
          '"hasWebDocument": "'. $result->hasWebDocument .'",'.
          '"hasStatus":"'.VSTOI::UNDER_REVIEW.'"'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->actuatorDel($result->uri);
        $api->actuatorAdd($actuatorJson);

      } elseif ($this->element_type == 'actuatorstem') {
        // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
        if ($result->wasDerivedFrom !== NULL
            && $this->checkDerivedElements($uri, $this->element_type)) {
            \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
            return false;

        // CENARIO #2: CHECK IF THERE ARE ANY OTHER ACTUATOR WITH SAME CONTENT ALREADY IN REP, must have a new end-point for that
        }
        elseif ($result->wasDerivedFrom === NULL) {
          $response = $api->listByKeywordAndLanguage($this->element_type, $result->hasContent, $result->hasLanguage, 99999, 0);
          $json_string = (string) $response;

          $decoded_response = json_decode($json_string, true);

          if (is_array($decoded_response)) {
            $count = count($decoded_response['body']);
            if ($count > 1) {
              \Drupal::messenger()->addError($this->t('There is already a @element with the same content in the Repository.', ['@element' => $this->single_class_name]));
              return false;
            }
          }
        }

        $actuatorStemJson = '{"uri":"'.$result->uri.'",'.
        '"superUri":"'.$result->superUri.'",'.
        '"label":"'.$result->label.'",'.
        '"hascoTypeUri":"'.VSTOI::ACTUATOR_STEM.'",'.
        '"hasStatus":"'.VSTOI::UNDER_REVIEW.'",'.
        '"hasContent":"'.$result->hasContent.'",'.
        '"hasLanguage":"'.$result->hasLanguage.'",'.
        '"hasVersion":"'.$result->hasVersion.'",'.
        '"comment":"'.$result->comment.'",'.
        '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$result->wasGeneratedBy.'",'.
        '"hasReviewNote":"'.$result->hasReviewNote.'",'.
        '"hasWebDocument":"'.$result->hasWebDocument.'",'.
        '"hasEditorEmail":"'.$result->hasEditorEmail.'",'.
        '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        $api->actuatorStemDel($result->uri);
        $api->actuatorStemAdd($actuatorStemJson);
      } elseif ($this->element_type == 'detector') {
        // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
        if ($result->wasDerivedFrom !== NULL
            && $this->checkDerivedElements($uri, $this->element_type)) {
            \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
            return false;

        // CENARIO #2: CHECK IF THERE ARE ANY OTHER DETECTOR WITH SAME CONTENT ALREADY IN REP, must have a new end-point for that
        }
        // elseif ($result->wasDerivedFrom === NULL) {
        //   $response = $api->listByKeywordAndLanguage($this->element_type, $result->label, $result->hasLanguage, 99999, 0);
        //   $json_string = (string) $response;

        //   $decoded_response = json_decode($json_string, true);

        //   if (is_array($decoded_response)) {
        //     $count = count($decoded_response['body']);
        //     if ($count > 1) {
        //       \Drupal::messenger()->addError($this->t('There is already a @element with the same content in the Repository.', ['@element' => $this->single_class_name]));
        //       return false;
        //     }
        //   }
        // }

        //MAIN BODY DETECTOR
        $detectorJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::DETECTOR.'",'.
          '"hasDetectorStem":"'.$result->hasDetectorStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.$result->hasContent.'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$result->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$result->hasEditorEmail.'",'.
          '"hasWebDocument": "'. $result->hasWebDocument .'",'.
          '"hasStatus":"'.VSTOI::UNDER_REVIEW.'"'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->detectorDel($result->uri);
        $api->detectorAdd($detectorJson);

      } elseif ($this->element_type == 'detectorstem') {
        // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
        if ($result->wasDerivedFrom !== NULL
            && $this->checkDerivedElements($uri, $this->element_type)) {
            \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
            return false;

        // CENARIO #2: CHECK IF THERE ARE ANY OTHER DETECTOR WITH SAME CONTENT ALREADY IN REP, must have a new end-point for that
        }
        elseif ($result->wasDerivedFrom === NULL) {
          $response = $api->listByKeywordAndLanguage($this->element_type, $result->hasContent, $result->hasLanguage, 99999, 0);
          $json_string = (string) $response;

          $decoded_response = json_decode($json_string, true);

          if (is_array($decoded_response)) {
            $count = count($decoded_response['body']);
            if ($count > 1) {
              \Drupal::messenger()->addError($this->t('There is already a @element with the same content in the Repository.', ['@element' => $this->single_class_name]));
              return false;
            }
          }
        }

        $detectorStemJson = '{"uri":"'.$result->uri.'",'.
        '"superUri":"'.$result->superUri.'",'.
        '"label":"'.$result->label.'",'.
        '"hascoTypeUri":"'.VSTOI::DETECTOR_STEM.'",'.
        '"hasStatus":"'.VSTOI::UNDER_REVIEW.'",'.
        '"hasContent":"'.$result->hasContent.'",'.
        '"hasLanguage":"'.$result->hasLanguage.'",'.
        '"hasVersion":"'.$result->hasVersion.'",'.
        '"comment":"'.$result->comment.'",'.
        '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$result->wasGeneratedBy.'",'.
        '"hasReviewNote":"'.$result->hasReviewNote.'",'.
        '"hasWebDocument":"'.$result->hasWebDocument.'",'.
        '"hasEditorEmail":"'.$result->hasEditorEmail.'",'.
        '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        $api->detectorStemDel($result->uri);
        $api->detectorStemAdd($detectorStemJson);
      }

      // } elseif ($this->element_type == 'annotationstem') {
      // } elseif ($this->element_type == 'processstem') {
      // } elseif ($this->element_type == 'process') {
      // } elseif ($this->element_type == 'actuatorstem') {
      // } elseif ($this->element_type == 'actuator') {
    }


    \Drupal::messenger()->addMessage($this->t('Selected @elements have been submited for review successfully.', ['@elements' => $this->plural_class_name]));
    //$form_state->setRebuild();
    $form_state->setRedirect('<current>');
  }

  /**
   * Perform the review recursive action.
   */
  protected function performReviewRecursive(array $uris, FormStateInterface $form_state) {

    $api = \Drupal::service('rep.api_connector');
    $useremail = \Drupal::currentUser()->getEmail();

    // DETECT ELEMENT
    foreach ($uris as $shortUri) {
      $uri = Utils::plainUri($shortUri);

      // GET OBJECT
      $rawresponse = $api->getUri($uri);
      $obj = json_decode($rawresponse);
      $result = $obj->body;

      //Case elementTypes are Instrument OR Codebook => Recursive Submit
      if ($this->element_type === 'instrument') {

        // UPDATE BY DELETING AND CREATING
        // dpm($uri);
        //dpr($responseOptionJSON);
        $resp = $api->reviewRecursive($uri, VSTOI::UNDER_REVIEW);
        $total = -1;
        dpm($resp);
        if ($resp != null) {
          $obj = json_decode($resp);
          if ($obj->isSuccessful) {
            $totalStr = $obj->body;
            $obj2 = json_decode($totalStr);
            $total = $obj2->total;
          }
        }
        dpm($total);

      // } elseif ($this->element_type == 'codebook') {

      //   // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element, checks chain for previous equal versions
      //   if ($result->wasDerivedFrom !== NULL
      //       && self::checkDerivedElements($uri, $this->element_type)) {
      //       \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
      //       return false;

      //   // CENARIO #2: CHECK IF THERE ARE ANY OTHER Codebook WITH SAME CONTENT ALREADY IN REP
      //   } elseif ($result->wasDerivedFrom === NULL) {

      //     //$response = $api->listSizeByKeywordAndLanguage($this->element_type, $result->hasContent, $result->hasLanguage);
      //     $response = $api->listByKeywordAndLanguage($this->element_type, $result->hasContent, $result->hasLanguage, 99999, 0);
      //     if ($response > 1) {
      //       \Drupal::messenger()->addError($this->t('There is already a '.$this->single_class_name.' with the same content on the Repository.'), ['@elements' => $this->plural_class_name]);
      //       return false;
      //     }
      //   }

      //   // UPDATE BY DELETING AND CREATING
      //   // dpm($uri);
      //   //dpr($responseOptionJSON);
      //   $resp = $api->reviewRecursive($uri);
      //   $total = -1;
      //   if ($resp != null) {
      //     $obj = json_decode($resp);
      //     if ($obj->isSuccessful) {
      //       $totalStr = $obj->body;
      //       $obj2 = json_decode($totalStr);
      //       $total = $obj2->total;
      //     }
      //   }
      //   // dpm($total);

      }
    }


    \Drupal::messenger()->addMessage($this->t('Selected @elements have been submited for review successfully.', ['@elements' => $this->plural_class_name]));
    //$form_state->setRebuild();
    $form_state->setRedirect('<current>');
  }

  /**
   * Perform manage slot elements action.
   */
  protected function performManageSlotElements($uri, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    $api = \Drupal::service('rep.api_connector');
    $container = $api->parseObjectResponse($api->getUri($uri), 'getUri');
    Utils::trackingStoreUrls($uid, $previousUrl, 'sir.manage_slotelements');
    $url = Url::fromRoute('sir.manage_slotelements', [
      'containeruri' => base64_encode($uri),
      'breadcrumbs' => $container->label,
    ]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Perform manage codebook slots action.
   */
  protected function performManageCodebookSlots($uri, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    Utils::trackingStoreUrls($uid, $previousUrl, 'sir.manage_codebook_slots');
    $url = Url::fromRoute('sir.manage_codebook_slots', ['codebookuri' => base64_encode($uri)]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Perform derive detector stem action.
   */
  protected function performDeriveDetectorStem(FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_detectorstem');
    $url = Url::fromRoute('sir.add_detectorstem');
    $url->setRouteParameter('sourcedetectorstemuri', 'DERIVED');
    // $url->setRouteParameter('containersloturi', 'DERIVED');
    $form_state->setRedirectUrl($url);
  }

  /**
   * Perform derive actuator stem action.
   */
  protected function performDeriveActuatorStem(FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_actuatorstem');
    $url = Url::fromRoute('sir.add_actuatorstem');
    $url->setRouteParameter('sourceactuatorstemuri', 'DERIVED');
    // $url->setRouteParameter('containersloturi', 'DERIVED');
    $form_state->setRedirectUrl($url);
  }

  /**
   * Perform derive process stem action.
   */
  protected function performDeriveProcessStem($uri, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_processstem');
    $url = Url::fromRoute('sir.add_processstem');
    $url->setRouteParameter('sourceprocessstemuri', base64_encode($uri));
    $form_state->setRedirectUrl($url);
  }

  /**
   * Checks for previous chain elements that are equal to current.
   */
  public static function checkDerivedElements($uri, $elementType) {
    $api = \Drupal::service('rep.api_connector');
    // Get current element
    $rawresponse = $api->getUri($uri);
    $obj = json_decode($rawresponse);

    if (!isset($obj->body)) {
        return false; // If API does not return an valid Body exits
    }

    $result = $obj->body;

    // If there is no derivated element returns false
    if (!isset($result->wasDerivedFrom) || empty($result->wasDerivedFrom)) {
        return false;
    }

    // Gets previous chain element
    $oldElement = $api->getUri($result->wasDerivedFrom);
    $oldObj = json_decode($oldElement);

    if (!isset($oldObj->body)) {
        return false; // Avoids errors on API part
    }

    $oldResult = $oldObj->body;

    // Check if its equal
    switch ($elementType) {
      case 'actuatorstem':
        if (
            isset($oldResult->hasContent, $result->hasContent,
                  $oldResult->hasLanguage, $result->hasLanguage) &&
            $oldResult->hasContent === $result->hasContent &&
            $oldResult->hasLanguage === $result->hasLanguage
        ) {
            return true; // Found an exact equal element → returns TRUE and exit
        }
        break;
      case 'actuator':
        if (
          isset($oldResult->hasActuatorStem, $result->hasActuatorStem,
                $oldResult->hasCodebook, $result->hasCodebook,
                $oldResult->isAttributeOf, $result->isAttributeOf) &&
          $oldResult->hasActuatorStem === $result->hasActuatorStem &&
          $oldResult->hasCodebook === $result->hasCodebook &&
          $oldResult->isAttributeOf === $result->isAttributeOf
          ) {
            return true; // Found an exact equal element → returns TRUE and exit
          }
        break;
      case 'detectorstem':
        if (
            isset($oldResult->hasContent, $result->hasContent,
                  $oldResult->hasLanguage, $result->hasLanguage) &&
            $oldResult->hasContent === $result->hasContent &&
            $oldResult->hasLanguage === $result->hasLanguage
        ) {
            return true; // Found an exact equal element → returns TRUE and exit
        }
        break;
      case 'detector':
        if (
          isset($oldResult->hasDetectorStem, $result->hasDetectorStem,
                $oldResult->hasCodebook, $result->hasCodebook,
                $oldResult->isAttributeOf, $result->isAttributeOf) &&
          $oldResult->hasDetectorStem === $result->hasDetectorStem &&
          $oldResult->hasCodebook === $result->hasCodebook &&
          $oldResult->isAttributeOf === $result->isAttributeOf
          ) {
            return true; // Found an exact equal element → returns TRUE and exit
          }
        break;
      case 'codebook':
        if (
          isset($oldResult->label, $result->label,
                $oldResult->hasLanguage, $result->hasLanguage,
                $oldResult->comment, $result->comment) &&
          $oldResult->label === $result->label &&
          $oldResult->hasLanguage === $result->hasLanguage &&
          $oldResult->comment === $result->comment
        ) {
          return true; // Found an exact equal element → returns TRUE and exit
        }
        break;
      case 'responseoption':
      default:
        if (
            isset($oldResult->hasContent, $result->hasContent,
                  $oldResult->hasLanguage, $result->hasLanguage,
                  $oldResult->comment, $result->comment) &&
            $oldResult->hasContent === $result->hasContent &&
            $oldResult->hasLanguage === $result->hasLanguage &&
            $oldResult->comment === $result->comment
        ) {
            return true; // Found an exact equal element → returns TRUE and exit
        }
        break;
    }

    // continues to search recursivelly on the chain
    return self::checkDerivedElements($result->wasDerivedFrom, $elementType);
  }
}
