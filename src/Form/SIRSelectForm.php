<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\Utils;
use Drupal\sir\Entity\AnnotationStem;
use Drupal\sir\Entity\DetectorStem;
use Drupal\sir\Entity\Detector;
use Drupal\sir\Entity\Codebook;
use Drupal\sir\Entity\Instrument;
use Drupal\sir\Entity\ResponseOption;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Render\Markup;
use Drupal\rep\Vocabulary\VSTOI;

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
      $form['add_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add New ' . $this->single_class_name),
        '#name' => 'add_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'add-element-button'],
        ],
      ];
      if ($this->element_type == 'detectorstem') {
        $form['derive_detectorstem'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $this->single_class_name . ' from Selected'),
          '#name' => 'derive_detectorstem',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'derive-button'],
          ],
        ];
      }
      $form['edit_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit Selected'),
        '#name' => 'edit_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'edit-element-button'],
        ],
      ];
      $form['delete_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete Selected'),
        '#name' => 'delete_element',
        '#attributes' => [
          'onclick' => 'if(!confirm("Really Delete?")){return false;}',
          'class' => ['btn', 'btn-primary', 'delete-element-button'],
        ],
      ];
      $form['review_selected_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send to Reviewer'),
        '#name' => 'review_element',
        '#attributes' => [
          'onclick' => 'if(!confirm("Are you sure to submit for Review selected entry?")){return false;}',
          'class' => ['btn', 'btn-primary', 'review-element-button'],
          'disabled' => 'disabled',
          'id' => 'review-selected-button',
        ],
      ];

      if ($this->element_type == 'instrument') {
        $form['review_recursive_selected_element'] = [
          '#type' => 'submit',
          '#value' => $this->t('Send Recursive to Reviewer'),
          '#name' => 'review_recursive_element',
          '#attributes' => [
            'onclick' => 'if(!confirm("Are you sure you want to submit for Review selected entry?")){return false;}',
            'class' => ['btn', 'btn-primary', 'review-element-button'],
          ],
        ];
        $form['generate_ins_select_element'] = [
          '#type' => 'submit',
          '#value' => $this->t('Generate INS'),
          '#name' => 'generate_ins_element',
          '#attributes' => [
            'onclick' => 'if(!confirm("Are you sure you want to generate an INS file?")){return false;}',
            'class' => ['btn', 'btn-primary', 'generate-ins-element-button'],
          ],
        ];
        $form['manage_slotelements'] = [
          '#type' => 'submit',
          '#value' => $this->t('Manage Structure of Selected'),
          '#name' => 'manage_slotelements',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'manage_slotelements-button'],
          ],
        ];
      }
      if ($this->element_type == 'codebook') {
        $form['manage_codebookslots'] = [
          '#type' => 'submit',
          '#value' => $this->t('Manage Response Option Slots of Selected Codebook'),
          '#name' => 'manage_codebookslots',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'manage_codebookslots-button'],
          ],
        ];
      }
    } else {
      // In card view, add 'Add New' button at the top
      $form['add_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add New ' . $this->single_class_name),
        '#name' => 'add_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'add-element-button', 'mb-3'],
        ],
      ];
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
        <li>You cannot submit for Review if the status is different from "Draft".</li>
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

    switch ($this->element_type) {

      // INSTRUMENT
      case "instrument":
        $this->single_class_name = $preferred_instrument;
        $this->plural_class_name = $preferred_instrument . "s";
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

      default:
        $this->single_class_name = "Object of Unknown Type";
        $this->plural_class_name = "Objects of Unknown Types";
    }
  }

  /**
   * Build the table view.
   */
  protected function buildTableView(array &$form, FormStateInterface $form_state, $page, $pagesize) {
    // GET TOTAL NUMBER OF PAGES
    if (gettype($this->list_size) == 'string') {
      $total_pages = "0";
    } else {
      if ($this->list_size % $pagesize == 0) {
        $total_pages = $this->list_size / $pagesize;
      } else {
        $total_pages = floor($this->list_size / $pagesize) + 1;
      }
    }

    // CREATE LINK FOR NEXT PAGE AND PREVIOUS PAGE
    if ($page < $total_pages) {
      $next_page = $page + 1;
      $next_page_link = ListManagerEmailPage::link($this->element_type, $next_page, $pagesize);
    } else {
      $next_page_link = '';
    }
    if ($page > 1) {
      $previous_page = $page - 1;
      $previous_page_link = ListManagerEmailPage::link($this->element_type, $previous_page, $pagesize);
    } else {
      $previous_page_link = '';
    }

    // RETRIEVE ELEMENTS FOR THE CURRENT PAGE
    $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));

    //dpm($this->getList());

    // Generate header and output
    $header = $this->generateHeader();
    //$output = $this->generateOutput();

    $results = $this->generateOutput();

    $output = $results['output'];
    $disabled_rows = $results['disabled_rows'];

    // Criar tabela personalizada
    $form['element_table'] = [
      '#type' => 'table',
      //'#type' => 'tableselect',
      '#header' => array_merge(
        ['select' => ''],
        $header
      ),
      '#empty' => $this->t('No ' . $this->plural_class_name . ' found'),
      '#attributes' => [
        'class' => ['table', 'table-striped'],
      ],
      '#js_select' => FALSE,
    ];

    // OLD METHOS TO CREATE TABLES
    // $form['element_table'] = [
    //   '#type' => 'tableselect',
    //   '#header' => $header,
    //   '#options' => $output,
    //   '#js_select' => FALSE,
    //   '#empty' => $this->t('No ' . $this->plural_class_name . ' found'),
    // ];

    // ADD lines to table
    foreach ($output as $key => $row) {
        $is_disabled = isset($disabled_rows[$key]);

        // ADD checkbox's to row
        $checkbox = [
            '#type' => 'checkbox',
            '#title' => $this->t('Select'),
            '#title_display' => 'invisible',
            '#return_value' => $key,
            '#attributes' => [
                'class' => ['element-select-checkbox checkbox-status-'. strtolower($row['element_status'])],
            ],
        ];

        // Assemble row
        // $form['element_table'][$key]['select'] = $is_disabled ? [
        //     '#markup' => '',  // Célula vazia para linhas desativadas
        // ] : $checkbox;
        $form['element_table'][$key]['select'] = $checkbox;

        // Next Columns
        foreach ($row as $field_key => $field_value) {
            $form['element_table'][$key][$field_key] = [
                '#markup' => $field_value,
            ];
        }

        // Add classes to disabled rows
        // if ($is_disabled) {
        //     $form['element_table'][$key]['#attributes']['class'][] = 'disabled-row';
        // }
    }

    // Add custom CSS
    $form['#attached']['library'][] = 'sir/sir_js_css';

    // Pager
    $form['pager'] = [
      '#theme' => 'list-page',
      '#items' => [
        'page' => strval($page),
        'first' => ListManagerEmailPage::link($this->element_type, 1, $pagesize),
        'last' => ListManagerEmailPage::link($this->element_type, $total_pages, $pagesize),
        'previous' => $previous_page_link,
        'next' => $next_page_link,
        'last_page' => strval($total_pages),
        'links' => null,
        'title' => ' ',
      ],
    ];
  }

  /**
   * Build the card view.
   */
  protected function buildCardView(array &$form, FormStateInterface $form_state, $page, $pagesize, $addMore = false) {
    // Remove paginação na visualização de cartões
    $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));

    // Generate header and output
    $header = $this->generateHeader();
    $output = $this->generateOutput();

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

    // Processar cada item para construir os cartões
    foreach ($output as $key => $item) {

        // Obter variáveis do item
        $item_vars = [];
        if (is_object($item)) {
            $item_vars = get_object_vars($item);
        } elseif (is_array($item)) {
            $item_vars = $item;
        } else {
            // Se não for objeto nem array, pular este item
            continue;
        }

        //dpr($item_vars);

        $uri = $key;
        $content = '';
        $header_text = $item_vars['label'] ?? '';

        foreach ($header as $column_key => $column_label) {
            // Converter $column_label para string
            $column_label_string = (string) $column_label;

            // Obter o valor correspondente, ou definir como vazio se não existir
            $value = $item_vars[$column_key] ?? '';

            //dpm("Column Key: $column_key, Value: $value"); // Debug para verificar correspondência da coluna e valor

            // Remover quebras de linha para o campo "Downloads"
            if ($column_label_string == 'Downloads') {
                $value = str_replace(['<br>', '<br/>', '<br />'], '', $value);
            }

            // Atualizar o texto do cabeçalho se for o campo "Name"
            if ($column_label_string == 'Name') {
                $header_text = preg_split('/<br\s*\/?>/i', $value)[0];
            }

            $content .= '<p class="mb-0 pb-0"><strong>' . $column_label_string . ':</strong> ' . $value . '</p>';
        }

        // Definir a URL da imagem, usar placeholder se não houver imagem no item
        $image_uri = !empty($item_vars['image']) ? $item_vars['image'] : $placeholder_image;

        // Construir a estrutura do cartão
        $card = [
          '#type' => 'container',
          '#attributes' => [
              'class' => ['col-md-4', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
              'id' => 'card-item-' . $uri,
              'data-drupal-selector' => 'edit-card-' . str_replace([':', '/', '.'], '', $uri), // Removendo caracteres especiais para manter o padrão consistente
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

        // Cabeçalho do cartão
        if ($header_text != '')
          $card['card']['header'] = [
            '#type' => 'container',
            '#attributes' => [
                'style' => 'margin-bottom:0!important;',
                'class' => ['card-header', 'js-form-wrapper', 'form-wrapper', 'mb-3'],
                'data-drupal-selector' => 'edit-header',
                'id' => 'edit-header--' . md5($uri), // Usando md5 para garantir IDs únicos
            ],
            '#markup' => '<h5 class="mb-0">' . $header_text . '</h5>',
          ];

        // Corpo do cartão
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

        // Rodapé do cartão (Ações)
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

        // Botão Editar
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

        // Botão Deletar
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

        // Adicionar outros botões conforme necessário (Gerenciar, Derivar)
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

        if ($this->element_type == 'detectorstem') {
          $card['card']['footer']['actions']['derive_detectorstem'] = [
              '#type' => 'submit',
              '#value' => $this->t('Derive New '),
              '#name' => 'derive_detectorstemelements_' . md5($uri),
              '#attributes' => [
                  'class' => ['btn', 'btn-secondary', 'btn-sm', 'derive-button', 'button', 'js-form-submit', 'form-submit'],
                  'data-drupal-selector' => 'edit-derive',
                  'id' => 'edit-derive--' . md5($uri),
              ],
              '#submit' => ['::deriveDetectorStemSubmit'],
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
        'detectorstem' => 'sir.edit_detectorstem',
        'detector' => 'sir.edit_detector',
        'codebook' => 'sir.edit_codebook',
        'responseoption' => 'sir.edit_response_option',
        'annotationstem' => 'sir.edit_annotationstem',
      ];

      // Verificar se o tipo de elemento possui uma rota definida
      if (isset($route_map[$element_type])) {
        $route_name = $route_map[$element_type];

        // Chamar a função para executar a edição
        $this->performEdit($uri, $form_state);

        // Redirecionar para a rota apropriada com o URI como parâmetro
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
   * Submit handler for deriving a detector stem in card view.
   */
  public function deriveDetectorStemSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performDeriveDetectorStem($uri, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    #\Drupal::logger('sir_select_form')->debug('Botão acionado: @button', ['@button' => $triggering_element['#name']]);

    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // SET USER ID AND PREVIOUS URL FOR TRACKING STORE URLS
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    // Handle actions based on button name
    if (strpos($button_name, 'edit_element_') === 0) {
      // Certifique-se de que o URI está realmente presente
      if (isset($triggering_element['#element_uri'])) {
        $uri = $triggering_element['#element_uri'];
        $this->performEdit($uri, $form_state);
      } else {
        \Drupal::messenger()->addError($this->t('Cannot edit: URI is missing.'));
      }
    } elseif (strpos($button_name, 'delete_element_') === 0) {
      $uri = $triggering_element['#element_uri'];
      $this->performDelete([$uri], $form_state);
    } elseif (strpos($button_name, 'manage_slotelements_') === 0) {
      $uri = $triggering_element['#element_uri'];
      $this->performManageSlotElements($uri, $form_state);
    } elseif (strpos($button_name, 'manage_codebookslots_') === 0) {
      $uri = $triggering_element['#element_uri'];
      $this->performManageCodebookSlots($uri, $form_state);
    } elseif (strpos($button_name, 'derive_detectorstem_') === 0) {
      $uri = $triggering_element['#element_uri'];
      $this->performDeriveDetectorStem($uri, $form_state);
    } elseif ($button_name === 'add_element') {
      $this->performAdd($form_state);
    } elseif ($button_name === 'edit_element') {
      $selected_rows = array_filter($form_state->getValue('element_table'));
      if (count($selected_rows) == 1) {
        $selected_uris = array_keys($selected_rows);
        $uri = $selected_uris[0];
        $this->performEdit($uri, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select exactly one item to edit.'));
      }
    } elseif ($button_name === 'delete_element') {
      $selected_rows = array_filter($form_state->getValue('element_table'));
      if (!empty($selected_rows)) {
        $selected_uris = array_keys($selected_rows);
        $this->performDelete($selected_uris, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select item(s) to delete.'));
      }
    } elseif ($button_name === 'review_element') {
      // HAS ELEMENTS
      if ($form_state->getValue('element_table') !== "") {
        $selected_rows = array_filter($form_state->getValue('element_table'));
        if (!empty($selected_rows)) {
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
      if ($form_state->getValue('element_table') !== "") {
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
      $selected_rows = array_filter($form_state->getValue('element_table'));
      if (count($selected_rows) == 1) {
        $selected_uris = array_keys($selected_rows);
        $uri = $selected_uris[0];
        $this->performManageSlotElements($uri, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select exactly one item to manage.'));
      }
    } elseif ($button_name === 'manage_codebookslots') {
      $selected_rows = array_filter($form_state->getValue('element_table'));
      if (count($selected_rows) == 1) {
        $selected_uris = array_keys($selected_rows);
        $uri = $selected_uris[0];
        $this->performManageCodebookSlots($uri, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select exactly one codebook to manage.'));
      }
    } elseif ($button_name === 'derive_detectorstem') {
      $selected_rows = array_filter($form_state->getValue('element_table'));
      if (count($selected_rows) == 1) {
        $selected_uris = array_keys($selected_rows);
        $uri = $selected_uris[0];
        $this->performDeriveDetectorStem($uri, $form_state);
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
      }
    }
    \Drupal::messenger()->addMessage($this->t('Selected @elements have been deleted successfully.', ['@elements' => $this->plural_class_name]));
    $form_state->setRebuild();
  }

  /**
   * Perform the review action.
   */
  protected function performReview(array $uris, FormStateInterface $form_state) {

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
        \Drupal::messenger()->addWarning($this->t('ATTENTION: Only draft elements can be submitted for review. Check the status of the elements and submit again. '));
        return;
      }

      if ($this->element_type == 'responseoption') {

      // } elseif ($this->element_type == 'detectorstem') {

      // } elseif ($this->element_type == 'detector') {

      // } elseif ($this->element_type == 'codebook') {

      // } elseif ($this->element_type == 'responseoption') {


        //dpr($result);

        $responseOptionJSON = '{'.
          '"uri":"'. $result->uri .'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.$result->hascoTypeUri.'",'.
          '"hasStatus":"'.VSTOI::UNDER_REVIEW.'",'.
          '"hasContent":"'.$result->hasContent.'",'.
          '"hasLanguage":"'.$result->hasLanguage.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"comment":"'.$result->comment.'",'.
          '"hasSIRManagerEmail":"'.$useremail;


        $responseOptionJSON .= '"}';

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        //dpr($responseOptionJSON);
        $api->responseOptionDel($uri);
        $api->responseOptionAdd($responseOptionJSON);

      // } elseif ($this->element_type == 'annotationstem') {

      }
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

      if ($this->element_type == 'instrument') {

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        dpm($uri);
        //dpr($responseOptionJSON);
        $resp = $api->reviewRecursive($uri);
        $total = -1;
        if ($resp != null) {
          $obj = json_decode($resp);
          if ($obj->isSuccessful) {
            $totalStr = $obj->body;
            $obj2 = json_decode($totalStr);
            $total = $obj2->total;
          }
        }       
        dpm($total);

      // } elseif ($this->element_type == 'annotationstem') {

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
  protected function performDeriveDetectorStem($uri, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_detectorstem');
    $url = Url::fromRoute('sir.add_detectorstem');
    $url->setRouteParameter('sourcedetectorstemuri', base64_encode($uri));
    $url->setRouteParameter('containersloturi', 'EMPTY');
    $form_state->setRedirectUrl($url);
  }

}
