<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\HASCO;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\Core\Render\Markup;

class SIRSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sir_search_form';
  }

  protected $elementtype;
  protected $keyword;
  protected $language;
  protected $page;
  protected $pagesize;

  public function getElementType() {
    return $this->elementtype;
  }

  public function setElementType($type) {
    return $this->elementtype = $type;
  }

  public function getKeyword() {
    return $this->keyword;
  }

  public function setKeyword($kw) {
    return $this->keyword = $kw;
  }

  public function getLanguage() {
    return $this->language;
  }

  public function setLanguage($lang) {
    return $this->language = $lang;
  }

  public function getPage() {
    return $this->page;
  }

  public function setPage($pg) {
    return $this->page = $pg;
  }

  public function getPageSize() {
    return $this->pagesize;
  }

  public function setPageSize($pgsize) {
    return $this->pagesize = $pgsize;
  }

  public function iconSubmitForm(array &$form, FormStateInterface $form_state) {
    $clicked_button = $form_state->getTriggeringElement()['#name'] ?? null;
    if ($clicked_button) {
      $form_state->setValue('search_element_type', $clicked_button);
    }
    $form_state->setValue('search_keyword', '');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'rep/webdoc_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';
    $base_url = (\Drupal::request()->headers->get('x-forwarded-proto') === 'https' ? 'https://':'http://'). \Drupal::request()->getHost() . \Drupal::request()->getBaseUrl();
    $form['#attached']['drupalSettings']['webdoc_modal'] = [
      'baseUrl' => $base_url,
    ];
    $form['#attached']['library'][] = 'rep/pdfjs';

    $form['#attached']['library'][] = 'sir/sir_icons';

    $tables = new Tables;
    $tablesLanguages = $tables->getLanguages();
    $languages = [];
    $languages['ANY'] = '-- ANY LANGUAGE --';
    if ($tablesLanguages != NULL) {
      foreach ($tablesLanguages as $key => $lang) {
        $languages[$key] = $lang;
      }
    }

    $request = \Drupal::request();
    $pathInfo = $request->getPathInfo();
    $pathElements = (explode('/', $pathInfo));

    $this->setElementType('instrument');
    $this->setKeyword('');
    $this->setLanguage('');
    $this->setPage(1);
    $this->setPageSize(12);

    // IT IS A CLASS ELEMENT if size of path elements is equal 5
    if (sizeof($pathElements) == 5) {
      // ELEMENT TYPE
      $this->setElementType($pathElements[4]);

    // IT IS AN INSTANCE ELEMENT if size of path elements is equal 8
    } else if (sizeof($pathElements) >= 8) {
      // ELEMENT TYPE
      $this->setElementType($pathElements[3]);

      // KEYWORD
      if ($pathElements[4] == '_') {
        $this->setKeyword('');
      } else {
        $this->setKeyword($pathElements[4]);
      }

      // LANGUAGE
      if ($pathElements[5] == '_') {
        $this->setLanguage('');
      } else {
        $this->setLanguage($pathElements[5]);
      }

      // PAGE
      $this->setPage((int) $pathElements[6]);

      // PAGESIZE
      $this->setPageSize((int) $pathElements[7]);
    }

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');

    $form['search_element_type'] = [
      '#type' => 'hidden',
      '#value' => $this->getElementType(),
    ];

    $form['element_icons'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['element-icons-grid-wrapper']],
    ];

    $form['element_icons']['grid'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['element-icons-grid']],
    ];

    $element_types = [
      'instrument'      => ['label' => $this->t($preferred_instrument . 's'),          'image' => 'white/instrument_placeholder.png'],
      'componentstem'   => ['label' => $this->t('Component Stems'),                    'image' => 'white/component_stem_placeholder.png'],
      'component'       => ['label' => $this->t('Components'),                         'image' => 'white/component_placeholder.png'],
      'codebook'        => ['label' => $this->t('Codebooks'),                          'image' => 'white/codebook_placeholder.png'],
      'responseoption'  => ['label' => $this->t('Response Options'),                   'image' => 'white/responseoption_placeholder.png'],
      'annotationstem'  => ['label' => $this->t('Annotation Stems'),                   'image' => 'white/annotation_stem_placeholder.png'],
      'annotation'      => ['label' => $this->t('Annotations'),                        'image' => 'white/annotation_placeholder.png'],
    ];

    $module_path = \Drupal::request()->getBaseUrl() . '/' . \Drupal::service('extension.list.module')->getPath('rep');

    foreach ($element_types as $type => $info) {
      $placeholder_image = $module_path . '/images/placeholders/' . $info['image'];

      $button_classes = ['element-icon-button'];
      if ($type === $this->getElementType()) {
        $button_classes[] = 'selected';
      }

      $form['element_icons']['grid'][$type] = [
        '#type' => 'submit',
        '#value' => '',
        '#attributes' => [
          'class' => $button_classes,
          'style' => "background-image: url('{$placeholder_image}');",
          'title' => $info['label'],
          'aria-label' => $info['label'],
        ],
        '#name' => $type,
        '#submit' => ['::iconSubmitForm'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::ajaxSubmitForm',
          'progress' => ['type' => 'none'],
        ],
      ];
    }

    $element = $this->getElementType();
    if (($element !== null && $element !== 'instrument')) {

      $form['search_language'] = [
        '#type' => 'select',
        '#title' => $this->t('Language'),
        '#options' => $languages,
        '#default_value' => $this->getLanguage(),
        '#ajax' => [
          'callback' => '::ajaxSubmitForm',
        ],
        '#attributes' => [
          'class' => ['mt-1'],
        ],
      ];

      $form['search_keyword'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Keyword'),
        '#default_value' => $this->getKeyword(),
        '#attributes' => [
          'class' => ['mt-1'],
        ],
      ];

      $form['search_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Search'),
        '#attributes' => [
          'class' => ['btn', 'btn-primary', 'search-button'],
        ],
      ];

      $form['bottom_space'] = [
        '#type' => 'item',
        '#title' => t('<br><br>'),
      ];
    }

    $form['node_comment_display'] = [
      '#type' => 'container',
      '#text' => '',
      '#attributes' => [
        'id' => 'node-comment-display',
        'class' => ['mt-2', 'w-100'],
        'style' => 'display:none;',
      ],
    ];

    $form['modal'] = [
      '#type' => 'markup',
      '#markup' => Markup::create('
        <div id="modal-container" class="modal-media hidden">
          <div class="modal-content">
            <button class="close-btn" type="button">&times;</button>
            <div id="pdf-scroll-container"></div>
            <div id="modal-content"></div>
          </div>
          <div class="modal-backdrop"></div>
        </div>
      '),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $elt = $form_state->getValue('search_element_type');
    if (!is_string($elt) || strlen($elt) < 1) {
      $form_state->setErrorByName('search_element_type', $this->t('Please select an element type'));
    }
  }

  /**
   * {@inheritdoc}
   */
  private function redirectUrl(FormStateInterface $form_state) {

    $this->setKeyword($form_state->getValue('search_keyword'));
    if ($this->getKeyword() == NULL || $this->getKeyword() == '') {
      $this->setKeyword("_");
    }
    $this->setLanguage($form_state->getValue('search_language'));
    if ($this->getLanguage() == NULL || $this->getLanguage() == '' || $this->getLanguage() == 'ANY') {
      $this->setLanguage("_");
    }

    // IF ELEMENT TYPE IS CLASS
    dpm($form_state->getValue('search_element_type'));
    if (
      $form_state->getValue('search_element_type') == 'instrument' ||
      $form_state->getValue('search_element_type') == 'componentstem' ||
      $form_state->getValue('search_element_type') == 'annotationstem'
    ) {
      $url = Url::fromRoute('rep.browse_tree');
      $url->setRouteParameter('mode', 'browse');
      $url->setRouteParameter('elementtype', $form_state->getValue('search_element_type'));
      return $url;
    }

    // IF ELEMENT TYPE IS INSTANCE
    $url = Url::fromRoute('sir.list_element');
    $url->setRouteParameter('elementtype', $form_state->getValue('search_element_type'));
    $url->setRouteParameter('keyword', $this->getKeyword());
    $url->setRouteParameter('language', $this->getLanguage());
    $url->setRouteParameter('page', $this->getPage());
    $url->setRouteParameter('pagesize', $this->getPageSize());
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $this->setPage(1);
    $this->setPageSize(12);
    $elementType = $form_state->getValue('search_element_type');

    // Build URL for the route 'sir.search' with parameters.
    if ($elementType === 'instrument' ||
        $elementType === 'componentstem' ||
        $elementType === 'annotationstem') {
      $url = Url::fromRoute('sir.search', [
        'mode' => 'browse',
        'elementtype' => $elementType,
      ]);
    } else {
      $url = $this->redirectUrl($form_state);
    }

    $response->addCommand(new RedirectCommand($url->toString()));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = $this->redirectUrl($form_state);
    $form_state->setRedirectUrl($url);
  }
}
