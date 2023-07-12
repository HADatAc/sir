<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\sir\Entity\Tables;
use Drupal\sir\Vocabulary\HASCO;
use Drupal\sir\Vocabulary\VSTOI;

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
    $languages = [];
    $languages['ANY'] = '-- ANY LANGUAGE --';
    foreach ($tables->getLanguages() as $key => $lang) {
      $languages[$key] = $lang;
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
    if (sizeof($pathElements) >= 8) {

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

    $form['search_element_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Element Type'),
      '#required' => TRUE,
      '#options' => [
        'instrument' => $this->t('Questionnaires'),
        'detector' => $this->t('Items'),
        'experience' => $this->t('Experience'),
        'responseoption' => $this->t('Response Option'),
//        'semanticvariable' => $this->t('Semantic Variable'),
      ],
      '#default_value' => $this->getElementType(),
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
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
    ];
    $form['search_keyword'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Keyword'),
      '#default_value' => $this->getKeyword(),
    ];
    $form['search_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
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
    $url = $this->redirectUrl($form_state);
    $response->addCommand(new RedirectCommand($url->toString()));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->setKeyword($form_state->getValue('search_keyword'));
    if ($this->getKeyword() == NULL || $this->getKeyword() == '') {
      $this->setKeyword("_");
    }
    $this->setLanguage($form_state->getValue('search_language'));
    if ($this->getLanguage() == NULL || $this->getLanguage() == '' || $this->getLanguage() == 'ANY') {
      $this->setLanguage("_");
    }
    
    $url = Url::fromRoute('sir.list_element');
    $url->setRouteParameter('elementtype', $form_state->getValue('search_element_type'));
    $url->setRouteParameter('keyword', $this->getKeyword());
    $url->setRouteParameter('language', $this->getLanguage());
    $url->setRouteParameter('page', $this->getPage());
    $url->setRouteParameter('pagesize', $this->getPageSize());
    $form_state->setRedirectUrl($url);
  }

}