<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rep\ListKeywordLanguagePage;
use Drupal\sir\Entity\AnnotationStem;
use Drupal\sir\Entity\Annotation;
use Drupal\sir\Entity\ComponentStem;
use Drupal\sir\Entity\Component;
use Drupal\sir\Entity\Codebook;
use Drupal\sir\Entity\Instrument;
use Drupal\sir\Entity\ResponseOption;
use Drupal\rep\Entity\Tables;

class SIRListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'list_form';
  }

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
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype=NULL, $keyword=NULL, $language=NULL, $type=NULL, $manageremail=NULL, $status=NULL, $page=NULL, $pagesize=NULL) {

    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->setListSize(-1);
    if ($elementtype != NULL) {
      $this->setListSize(ListKeywordLanguagePage::total($elementtype, $keyword, $language, $type, $manageremail, $status));
    }
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
      $next_page_link = ListKeywordLanguagePage::link($elementtype, $keyword, $language, $type, $manageremail, $status, $next_page, $pagesize);
    } else {
      $next_page_link = '';
    }
    if ($page > 1) {
      $previous_page = $page - 1;
      $previous_page_link = ListKeywordLanguagePage::link($elementtype, $keyword, $language, $type, $manageremail, $status, $previous_page, $pagesize);
    } else {
      $previous_page_link = '';
    }

    // Gets Filter Values
    $status_filter = $form_state->getValue('status_filter') ?? 'all';
    $language_filter = $form_state->getValue('language_filter') ?? 'all';
    $text_filter = ($form_state->getValue('text_filter') != '' && $form_state->getValue('text_filter') != '_') ? $form_state->getValue('text_filter') : $keyword;
    // Convert the text filter to lowercase for case-insensitive comparison
    $text_filter = strtolower($text_filter);

    // RETRIEVE ELEMENTS
    $this->setList(ListKeywordLanguagePage::exec($elementtype, $keyword, $language, $type, $manageremail, $status, $page, $pagesize));

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');
    $preferred_component = \Drupal::config('rep.settings')->get('preferred_component') ?? 'Component';

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

    if ($elementtype !== 'component' && $elementtype !== 'componentstem' && $elementtype !== 'responseoption' && $elementtype !== 'annotation' && $elementtype !== 'annotationstem' && $elementtype !== 'codebook') {
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
            'style' => 'max-width:230px;margin-bottom:0!important;float:right;',
            'placeholder' => 'Type in your search criteria',
            // Ao pressionar Enter, previne o submit e dispara o evento "change"
            'onkeydown' => 'if (event.keyCode == 13) { event.preventDefault(); this.blur(); }',
        ],
      ];
    }

    if ($elementtype !== 'component' && $elementtype !== 'componentstem' && $elementtype !== 'responseoption' && $elementtype !== 'annotation' && $elementtype !== 'annotationstem' && $elementtype !== 'codebook') {
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

    if ($elementtype !== 'component' && $elementtype !== 'componentstem' && $elementtype !== 'responseoption' && $elementtype !== 'annotation' && $elementtype !== 'annotationstem' && $elementtype !== 'codebook') {
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
    }

    $class_name = "";
    switch ($elementtype) {

      // INSTRUMENT
      case "instrument":
        $class_name = $preferred_instrument . "s";
        $header = Instrument::generateHeader();
        $output = Instrument::generateOutput($this->getList());
        break;

      // COMPONENT STEM
      case "componentstem":
        $class_name = $preferred_component . " Stems";
        $header = ComponentStem::generateHeader();
        $output = ComponentStem::generateOutput($this->getList());
        break;

      // COMPONENT
      case "component":
        $class_name = $preferred_component . "s";
        $header = Component::generateHeader();
        $output = Component::generateOutput($this->getList());
        break;

      // CODEBOOK
      case "codebook":
        $class_name = "Codebooks";
        $header = Codebook::generateHeader();
        $output = Codebook::generateOutput($this->getList());
        break;

      // RESPONSE OPTION
      case "responseoption":
        $class_name = "Response Options";
        $header = ResponseOption::generateHeader();
        $output = ResponseOption::generateOutput($this->getList());
        break;

      // ANNOTATION STEM
      case "annotationstem":
        $class_name = "Annotaiton Stems";
        $header = AnnotationStem::generateHeader();
        $output = AnnotationStem::generateOutput($this->getList());
        break;

      // ANNOTATION
      case "annotation":
        $class_name = "Annotations";
        $header = Annotation::generateHeader();
        $output = Annotation::generateOutput($this->getList());
        break;

      default:
        $class_name = "Objects of Unknown Types";
    }

    // PUT FORM TOGETHER

    // OLD WAY
    // $form['element_table'] = [
    //   '#type' => 'table',
    //   '#header' => $header,
    //   '#rows' => $output,
    //   '#empty' => t('No response options found'),
    // ];

    $output = $output['output'];

    $form['header'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['header-container'],
        'style' => 'display: flex; justify-content: space-between; align-items: center;',
      ],
    ];
    $form['header']['title'] = [
      '#type' => 'item',
      '#markup' => t('<h3>Available <font style="color:DarkGreen;">' . $class_name . '</font></h3>'),
    ];

    $form['element_table_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'element-table-wrapper'],
    ];

    // $form['element_table_wrapper']['element_title'] = [
    //     '#type' => 'markup',
    //     '#markup' => $this->t('<h3 class="mt-0 mb-4">@class_name</h3>', [
    //       '@class_name' => $class_name,
    //     ]),
    // ];

    $form['element_table_wrapper']['element_table'] = [
        '#type' => 'table',
        '#header' => array_merge( $header),
        '#empty' => $this->t('No ' . $class_name . ' found'),
        '#attributes' => ['class' => ['table', 'table-striped']],
        '#js_select' => FALSE,
    ];

    foreach ($output as $key => $row) {
        $row_status = strtolower($row['element_hasStatus']);
        $row_language = strtolower($row['element_hasLanguage']);

        if ($elementtype == 'instrument' || $elementtype == 'codebook')
          $row_label = strtolower($row['element_name']);
        else if ($elementtype == 'component' || $elementtype == 'componentstem' || $elementtype == 'responseoption')
          $row_label = strtolower($row['element_content']);

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
        // $checkbox = [
        //     '#type' => 'checkbox',
        //     '#title' => $this->t('Select'),
        //     '#title_display' => 'invisible',
        //     '#return_value' => $key,
        //     '#attributes' => [
        //         'class' => ['element-select-checkbox', 'checkbox-status-' . $row_status],
        //     ],
        // ];

        // Create the table row
        // $form['element_table_wrapper']['element_table'][$key]['select'] = $checkbox;

        // Hide unnecessary columns
        foreach ($row as $field_key => $field_value) {
            if ($field_key !== 'element_hasStatus' && $field_key !== 'element_hasLanguage' && $field_key !== 'element_hasImageUri') {
                $form['element_table_wrapper']['element_table'][$key][$field_key] = [
                    '#markup' => $field_value,
                ];
            }
        }
    }

    $form['element_table_wrapper']['pager'] = [
      '#theme' => 'list-page',
      '#items' => [
        'page' => strval($page),
        'first' => ListKeywordLanguagePage::link($elementtype, $keyword, $language, $type, $manageremail, $status, 1, $pagesize),
        'last' => ListKeywordLanguagePage::link($elementtype, $keyword, $language,  $type, $manageremail, $status, $total_pages, $pagesize),
        'previous' => $previous_page_link,
        'next' => $next_page_link,
        'last_page' => strval($total_pages),
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
