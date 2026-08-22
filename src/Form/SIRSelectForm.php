<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\Utils;
use Drupal\sir\Entity\AnnotationStem;
use Drupal\sir\Entity\ComponentStem;
use Drupal\sir\Entity\Component;
use Drupal\sir\Entity\Codebook;
use Drupal\sir\Entity\Instrument;
use Drupal\sir\Entity\ResponseOption;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Render\Markup;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Entity\Tables;
use Drupal\rep\ManageOwnerFilter;
use Drupal\rep\ListKeywordPage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\ListKeywordLanguagePage;
use Drupal\sir\Entity\Task;

use function Termwind\style;

class SIRSelectForm extends FormBase {

  /**
   * Filter trigger element names.
   */
  private const FILTER_TRIGGER_NAMES = [
    'text_filter',
    'language_filter',
    'manager_filter',
    'status_filter',
    'clear_filters',
  ];

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

    // This form is used for filtering/selecting records and does not persist
    // partial edits, so URI navigation should not be blocked by dirty guards.
    $form['#attributes']['data-rep-nav-guard-ignore'] = '1';

    // Normalize route params.
    $page = max(1, (int) ($page ?? 1));
    $pagesize = max(1, (int) ($pagesize ?? 9));
    $route_page = $page;
    $route_pagesize = $pagesize;

    // GET manager EMAIL
    $this->manager_email = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $this->manager_name = $user->name->value;

    // GET TOTAL NUMBER OF ELEMENTS
    $this->element_type = $elementtype;
    $pagesize_session_key = 'sir_select_form_pagesize.' . (string) $this->element_type;
    $view_type_session_key = 'sir_select_view_type.' . (string) $this->element_type;

    // Extra safety: avoid reusing page-size state when element type changes.
    $page_size_owner = (string) ($form_state->get('page_size_owner') ?? '');
    if ($page_size_owner !== (string) $this->element_type) {
      $form_state->set('page_size', NULL);
      $form_state->set('previous_page_size', NULL);
      $form_state->set('page_size_owner', (string) $this->element_type);
    }

    // PREVENT THE ACCESS TO COMON USERS
    $has_content_editor_role = in_array('content_editor', \Drupal::currentUser()->getRoles());
    if ($this->element_type == 'task' && !$has_content_editor_role) {
      \Drupal::messenger()->addError($this->t('Insuficient Permissions to access that area.'));
      $url = Url::fromRoute('rep.home')->toString();
      return new RedirectResponse($url);
    }

    $session = \Drupal::request()->getSession();

    // Search values filter
    $status_filter_key = 'sir_select_status_filter.' . (string) $this->element_type;
    $status_filter = $form_state->getValue('status_filter');
    if ($status_filter === NULL) {
      $status_filter = $session->get($status_filter_key, '_');
    }
    else {
      $session->set($status_filter_key, $status_filter);
    }

    $language_filter_key = 'sir_select_language_filter.' . (string) $this->element_type;
    $language_filter = $form_state->getValue('language_filter');
    if ($language_filter === NULL) {
      $language_filter = $session->get($language_filter_key, '_');
    }
    else {
      $session->set($language_filter_key, $language_filter);
    }

    $text_filter_key = 'sir_select_text_filter.' . (string) $this->element_type;
    $text_filter = $form_state->getValue('text_filter');
    if ($text_filter === NULL) {
      $text_filter = $session->get($text_filter_key, '');
    }
    else {
      $session->set($text_filter_key, $text_filter);
    }

    $is_admin = ManageOwnerFilter::isAdmin();
    $manager_filter_key = 'sir_select_manager_filter.' . (string) $this->element_type;
    $manager_filter = $form_state->getValue('manager_filter');
    if ($manager_filter === NULL) {
      $manager_filter = $session->get($manager_filter_key, '');
    }
    else {
      $manager_filter = ManageOwnerFilter::normalizeSelectedEmail($manager_filter);
      $session->set($manager_filter_key, $manager_filter);
    }

    $type = NULL;
    $manager_email = ManageOwnerFilter::resolveEffectiveOwner($this->manager_email, $manager_filter, $status_filter);

    // Content editors can update selected SIR element types regardless of owner.
    $contentEditorBypassTypes = ['instrument', 'component', 'codebook', 'responseoption', 'annotation', 'annotationstem'];
    if (in_array($this->element_type, $contentEditorBypassTypes, TRUE)
      && in_array('content_editor', \Drupal::currentUser()->getRoles(), TRUE)) {
      $manager_email = '_';
    }
    $form_state->set('effective_manager_email', $manager_email);
    $form_state->set('sir_select_filters', [
      'text_filter' => (string) $text_filter,
      'language_filter' => (string) $language_filter,
      'status_filter' => (string) $status_filter,
      'manager_email' => (string) $manager_email,
    ]);
    $status = $status_filter;

    // Get elements respecting pagination even when filters are applied.
    // (Historically this forced pagesize=9999 when filtering, which broke pagination.)
    $has_text_filter = trim((string) $text_filter) !== '';
    $has_language_filter = !($language_filter === '_' || $language_filter === '' || $language_filter === NULL);
    $has_status_filter = !($status_filter === '_' || $status_filter === '' || $status_filter === NULL);
    $using_owner_only_filters = (!$has_text_filter && !$has_language_filter && !$has_status_filter);
    $using_status_only_filter = ($has_status_filter && !$has_text_filter && !$has_language_filter);

    if ($using_owner_only_filters) {
      $this->setListSize(ListManagerEmailPage::total($this->element_type, $manager_email));
    }
    elseif ($using_status_only_filter) {
      $this->setListSize(ListManagerEmailPage::totalByStatusManagerEmail($this->element_type, $status_filter, $manager_email, FALSE));
    }
    else {
      $this->setListSize(ListKeywordLanguagePage::total($this->element_type, $text_filter, $language_filter, $type, $manager_email, $status));
    }

    $size = (is_numeric($this->getListSize()) ? (int) $this->getListSize() : -1);
    $total_pages = ($size > 0 && $pagesize > 0) ? (int) ceil($size / $pagesize) : 1;
    $page = max(1, min($page, max(1, $total_pages)));

