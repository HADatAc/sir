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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // LOAD LANGUAGE TABLE
    $tables = new Tables;
    $tablesLanguages = $tables->getLanguages();
    $languages = [];
    $languages['ANY'] = '-- ANY LANGUAGE --';
    if ($tablesLanguages != NULL) {
      foreach ($tablesLanguages as $key => $lang) {
        $languages[$key] = $lang;
      }
    }

    // RETRIEVE PARAMETERS FROM HTML REQUEST
    $request = \Drupal::request();
    $pathInfo = $request->getPathInfo();
    $pathElements = (explode('/',$pathInfo));
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
      $this->setPage((int)$pathElements[6]);

      // PAGESIZE
      $this->setPageSize((int)$pathElements[7]);
    }

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');
    $preferred_detector = \Drupal::config('rep.settings')->get('preferred_detector');

    $form['search_element_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Element Type'),
      '#required' => TRUE,
      '#options' => [
        'instrument' => $this->t($preferred_instrument . 's'),
        'detectorstem' => $this->t($preferred_detector . ' Stems'),
        'detector' => $this->t($preferred_detector . 's'),
        'codebook' => $this->t('Codebooks'),
        'responseoption' => $this->t('Response Options'),
        'annotationstem' => $this->t('Annotation Stems'),
        'annotation' => $this->t('Annotations'),
      ],
      '#default_value' => $this->getElementType(),
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
      ],
      '#attributes' => [
        'class' => ['mt-1'],
      ],
    ];
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

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if(strlen($form_state->getValue('search_element_type')) < 1) {
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
    if (($form_state->getValue('search_element_type') == 'instrument') ||
        ($form_state->getValue('search_element_type') == 'detectorstem') ||
        ($form_state->getValue('search_element_type') == 'detector')) {
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
    $url = $this->redirectUrl($form_state);
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
