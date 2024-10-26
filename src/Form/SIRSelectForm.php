<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\Utils;
use Drupal\sir\Entity\AnnotationStem;
use Drupal\sir\Entity\Annotation;
use Drupal\sir\Entity\DetectorStem;
use Drupal\sir\Entity\Detector;
use Drupal\sir\Entity\Codebook;
use Drupal\sir\Entity\Instrument;
use Drupal\sir\Entity\ResponseOption;
use Symfony\Component\Validator\Constraints\Length;

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
    if ($view_type == 'card') {
      // Attach Infinite Scroll library
      $form['#attached']['library'][] = 'rep/infinite_scroll';
      // Add drupalSettings for infinite scroll
      $form['#attached']['drupalSettings']['rep'] = [
        'pagesize' => $pagesize,
        'list_size' => $this->list_size,
      ];
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
      if ($this->element_type == 'instrument') {
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
          'class' => ['btn', 'btn-primary', 'add-element-button'],
        ],
      ];
    }

    // Render output based on view type
    if ($view_type == 'table') {
      $this->buildTableView($form, $form_state, $page, $pagesize);
    } elseif ($view_type == 'card') {
      $this->buildCardView($form, $form_state, $page, $pagesize);
    }

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

    // Generate header and output
    $header = $this->generateHeader();
    $output = $this->generateOutput();

    $form['element_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => $this->t('No ' . $this->plural_class_name . ' found'),
    ];

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
  protected function buildCardView(array &$form, FormStateInterface $form_state, $page, $pagesize) {
    // Remove pagination in card view
    $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));

    // Generate header and output
    $header = $this->generateHeader();
    $output = $this->generateOutput();

    $form['element_cards'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['row', 'infinite-scroll', 'mt-4'],
        'id' => 'element-table',  // This ID is necessary for the infinite scroll JS to work.
      ],
    ];

    $cards_per_page = 9; // Number of cards to show per page
    $cards_displayed = 0;

    foreach ($output as $key => $item) {
      if ($cards_displayed >= $cards_per_page) {
        break; // Stop showing cards if limit is reached
      }

      // Use $key as the URI
      $uri = $key;

      // Generate a sanitized key for element names
      $sanitized_key = md5($uri);

      $form['element_cards'][$sanitized_key] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-md-4']],
      ];

      $form['element_cards'][$sanitized_key]['card'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['card', 'mb-4']],
      ];

      // Define the placeholder image URL
      $placeholder_image = base_path() . \Drupal::service('extension.list.module')->getPath('rep') . '/images/ins_placeholder.png';

      // Header text and content
      $header_text = '';
      $content = '<div class="row">';
      $content .= '<div class="col-md-8">';
      $content .= '<div class="card-body">';

      foreach ($header as $column_key => $column_label) {
        $value = isset($item[$column_key]) ? $item[$column_key] : '';
        if ($column_label == 'Downloads') {
          $value = str_replace(['<br>', '<br/>', '<br />'], '', $value);
        }
        if ($column_label == 'Name') {
          $header_text = preg_split('/<br\s*\/?>/i', $value)[0];
        }
        $content .= '<p class="mb-0 pb-0"><strong>' . $column_label . ':</strong> ' . $value . '</p>';
      }
      $content .= '</div>'; // Close card-body
      $content .= '</div>'; // Close left column

      // Right column with placeholder image
      $content .= '<div class="col-md-4">';
      $content .= '<div class="card-body text-center">';
      $content .= '<img style="border:1px solid #d7d7d7; border-radius:15px;" src="' . $placeholder_image . '" alt="' . $this->element_type . ' Placeholder Image" class="img-fluid" />';
      $content .= '</div>'; // Close card-body
      $content .= '</div>'; // Close right column

      $content .= '</div>'; // Close row

      // Header
      if (strlen($header_text) > 0) {
        $form['element_cards'][$sanitized_key]['card']['header'] = [
          '#type' => 'container',
          '#attributes' => [
            'style' => 'margin-bottom:0!important;',
            'class' => ['card-header', 'mb-0'],
          ],
          '#markup' => '<h4 class="mb-0">' . $header_text. '</h4>',
        ];
      }

      $form['element_cards'][$sanitized_key]['card']['content'] = [
        '#markup' => $content,
      ];

      // Card Footer (Action buttons)
      $form['element_cards'][$sanitized_key]['card']['footer'] = [
        '#type' => 'container',
        '#attributes' => [
          'style' => 'margin-bottom:0!important;',
          'class' => ['d-flex', 'card-footer', 'justify-content-end', 'mb-0'],
        ],
      ];

      $form['element_cards'][$sanitized_key]['card']['footer']['actions'] = [
        '#type' => 'actions',
        '#attributes' => [
          'style' => 'margin-bottom:0!important;',
          'class' => ['mb-0'],
        ],
      ];

      // Edit button
      $form['element_cards'][$sanitized_key]['card']['footer']['actions']['edit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit'),
        '#name' => 'edit_element_' . $sanitized_key,
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'btn-sm', 'edit-element-button'],
        ],
        '#submit' => ['::editElementSubmit'],
        '#limit_validation_errors' => [],
        '#element_uri' => $uri,
      ];

      // Delete button
      $form['element_cards'][$sanitized_key]['card']['footer']['actions']['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#name' => 'delete_element_' . $sanitized_key,
        '#attributes' => [
          'class' => ['btn', 'btn-danger', 'btn-sm', 'delete-element-button'],
          'onclick' => 'if(!confirm("Really Delete?")){return false;}',
        ],
        '#submit' => ['::deleteElementSubmit'],
        '#limit_validation_errors' => [],
        '#element_uri' => $uri,
      ];

      // Manage button (if applicable)
      if ($this->element_type == 'instrument') {
        $form['element_cards'][$sanitized_key]['card']['footer']['actions']['manage'] = [
          '#type' => 'submit',
          '#value' => $this->t('Manage Structure'),
          '#name' => 'manage_slotelements_' . $sanitized_key,
          '#attributes' => [
            'class' => ['btn', 'btn-secondary', 'btn-sm', 'manage_slotelements-button'],
          ],
          '#submit' => ['::manageSlotElementsSubmit'],
          '#limit_validation_errors' => [],
          '#element_uri' => $uri,
        ];
      } elseif ($this->element_type == 'codebook') {
        $form['element_cards'][$sanitized_key]['card']['footer']['actions']['manage'] = [
          '#type' => 'submit',
          '#value' => $this->t('Manage Response Option Slots'),
          '#name' => 'manage_codebookslots_' . $sanitized_key,
          '#attributes' => [
            'class' => ['btn', 'btn-secondary', 'btn-sm', 'manage_codebookslots-button'],
          ],
          '#submit' => ['::manageCodebookSlotsSubmit'],
          '#limit_validation_errors' => [],
          '#element_uri' => $uri,
        ];
      }

      // Derive button (if applicable)
      if ($this->element_type == 'detectorstem') {
        $form['element_cards'][$sanitized_key]['card']['footer']['actions']['derive'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive'),
          '#name' => 'derive_detectorstem_' . $sanitized_key,
          '#attributes' => [
            'class' => ['btn', 'btn-secondary', 'btn-sm', 'derive-button'],
          ],
          '#submit' => ['::deriveDetectorStemSubmit'],
          '#limit_validation_errors' => [],
          '#element_uri' => $uri,
        ];
      }

      $cards_displayed++; // Count each card rendered
    }

    // Show Load More button if there are more cards to load
    if (count($output) > $cards_per_page) {
      $form['load_more'] = [
        '#type' => 'submit',
        '#value' => $this->t('Load More'),
        '#attributes' => [
          'data-drupal-selector' => 'edit-load-more',
          'class' => ['btn', 'btn-primary', 'load-more-button'],
          'data-page' => $page,
          'data-elementtype' => $this->element_type,
        ],
        '#prefix' => '<div class="d-flex justify-content-center">',
        '#suffix' => '</div>',
        '#ajax' => [
          'callback' => '::loadMoreCallback',
          'wrapper' => 'element-table',
        ],
      ];
    }
  }

  /**
   * Callback for Load More button.
   */
  public function loadMoreCallback(array &$form, FormStateInterface $form_state) {
    $page = $form_state->getTriggeringElement()['#attributes']['data-page'];
    $this->buildCardView($form, $form_state, $page + 1, 9); // Load next 9 cards
    return $form['element_cards']; // Update the card section
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
    $uri = $triggering_element['#element_uri'];

    $this->performEdit($uri, $form_state);
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
    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // SET USER ID AND PREVIOUS URL FOR TRACKING STORE URLS
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    // Handle actions based on button name
    if (strpos($button_name, 'edit_element_') === 0) {
      $uri = $triggering_element['#element_uri'];
      $this->performEdit($uri, $form_state);
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
        \Drupal::messenger()->addWarning($this->t('Please select items to delete.'));
      }
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
    if (empty($uri)) {
      \Drupal::messenger()->addError($this->t('Cannot edit: URI is empty.'));
      return;
    }

    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    if ($this->element_type == 'instrument') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.edit_instrument');
      $url = Url::fromRoute('sir.edit_instrument', ['instrumenturi' => base64_encode($uri)]);
    } elseif ($this->element_type == 'detectorstem') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.edit_detectorstem');
      $url = Url::fromRoute('sir.edit_detectorstem', ['detectorstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'detector') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.edit_detector');
      $url = Url::fromRoute('sir.edit_detector', ['detectoruri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'codebook') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.edit_codebook');
      $url = Url::fromRoute('sir.edit_codebook', ['codebookuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'responseoption') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.edit_response_option');
      $url = Url::fromRoute('sir.edit_response_option', ['responseoptionuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'annotationstem') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.edit_annotationstem');
      $url = Url::fromRoute('sir.edit_annotationstem', ['annotationstemuri' => base64_encode($uri)]);
    }
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