    if ($using_owner_only_filters) {
      $this->setList(ListManagerEmailPage::exec($this->element_type, $manager_email, $page, $pagesize));
    }
    elseif ($using_status_only_filter) {
      $this->setList(ListManagerEmailPage::execByStatusManagerEmail($this->element_type, $status_filter, $manager_email, FALSE, $page, $pagesize));
    }
    else {
      $this->setList(ListKeywordLanguagePage::exec($this->element_type, $text_filter, $language_filter, $type, $manager_email, $status, $page, $pagesize));
    }

    // if ($this->element_type != NULL) {
    //   $this->setListSize(ListManagerEmailPage::total($this->element_type, $this->manager_email));
    // }

    // Retrieve or set default view type
    $view_type = $form_state->get('view_type');
    if ($view_type === NULL) {
      $view_type = $session->get($view_type_session_key, 'table');
    }
    $form_state->set('view_type', $view_type);
    $table_active_class = ($view_type === 'table') ? ['selected-button'] : [];
    $card_active_class = ($view_type === 'card') ? ['selected-button'] : [];

    // Attach necessary libraries
    $form['#attached']['library'][] = 'core/drupal.bootstrap';

    $form['#attached']['library'][] = 'core/jquery';
    $form['#attached']['library'][] = 'core/jquery.once';
    $form['#attached']['library'][] = 'core/drupal';
    $form['#attached']['library'][] = 'core/drupal.ajax';
    $form['#attached']['library'][] = 'core/drupalSettings';
    $form['#attached']['library'][] = 'sir/sir_js_css';

    // Card view lazyload/infinite-scroll.
    if ($view_type == 'card') {
      $form['#attached']['library'][] = 'rep/infinitescroll';
    }


    $form['#attached']['drupalSettings']['sir_select_form']['base_url'] = (\Drupal::request()->headers->get('x-forwarded-proto') === 'https' ? 'https://':'http://'). \Drupal::request()->getHost() . \Drupal::request()->getBaseUrl();
    $form['#attached']['drupalSettings']['sir_select_form']['elementtype'] = $elementtype;
    $form['#attached']['drupalSettings']['sir_select_form']['disable_auto_scroll'] = ($view_type == 'card');

    // Get value `pagesize` (default 9)
    // Only override in CARD view (used by infinite scroll / "Load more").
    if ($view_type == 'card') {
      $triggering_element = $form_state->getTriggeringElement();
      $trigger_name = (string) ($triggering_element['#name'] ?? '');
      $is_filter_trigger = in_array($trigger_name, self::FILTER_TRIGGER_NAMES, TRUE);

      if ($is_filter_trigger) {
        $form_state->set('page_size', NULL);
        $form_state->set('previous_page_size', NULL);
        $session->remove($pagesize_session_key);
      }

      $card_page_size = $form_state->get('page_size');
      if ($card_page_size === NULL) {
        $session_page_size = $session->get($pagesize_session_key);
        if (is_numeric($session_page_size) && (int) $session_page_size > 0) {
          $card_page_size = (int) $session_page_size;
        }
        else {
          // When entering card view from table page N, preload N * pageSize cards.
          $card_page_size = $route_page * $route_pagesize;
        }
        $form_state->set('page_size', (int) $card_page_size);
      }

      // Same strategy used in stable card modules: increase page size based on
      // the triggering element, independent of submit callback ordering.
      $is_load_more_trigger = $triggering_element && (($triggering_element['#name'] ?? '') === 'load_more_button');
      if ($is_load_more_trigger) {
        $card_page_size = max(1, (int) $card_page_size);
        $form_state->set('previous_page_size', $card_page_size);
        $card_page_size += 9;
        $form_state->set('page_size', (int) $card_page_size);
      }
      else {
        $form_state->set('previous_page_size', NULL);
      }

      $pagesize = max(1, (int) $card_page_size);
      $session->set($pagesize_session_key, $pagesize);

      // Card mode is cumulative, so always query from the first page.
      $page = 1;
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

    $show_owner_indicator = $is_admin && $manager_filter !== '' && strcasecmp($manager_email, $manager_filter) === 0;
    if ($show_owner_indicator) {
      $form['owner_indicator'] = [
        '#type' => 'item',
        '#markup' => $this->t('<div class="alert alert-info py-2 mb-3"><strong>A visualizar owner:</strong> @owner</div>', [
          '@owner' => $manager_email,
        ]),
      ];
    }

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
        'class' => array_merge(['table-view-button', 'fa-xl', 'mx-1'], $table_active_class),
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
        'class' => array_merge(['card-view-button', 'fa-xl'], $card_active_class),
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
            'class' => ['sir-manage-toolbar', 'mb-3'],
        ],
      ];

      $form['actions_wrapper']['buttons_container'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'flex-wrap', 'gap-2', 'align-items-stretch', 'sir-manage-buttons'],
        ],
      ];

      $form['actions_wrapper']['buttons_container']['add_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add New ' . $this->single_class_name),
        '#name' => 'add_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'add-element-button'],
        ],
      ];
      if ($this->element_type == 'componentstem') {
        $form['actions_wrapper']['buttons_container']['derive_componentstem'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $this->single_class_name),
          '#name' => 'derive_componentstem',
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
          '#value' => $this->t('Send for Review'),
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
        // Check if the current user has the "Content editor" role.
        // Note: Role machine names are case-sensitive. Adjust the string if your role machine name is different.

        // ************** MOVED TO EDITOR MENU *******************

        // $has_content_editor_role = in_array('content_editor', \Drupal::currentUser()->getRoles());
        // $form['actions_wrapper']['buttons_container']['generate_ins_select_element'] = [
        //   '#type' => 'submit',
        //   '#value' => $this->t('Generate INS'),
        //   '#name' => 'generate_ins_element',
        //   '#attributes' => [
        //     // 'onclick' => 'if(!confirm("Are you sure you want to generate an INS file?")){return false;}',
        //     'class' => ['btn', 'btn-primary', 'generate-ins-element-button'],
        //   ],
        //   // Render the button only if the user has the "Content editor" role.
        //   '#access' => $has_content_editor_role,
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

      $this->buildFiltersPanel($form, $view_type, $is_admin, (string) $text_filter, (string) $language_filter, (string) $manager_filter, (string) $status_filter);

    } else {
      $form['actions_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
            'class' => ['sir-manage-toolbar', 'mb-3'],
        ],
      ];

      $form['actions_wrapper']['buttons_container'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['d-flex', 'flex-wrap', 'gap-2', 'align-items-stretch', 'sir-manage-buttons'],
        ],
      ];

      // In card view, add 'Add New' button at the top
      $form['actions_wrapper']['buttons_container']['add_element'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add New ' . $this->single_class_name),
        '#name' => 'add_element',
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'add-element-button', 'mb-3'],
        ],
      ];
      if ($this->element_type == 'componentstem') {
        $form['actions_wrapper']['buttons_container']['derive_componentstem'] = [
          '#type' => 'submit',
          '#value' => $this->t('Derive New ' . $this->single_class_name),
          '#name' => 'derive_componentstem',
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'derive-button', 'mb-3'],
          ],
        ];
      }

      $this->buildFiltersPanel($form, $view_type, $is_admin, (string) $text_filter, (string) $language_filter, (string) $manager_filter, (string) $status_filter);
    }

    // Render output based on view type
    if ($view_type == 'table') {
      $this->buildTableView($form, $form_state, $page, $pagesize);
    } elseif ($view_type == 'card') {
      $form['cards_lazy_wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'cards-lazy-wrapper',
        ],
      ];

      $this->buildCardView($form['cards_lazy_wrapper'], $form_state, $page, $pagesize);

      $form['cards_lazy_wrapper']['records_count'] = [
        '#type' => 'item',
        '#markup' => $this->t('<div id="count-cards" style="font-weight:bold; margin-top:10px; padding-right:2rem;">Currently viewing @count of @total @class</div>', [
          '@count' => count($this->getList()),
          '@total' => (int) $this->getListSize(),
          '@class' => $this->plural_class_name,
        ]),
      ];

      // Load-more controls (used by infinite scroll).
      $total_items = $this->getListSize();
      $current_page_size = $form_state->get('page_size') ?? 9;

      if (is_numeric($total_items) && (int) $total_items > (int) $current_page_size) {
        $form['cards_lazy_wrapper']['load_more_button'] = [
          '#type' => 'submit',
          '#value' => $this->t('Load More'),
          '#name' => 'load_more_button',
          '#executes_submit_callback' => TRUE,
          '#attributes' => [
            'class' => ['btn', 'btn-primary', 'load-more-button'],
            'style' => 'display:block;margin:1.5rem auto 2rem;',
          ],
          '#submit' => ['::loadMoreSubmit'],
          '#ajax' => [
            'callback' => '::ajaxReloadCards',
            'wrapper' => 'cards-lazy-wrapper',
            'event' => 'click',
            'disable-refocus' => TRUE,
            'progress' => [
              'type' => 'none',
            ],
          ],
          '#limit_validation_errors' => [],
        ];

        $form['cards_lazy_wrapper']['list_state'] = [
          '#type' => 'hidden',
          '#value' => 1,
          '#attributes' => [
            'id' => 'list_state',
          ],
        ];
      }
    }

    $form['space_0'] = [
      '#type' => 'item',
      '#markup' => '<br><br>',
    ];

    $form['notes'] = [
      '#type' => 'markup',
      '#markup' => '<div class="info-label" style="margin-top:2rem"!important;">Informative Notes:</div>
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
    $preferred_component = \Drupal::config('rep.settings')->get('preferred_component') ?? 'Component';
    switch ($this->element_type) {

      // INSTRUMENT
      case "instrument":
        $this->single_class_name = $preferred_instrument;
        $this->plural_class_name = $preferred_instrument . "s";
        break;

      // COMPONENTSTEM
      case "componentstem":
        $this->single_class_name = $preferred_component . " Stem";
        $this->plural_class_name = $preferred_component . " Stems";
        break;

      // COMPONENT
      case "component":
        $this->single_class_name = $preferred_component;
        $this->plural_class_name = $preferred_component . "s";
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

      case "uberon":
        $this->single_class_name = "Anatomical Category";
        $this->plural_class_name = "Anatomical Categories";
        break;

      case "anatomicalpart":
        $this->single_class_name = "Anatomical Part";
        $this->plural_class_name = "Anatomical Parts";
        break;

      case "ncit":
        $this->single_class_name = "Procedure Type";
        $this->plural_class_name = "Procedure Types";
        break;

      case "medicaldevice":
        $this->single_class_name = "Medical Device";
        $this->plural_class_name = "Medical Devices";
        break;

      case "process":
        $this->single_class_name = "Process";
        $this->plural_class_name = "Processes";
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
    // // Retrieve the filtered status
    // $status_filter = $form_state->getValue('status_filter') ?? 'all';
    // $language_filter = $form_state->getValue('language_filter') ?? 'all';
    // $text_filter = $form_state->getValue('text_filter') ?? '';

    // // // Convert the text filter to lowercase for case-insensitive comparison
    // // $text_filter = strtolower($text_filter);

    // if (strlen($text_filter) === 0 ) {
    //   $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));
    //   $this->setListSize(ListManagerEmailPage::total($this->element_type, $this->manager_email));
    // } elseif (strlen($text_filter) > 0 && $language_filter !== 'all') {
    //   $this->setList(ListKeywordLanguagePage::exec($this->element_type,$text_filter, $language_filter, $page, 99999999));
    //   $this->setListSize(ListKeywordLanguagePage::total($this->element_type, $text_filter, $language_filter));
    //   // $pagesize = 99999999;
    // } else {
    //   $this->setList(ListKeywordPage::exec($this->element_type, $text_filter, 1, 99999999));
    //   $this->setListSize(ListKeywordPage::total($this->element_type, $text_filter, 1, 99999999));
    //   // $pagesize = 99999999;
    // }

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
        $row_status = strtolower((string) ($row['element_hasStatus'] ?? ''));
        $row_language = strtolower((string) ($row['element_hasLanguage'] ?? ''));

        if ($this->element_type == 'instrument' || $this->element_type == 'codebook')
          $row_label = strtolower((string) ($row['element_name'] ?? ''));
        else if ($this->element_type == 'component' || $this->element_type == 'componentstem' || $this->element_type == 'responseoption')
          $row_label = strtolower((string) ($row['element_content'] ?? ''));
        else
          $row_label = '';

        // if ($status_filter !== 'all' && $row_status !== $status_filter) {
        //     continue;
        // }

        // if ($language_filter !== 'all' && $row_language !== $language_filter) {
        //     continue;
        // }

        // // Use strpos to check if the text filter is contained in the label.
        // if ($text_filter !== '' && strpos($row_label, $text_filter) === false) {
        //     continue;
        // }

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
            if ($field_key !== 'element_hasStatus' && $field_key !== 'element_hasLanguage' && $field_key !== 'element_hasImageUri') {
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
      $form_state->setRebuild(TRUE);
      return $form['element_table_wrapper'];
  }

  /**
   * AJAX callback to reload card view when loading more.
   */
  public function ajaxReloadCards(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);

    $triggering_element = $form_state->getTriggeringElement();
    $trigger_name = (string) ($triggering_element['#name'] ?? '');

    if ($trigger_name === 'load_more_button') {
      $response = new AjaxResponse();

      $previous = (int) ($form_state->get('previous_page_size') ?? 0);
      $cards_container = $form['cards_lazy_wrapper']['cards_wrapper'] ?? [];

      $card_keys = [];
      if (is_array($cards_container)) {
        foreach (array_keys($cards_container) as $key) {
          if (is_string($key) && strpos($key, 'card_') === 0) {
            $card_keys[] = $key;
          }
        }
      }

      $previous = max(0, min($previous, count($card_keys)));
      $new_keys = array_slice($card_keys, $previous);
      $append_build = [];
      foreach ($new_keys as $k) {
        $append_build[$k] = $cards_container[$k];
      }

      if (!empty($append_build)) {
        $rendered = (string) \Drupal::service('renderer')->renderPlain($append_build);
        if (trim($rendered) !== '') {
          $response->addCommand(new AppendCommand('#cards-wrapper', $rendered));
        }
      }

      $loaded = count($card_keys);
      $total = (int) ($this->getListSize() ?? 0);
      $has_more = $total > $loaded;

      $count_markup = '<div id="count-cards" style="font-weight:bold; margin-top:10px; padding-right:2rem;">'
        . $this->t('Currently viewing @count of @total @class', [
          '@count' => $loaded,
          '@total' => $total,
          '@class' => $this->plural_class_name,
        ])
        . '</div>';
      $response->addCommand(new ReplaceCommand('#count-cards', $count_markup));

      $response->addCommand(new InvokeCommand('#list_state', 'val', [$has_more ? 1 : 0]));
      if (!$has_more) {
        $response->addCommand(new InvokeCommand('#load-more-button, #edit-load-more-button', 'hide', []));
      }

      return $response;
    }

    return $form['cards_lazy_wrapper'];
  }

  /**
   * Build the reusable filters panel for table and card views.
   */
  protected function buildFiltersPanel(array &$form, string $view_type, bool $is_admin, string $text_filter, string $language_filter, string $manager_filter, string $status_filter): void {
    if (!isset($form['actions_wrapper'])) {
      return;
    }

    $ajax_callback = ($view_type === 'card') ? '::ajaxReloadCards' : '::ajaxReloadTable';
    $ajax_wrapper = ($view_type === 'card') ? 'cards-lazy-wrapper' : 'element-table-wrapper';

    $status_options = [
      '_' => $this->t('All Status'),
      VSTOI::DRAFT => $this->t('Draft'),
      VSTOI::UNDER_REVIEW => $this->t('Under Review'),
      VSTOI::CURRENT => $this->t('Current'),
      VSTOI::DEPRECATED => $this->t('Deprecated'),
    ];

    $has_active_filters = trim((string) $text_filter) !== ''
      || $language_filter !== '_'
      || $status_filter !== '_'
      || ($is_admin && trim((string) $manager_filter) !== '');

    $form['actions_wrapper']['filters_panel'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter(s)'),
      '#open' => $has_active_filters,
      '#attributes' => [
        'class' => ['sir-manage-filters-panel'],
      ],
    ];

    $form['actions_wrapper']['filters_panel']['filter_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['row', 'g-2', 'align-items-end', 'sir-manage-filters'],
      ],
    ];

    $form['actions_wrapper']['filters_panel']['filter_container']['text_filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword'),
      '#title_display' => 'invisible',
      '#default_value' => $text_filter,
      '#prefix' => '<div class="col-12 col-lg-4">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => $ajax_callback,
        'wrapper' => $ajax_wrapper,
        'event' => 'change',
      ],
      '#attributes' => [
        'class' => ['form-control'],
        'placeholder' => 'Type in your search criteria',
        'onkeydown' => 'if (event.keyCode == 13) { event.preventDefault(); this.blur(); }',
      ],
    ];

    if ($this->element_type !== 'component') {
      $tables = new Tables;
      $languages = $tables->getLanguages();
      if ($languages) {
        $languages = ['_' => $this->t('All Languages')] + $languages;
      }

      $form['actions_wrapper']['filters_panel']['filter_container']['language_filter'] = [
        '#type' => 'select',
        '#title' => $this->t('Language'),
        '#title_display' => 'invisible',
        '#options' => $languages,
        '#default_value' => $language_filter,
        '#prefix' => '<div class="col-12 col-md-4 col-lg-2">',
        '#suffix' => '</div>',
        '#ajax' => [
          'callback' => $ajax_callback,
          'wrapper' => $ajax_wrapper,
          'event' => 'change',
        ],
        '#attributes' => [
          'class' => ['form-select'],
        ],
      ];
    }

    if ($is_admin) {
      $form['actions_wrapper']['filters_panel']['filter_container']['manager_filter'] = [
        '#type' => 'textfield',
        '#title' => $this->t('User'),
        '#title_display' => 'invisible',
        '#default_value' => $manager_filter,
        '#prefix' => '<div class="col-12 col-md-8 col-lg-4">',
        '#suffix' => '</div>',
        '#ajax' => [
          'callback' => $ajax_callback,
          'wrapper' => $ajax_wrapper,
          'event' => 'change',
        ],
        '#attributes' => [
          'class' => ['form-control'],
          'placeholder' => $this->t('User email (owner filter)'),
        ],
      ];
    }

    $form['actions_wrapper']['filters_panel']['filter_container']['status_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#title_display' => 'invisible',
      '#options' => $status_options,
      '#default_value' => $status_filter,
      '#prefix' => '<div class="col-12 col-md-4 col-lg-2">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => $ajax_callback,
        'wrapper' => $ajax_wrapper,
        'event' => 'change',
      ],
      '#attributes' => [
        'class' => ['form-select'],
      ],
    ];

    $form['actions_wrapper']['filters_panel']['filter_container']['clear_filters'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Filters'),
      '#name' => 'clear_filters',
      '#limit_validation_errors' => [],
      '#prefix' => '<div class="col-12 col-md-4 col-lg-2 d-grid">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['btn', 'btn-outline-secondary'],
      ],
    ];
  }

  /**
   * Clear persisted table filters for the current element type.
   */
  protected function clearSavedFilters(FormStateInterface $form_state): void {
    $session = \Drupal::request()->getSession();
    $suffix = (string) $this->element_type;

    foreach (['status', 'language', 'text', 'manager'] as $key) {
      $session->remove('sir_select_' . $key . '_filter.' . $suffix);
    }

    $input = $form_state->getUserInput();
    unset($input['text_filter'], $input['language_filter'], $input['manager_filter'], $input['status_filter']);
    $form_state->setUserInput($input);

    $form_state->setValue('text_filter', '');
    $form_state->setValue('language_filter', '_');
    $form_state->setValue('manager_filter', '');
    $form_state->setValue('status_filter', '_');
    $form_state->setRebuild(TRUE);
  }


  /**
   * Build the card view.
   */
  protected function buildCardView(array &$form, FormStateInterface $form_state, $page, $pagesize, $addMore = false) {
    // Card mode must honor active filters exactly like table mode.
    $filters = $form_state->get('sir_select_filters') ?? [];
    $effective_manager_email = (string) ($filters['manager_email'] ?? ($form_state->get('effective_manager_email') ?? $this->manager_email));
    $text_filter = (string) ($filters['text_filter'] ?? '');
    $language_filter = (string) ($filters['language_filter'] ?? '_');
    $status_filter = (string) ($filters['status_filter'] ?? '_');

    $has_text_filter = trim((string) $text_filter) !== '';
    $has_language_filter = !($language_filter === '_' || $language_filter === '');
    $has_status_filter = !($status_filter === '_' || $status_filter === '');

    if (!$has_text_filter && !$has_language_filter && !$has_status_filter) {
      $this->setList(ListManagerEmailPage::exec($this->element_type, $effective_manager_email, $page, $pagesize));
    }
    elseif ($has_status_filter && !$has_text_filter && !$has_language_filter) {
      $this->setList(ListManagerEmailPage::execByStatusManagerEmail($this->element_type, $status_filter, $effective_manager_email, FALSE, $page, $pagesize));
    }
    else {
      $this->setList(ListKeywordLanguagePage::exec($this->element_type, $text_filter, $language_filter, '_', $effective_manager_email, $status_filter, $page, $pagesize));
    }

    // Generate header and output
    $header = $this->generateHeader();
    //$output = $this->generateOutput();
    $results = $this->generateOutput();
    $output = $results['output'];
    $disabled_rows = $results['disabled_rows'];

    // Define Placeholder image
    switch ($this->element_type) {
      case 'instrument':
        $placeholder_image = base_path() . \Drupal::service('extension.list.module')->getPath('rep') . '/images/placeholders/instrument_placeholder.png';
        break;
      case 'component':
        $placeholder_image = base_path() . \Drupal::service('extension.list.module')->getPath('rep') . '/images/placeholders/component_placeholder.png';
        break;
      case 'componentstem':
        $placeholder_image = base_path() . \Drupal::service('extension.list.module')->getPath('rep') . '/images/placeholders/component_stem_placeholder.png';
        break;
      case 'codebook':
        $placeholder_image = base_path() . \Drupal::service('extension.list.module')->getPath('rep') . '/images/placeholders/codebook_placeholder.png';
        break;
      case 'responseoption':
        $placeholder_image = base_path() . \Drupal::service('extension.list.module')->getPath('rep') . '/images/placeholders/responseoption_placeholder.png';
        break;
      case 'annotationstem':
        $placeholder_image = base_path() . \Drupal::service('extension.list.module')->getPath('rep') . '/images/placeholders/annotation_stem_placeholder.png';
        break;
    }

    // Se não estiver adicionando mais, crie o wrapper principal
    if (!$addMore) {

      $form['loading_overlay'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'sir-loading-overlay',
          'class' => ['sir-loading-overlay'],
          'style' => 'display: none;',
        ],
        '#markup' => '<div class="sir-loading-overlay__panel" role="status" aria-live="polite" aria-busy="true"><div class="spinner-border text-light sir-loading-overlay__spinner" aria-hidden="true"></div><span class="sir-loading-overlay__text">' . $this->t('Loading more results...') . '</span></div>',
      ];

      $form['cards_wrapper'] = [
          '#type' => 'container',
          '#attributes' => [
              'id' => 'cards-wrapper',
              'class' => ['row'],
          ],
      ];
    }

    if (empty($output)) {
      $form['cards_wrapper']['no_results'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['col-12', 'mt-3']],
        'message' => [
          '#markup' => '<div class="alert alert-info mb-0">'
            . $this->t('No @items found for the current filters.', ['@items' => $this->plural_class_name])
            . '</div>',
        ],
      ];
      return;
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
        $image_src = Utils::getAPIImage($key, $item_vars['element_hasImageUri'], $placeholder_image);

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
                  'class' => ['col-md-5', 'd-flex', 'justify-content-center', 'align-items-center'],
                  'data-drupal-selector' => 'edit-image-column',
                  'id' => 'edit-image-column--' . md5($uri),
                ],
                'image' => [
                  '#theme' => 'image',
                  '#uri' => $image_src,
                  '#alt' => $this->t('Image for @name', ['@name' => $item_vars['label'] ?? '']),
                  '#attributes' => [
                    'class' => ['img-fluid', 'mb-0', 'border', 'border-5', 'rounded', 'rounded-5'],
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
    }

    // Final Form Debbug
    #\Drupal::logger('sir_select_form')->debug('Estado final do formulário após buildCardView: @form', ['@form' => print_r($form, TRUE)]);
  }

  /**
   * Submit handler for the Load More button.
   */
  public function loadMoreSubmit(array &$form, FormStateInterface $form_state) {
    // Safety fallback: if buildForm did not detect the triggering element,
    // increment page_size here to guarantee Load More progression.
    $current_page_size = (int) ($form_state->get('page_size') ?? 9);
    $previous_page_size = (int) ($form_state->get('previous_page_size') ?? 0);

    if ($previous_page_size < 1 || $previous_page_size >= $current_page_size) {
      $form_state->set('previous_page_size', $current_page_size);
      $current_page_size += 9;
      $form_state->set('page_size', $current_page_size);

      $session = \Drupal::request()->getSession();
      $pagesize_session_key = 'sir_select_form_pagesize.' . (string) $this->element_type;
      $session->set($pagesize_session_key, $current_page_size);
    }

    $form_state->setRebuild();
  }

  /**
   * Generate header based on element type.
   */
  protected function generateHeader() {
    switch ($this->element_type) {
      case "instrument":
        return Instrument::generateHeader();
      case "componentstem":
        return ComponentStem::generateHeader();
      case "component":
        return Component::generateHeader();
      case "codebook":
        return Codebook::generateHeader();
      case "responseoption":
        return ResponseOption::generateHeader();
      case "annotationstem":
        return AnnotationStem::generateHeader();
      case "task":
        return Task::generateHeader();
      default:
        return $this->generateGenericHeader();
    }
  }

  /**
   * Generate output based on element type.
   */
  protected function generateOutput() {
    switch ($this->element_type) {
      case "instrument":
        return Instrument::generateOutput($this->getList());
      case "componentstem":
        return ComponentStem::generateOutput($this->getList());
      case "component":
        return Component::generateOutput($this->getList());
      case "codebook":
        return Codebook::generateOutput($this->getList());
      case "responseoption":
        return ResponseOption::generateOutput($this->getList());
      case "annotationstem":
        return AnnotationStem::generateOutput($this->getList());
      case "task":
        return Task::generateOutput($this->getList());
      default:
        return $this->generateGenericOutput($this->getList());
    }
  }

  /**
   * Fallback header for element types without dedicated entity table builders.
   */
  protected function generateGenericHeader() {
    return [
      'element_label' => $this->t('Label'),
      'element_uri' => $this->t('URI'),
      'element_hasStatus' => $this->t('Status'),
      'element_hasLanguage' => $this->t('Language'),
    ];
  }

  /**
   * Fallback output for element types without dedicated entity table builders.
   */
  protected function generateGenericOutput($list) {
    $rows = [];
    if (!is_array($list)) {
      return ['output' => $rows];
    }

    foreach ($list as $item) {
      if (!is_object($item)) {
        continue;
      }

      $uri = (string) ($item->uri ?? '');
      if ($uri === '') {
        continue;
      }

      $label = (string) ($item->label ?? ($item->name ?? ''));
      if ($label === '') {
        $label = Utils::namespaceUri($uri);
      }

      $rows[] = [
        'element_label' => '<a href="' . Url::fromRoute('sir.describe_element', ['elementuri' => base64_encode($uri)])->toString() . '">' . $label . '</a>',
        'element_uri' => $uri,
        'element_hasStatus' => (string) ($item->hasStatus ?? ''),
        'element_hasLanguage' => (string) ($item->hasLanguage ?? ''),
      ];
    }

    return ['output' => $rows];
  }

  /**
   * Submit handler for table view toggle.
   */
  public function viewTableSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('view_type', 'table');
    // Update the view type in the session
    $session = \Drupal::request()->getSession();
    $view_type_session_key = 'sir_select_view_type.' . (string) $this->element_type;
    $pagesize_session_key = 'sir_select_form_pagesize.' . (string) $this->element_type;
    $session->set($view_type_session_key, 'table');
    $session->set('sir_select_view_type', 'table');
    $session->remove($pagesize_session_key);
    $form_state->set('page_size', NULL);
    $form_state->set('previous_page_size', NULL);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for card view toggle.
   */
  public function viewCardSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('view_type', 'card');
    // Update the view type in the session
    $session = \Drupal::request()->getSession();
    $view_type_session_key = 'sir_select_view_type.' . (string) $this->element_type;
    $pagesize_session_key = 'sir_select_form_pagesize.' . (string) $this->element_type;
    $session->set($view_type_session_key, 'card');
    $session->set('sir_select_view_type', 'card');
    // Force a clean card initialization from current route page/pageSize.
    $session->remove($pagesize_session_key);
    $form_state->set('page_size', NULL);
    $form_state->set('previous_page_size', NULL);
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
        'componentstem' => 'sir.edit_componentstem',
        'component' => 'sir.edit_component',
        'codebook' => 'sir.edit_codebook',
        'responseoption' => 'sir.edit_response_option',
        'annotationstem' => 'sir.edit_annotationstem',
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
   * Submit handler for deriving a component stem in card view.
   */
  public function deriveComponentStemSubmit(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $uri = $triggering_element['#element_uri'];

    $this->performDeriveComponentStem($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'clear_filters') {
      $this->clearSavedFilters($form_state);
      return;
    }

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
    } elseif (strpos($button_name, 'derive_componentstem_') === 0) {
      // // $uri = $triggering_element['#element_uri'];
      // $uri = array_keys($selected_rows)[0];
      // $this->performDeriveComponentStem($uri, $form_state);
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
      if ($form_state->getValue('element_table') !== "") {
        if (count($selected_rows) > 0) {
          $selected_uris = array_keys($selected_rows);
          $this->performReviewRecursive($selected_uris, $form_state);
        } else {
          \Drupal::messenger()->addWarning($this->t('Please select item(s) to submit for review.'));
        }
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select item(s) to submit for review.'));
      }
      // // HAS ELEMENTS
      // if (count($selected_rows) == 1) {
      //   $uri = array_keys($selected_rows)[0];
      //   $selected_rows = array_filter($form_state->getValue('element_table'));
      //   if (!empty($selected_rows)) {
      //     $selected_uris = array_keys($selected_rows);
      //     $this->performReviewRecursive($selected_uris, $form_state);
      //   } else {
      //     \Drupal::messenger()->addWarning($this->t('Please select item(s) to submit for recursive review.'));
      //   }
      // } else {
      //   \Drupal::messenger()->addWarning($this->t('Please select item(s) to submit for recursive review.'));
      // }
    } elseif ($button_name === 'generate_ins_element') {

      $uid = \Drupal::currentUser()->id();
      $previousUrl = \Drupal::request()->getRequestUri();
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.generate_ins');
      $url = Url::fromRoute('sir.generate_ins');
      $form_state->setRedirectUrl($url);
      // \Drupal::messenger()->addWarning($this->t('Under Development'));

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
    } elseif ($button_name === 'derive_componentstem') {
      // $url = Url::fromRoute('sir.add_componentstem');
      // $url->setRouteParameter('sourcecomponentstemuri', 'DERIVED');
      // $form_state->setRedirectUrl($url);
      $this->performDeriveComponentStem($form_state);
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
    } elseif ($this->element_type == 'componentstem') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_componentstem');
      $url = Url::fromRoute('sir.add_componentstem');
      $url->setRouteParameter('sourcecomponentstemuri', 'EMPTY');
    } elseif ($this->element_type == 'component') {
      Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_component');
      $url = Url::fromRoute('sir.add_component');
      $url->setRouteParameter('sourcecomponenturi', 'EMPTY');
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
    } elseif ($this->element_type == 'componentstem') {
      $url = Url::fromRoute('sir.edit_componentstem', ['componentstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'component') {
      $url = Url::fromRoute('sir.edit_component', ['componenturi' => base64_encode($uri)]);
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

  //   $form_state->setRebuild();
  // }
  protected function performDelete(array $uris, FormStateInterface $form_state) {
    /** @var \Drupal\rep\ApiConnectorInterface $api */
    $api = \Drupal::service('rep.api_connector');

    foreach ($uris as $shortUri) {
      $uri = Utils::plainUri($shortUri);
      $resp = $api->elementDel($this->element_type, $uri);

      // 1) Normalize resp into a JSON string
      if (is_string($resp)) {
        $json = $resp;
      }
      elseif (method_exists($resp, 'getContents')) {
        // Some connectors return a Symfony Response
        $json = $resp->getContents();
      }
      elseif (method_exists($resp, 'getBody')) {
        // PSR-7 Response
        $json = $resp->getBody()->getContents();
      }
      else {
        $json = '';
      }

      // 2) Decode and check
      $msg = json_decode($json);
      if ($msg && !empty($msg->isSuccessful)) {
        \Drupal::messenger()->addMessage($this->t(
          'Selected @elements have been deleted successfully.',
          ['@elements' => $this->plural_class_name]
        ));
      }
      else {
        \Drupal::messenger()->addError($this->t(
          'Failed to delete @elements; response was: @resp',
          [
            '@elements' => $this->plural_class_name,
            '@resp' => substr($json, 0, 200) . (strlen($json) > 200 ? '…' : ''),
          ]
        ));
      }
    }

    // rebuild the page so the deleted items disappear
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
          $response = $api->listByKeywordAndLanguage($this->element_type, ($result->hasContent ?? ''), ($result->hasLanguage ?? ''), '_', '_', '_', 99999, 0);
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
        // unset($clonedObject->hasImageUri);
        unset($clonedObject->typeLabel);
        unset($clonedObject->hascoTypeLabel);

        $finalObject = json_encode($clonedObject, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // UPDATE BY DELETING AND CREATING
        $resp = $api->responseOptionDel($uri);
        $msg = json_decode($resp->getContents());
        if ($msg && !$msg->isSuccessful) {
          \Drupal::messenger()->addError($this->t('Failed to delete @elements, @resp.', ['@elements' => $this->plural_class_name, '@resp' => $resp]));
        }
        $resp = $api->responseOptionAdd($finalObject);
        $msg = json_decode($resp->getContents());
        if ($msg && $msg->isSuccessful) {
          \Drupal::messenger()->addMessage($this->t('Selected @elements have been submited for review successfully.', ['@elements' => $this->plural_class_name]));
        } else {
          \Drupal::messenger()->addError($this->t('Failed to submit @elements for review, @resp.', ['@elements' => $this->plural_class_name, '@resp' => $resp]));
        }

      } elseif ($this->element_type == 'codebook') {
        // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
        if ($result->wasDerivedFrom !== NULL
            && $this->checkDerivedElements($uri, $this->element_type)) {
            \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
            return false;

        // CENARIO #2: CHECK IF THERE ARE ANY OTHER R.O. WITH SAME CONTENT ALREADY IN REP
        } elseif ($result->wasDerivedFrom === NULL) {
          $response = $api->listByKeywordAndLanguage($this->element_type, $result->label, $result->hasLanguage, '_', '_', '_', 99999, 0);
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
          '"hasImageUri": "'. $result->hasImageUri .'",'.
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
                  '"hasContent": "'.($slot->responseOption->hasContent ?? '').'",'.
                  '"hasLanguage": "'.($slot->responseOption->hasLanguage ?? '').'",'.
                  '"hasVersion": "'.$slot->responseOption->hasVersion.'",'.
                  '"wasDerivedFrom": "'.($slot->responseOption->wasDerivedFrom ?? NULL).'",'.
                  '"hasSIRManagerEmail": "'.$slot->responseOption->hasSIRManagerEmail.'",'.
                  '"hasEditorEmail": "'.($slot->responseOption->hasEditorEmail ?? NULL).'",'.
                  '"typeLabel": "'.$slot->responseOption->typeLabel.'",'.
                  '"hasImageUri": "'. $slot->responseOption->hasImageUri .'",'.
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
        $api->elementDel('codebook', $result->uri);
        $api->elementAdd('codebook', $codebookJSON);

      } elseif ($this->element_type == 'component') {
        // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
        if ($result->wasDerivedFrom !== NULL
            && $this->checkDerivedElements($uri, $this->element_type)) {
            \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
            return false;

        // CENARIO #2: CHECK IF THERE ARE ANY OTHER COMPONENT WITH SAME CONTENT ALREADY IN REP, must have a new end-point for that
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

        //MAIN BODY COMPONENT
        $componentJson = '{'.
          '"uri":"'.$result->uri.'",'.
          '"typeUri":"'.$result->typeUri.'",'.
          '"hascoTypeUri":"'.VSTOI::COMPONENT.'",'.
          '"hasComponentStem":"'.$result->hasComponentStem.'",'.
          '"hasCodebook":"'.$result->hasCodebook.'",'.
          '"hasContent":"'.($result->hasContent ?? '').'",'.
          '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'",'.
          '"label":"'.$result->label.'",'.
          '"hasVersion":"'.$result->hasVersion.'",'.
          '"isAttributeOf":"'.$result->isAttributeOf.'",'.
          '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
          '"hasReviewNote":"'.$result->hasReviewNote.'",'.
          '"hasEditorEmail":"'.$result->hasEditorEmail.'",'.
          '"hasImageUri": "'. $result->hasImageUri .'",'.
          '"hasWebDocument": "'. $result->hasWebDocument .'",'.
          '"hasStatus":"'.VSTOI::UNDER_REVIEW.'"'.
        '}';

        // UPDATE BY DELETING AND CREATING
        $api->elementDel('component', $result->uri);
        $api->elementAdd('component', $componentJson);

      } elseif ($this->element_type == 'componentstem') {
        // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
        if ($result->wasDerivedFrom !== NULL
            && $this->checkDerivedElements($uri, $this->element_type)) {
            \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
            return false;

        // CENARIO #2: CHECK IF THERE ARE ANY OTHER COMPONENT WITH SAME CONTENT ALREADY IN REP, must have a new end-point for that
        }
        elseif ($result->wasDerivedFrom === NULL) {
          $response = $api->listByKeywordAndLanguage($this->element_type, ($result->hasContent ?? ''), ($result->hasLanguage ?? ''), '_', '_', '_', 99999, 0);
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

        $componentStemJson = '{"uri":"'.$result->uri.'",'.
        '"superUri":"'.$result->superUri.'",'.
        '"label":"'.$result->label.'",'.
        '"hascoTypeUri":"'.VSTOI::COMPONENT_STEM.'",'.
        '"hasStatus":"'.VSTOI::UNDER_REVIEW.'",'.
        '"hasContent":"'.($result->hasContent ?? '').'",'.
        '"hasLanguage":"'.($result->hasLanguage ?? '').'",'.
        '"hasVersion":"'.$result->hasVersion.'",'.
        '"comment":"'.$result->comment.'",'.
        '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
        '"wasGeneratedBy":"'.$result->wasGeneratedBy.'",'.
        '"hasReviewNote":"'.$result->hasReviewNote.'",'.
        '"hasImageUri": "'. $result->hasImageUri .'",'.
        '"hasWebDocument":"'.$result->hasWebDocument.'",'.
        '"hasEditorEmail":"'.$result->hasEditorEmail.'",'.
        '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'"}';

        // UPDATE BY DELETING AND CREATING
        $api = \Drupal::service('rep.api_connector');
        $api->elementDel('componentstem', $result->uri);
        $api->elementAdd('componentstem', $componentStemJson);
      // } elseif ($this->element_type == 'processstem') {
      //   // CENARIO #1: CHECK IF IT HAS wasDerivedFrom property, means it is a derived element
      //   if ($result->wasDerivedFrom !== NULL
      //       && $this->checkDerivedElements($uri, $this->element_type)) {
      //       \Drupal::messenger()->addError($this->t('There is a previous version that has the same content.'), ['@elements' => $this->plural_class_name]);
      //       return false;

      //   // CENARIO #2: CHECK IF THERE ARE ANY OTHER PROCESS WITH SAME CONTENT ALREADY IN REP, must have a new end-point for that
      //   }
      //   elseif ($result->wasDerivedFrom === NULL) {
      //     $response = $api->listByKeywordAndLanguage($this->element_type, $result->hasContent, $result->hasLanguage, 99999, 0);
      //     $json_string = (string) $response;

      //     $decoded_response = json_decode($json_string, true);

      //     if (is_array($decoded_response)) {
      //       $count = count($decoded_response['body']);
      //       if ($count > 1) {
      //         \Drupal::messenger()->addError($this->t('There is already a @element with the same content in the Repository.', ['@element' => $this->single_class_name]));
      //         return false;
      //       }
      //     }
      //   }

      //   $processStemJson = '{"uri":"'.$result->uri.'",'.
      //   '"superUri":"'.$result->superUri.'",'.
      //   '"label":"'.$result->label.'",'.
      //   '"hascoTypeUri":"'.VSTOI::PROCESS_STEM.'",'.
      //   '"hasStatus":"'.VSTOI::UNDER_REVIEW.'",'.
      //   '"hasContent":"'.$result->hasContent.'",'.
      //   '"hasLanguage":"'.$result->hasLanguage.'",'.
      //   '"hasVersion":"'.$result->hasVersion.'",'.
      //   '"comment":"'.$result->comment.'",'.
      //   '"wasDerivedFrom":"'.$result->wasDerivedFrom.'",'.
      //   '"wasGeneratedBy":"'.$result->wasGeneratedBy.'",'.
      //   '"hasReviewNote":"'.$result->hasReviewNote.'",'.
      //   '"hasImageUri": "'. $result->hasImageUri .'",'.
      //   '"hasWebDocument":"'.$result->hasWebDocument.'",'.
      //   '"hasEditorEmail":"'.$result->hasEditorEmail.'",'.
      //   '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'"}';

      //   // UPDATE BY DELETING AND CREATING
      //   $api = \Drupal::service('rep.api_connector');
      //   $api->elementDel('processstem', $result->uri);
      //   $api->elementAdd('processstem', $processStemJson);
      }

      // } elseif ($this->element_type == 'annotationstem') {
      // } elseif ($this->element_type == 'process') {
    }

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
        // dpm($resp);
        if ($resp != null) {
          $obj = json_decode($resp);
          if ($obj->isSuccessful) {
            $totalStr = $obj->body;
            $obj2 = json_decode($totalStr);
            $total = $obj2->total;
          }
        }
        // dpm($total);

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
   * Perform derive component stem action.
   */
  protected function performDeriveComponentStem(FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();
    Utils::trackingStoreUrls($uid, $previousUrl, 'sir.add_componentstem');
    $url = Url::fromRoute('sir.add_componentstem');
    $url->setRouteParameter('sourcecomponentstemuri', 'DERIVED');
    // $url->setRouteParameter('containersloturi', 'DERIVED');
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
      case 'componentstem':
        if (
            isset($oldResult->hasContent, $result->hasContent,
                  $oldResult->hasLanguage, $result->hasLanguage) &&
            $oldResult->hasContent === $result->hasContent &&
            $oldResult->hasLanguage === $result->hasLanguage
        ) {
            return true; // Found an exact equal element → returns TRUE and exit
        }
        break;
      case 'component':
        if (
          isset($oldResult->hasComponentStem, $result->hasComponentStem,
                $oldResult->hasCodebook, $result->hasCodebook,
                $oldResult->isAttributeOf, $result->isAttributeOf) &&
          $oldResult->hasComponentStem === $result->hasComponentStem &&
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
