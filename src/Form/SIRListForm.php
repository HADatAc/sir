<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rep\ListKeywordLanguagePage;
use Drupal\sir\Entity\AnnotationStem;
use Drupal\sir\Entity\Annotation;
use Drupal\sir\Entity\DetectorStem;
use Drupal\sir\Entity\Detector;
use Drupal\sir\Entity\Codebook;
use Drupal\sir\Entity\Instrument;
use Drupal\sir\Entity\ResponseOption;

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
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype=NULL, $keyword=NULL, $language=NULL, $page=NULL, $pagesize=NULL) {

    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->setListSize(-1);
    if ($elementtype != NULL) {
      $this->setListSize(ListKeywordLanguagePage::total($elementtype, $keyword, $language));
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
      $next_page_link = ListKeywordLanguagePage::link($elementtype, $keyword, $language, $next_page, $pagesize);
    } else {
      $next_page_link = '';
    }
    if ($page > 1) {
      $previous_page = $page - 1;
      $previous_page_link = ListKeywordLanguagePage::link($elementtype, $keyword, $language, $previous_page, $pagesize);
    } else {
      $previous_page_link = '';
    }

    // RETRIEVE ELEMENTS
    $this->setList(ListKeywordLanguagePage::exec($elementtype, $keyword, $language, $page, $pagesize));

    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');
    $preferred_detector = \Drupal::config('rep.settings')->get('preferred_detector');

    $class_name = "";
    switch ($elementtype) {

      // INSTRUMENT
      case "instrument":
        $class_name = $preferred_instrument . "s";
        $header = Instrument::generateHeader();
        $output = Instrument::generateOutput($this->getList());
        break;

      // DETECTOR STEM
      case "detectorstem":
        $class_name = $preferred_detector . " Stems";
        $header = DetectorStem::generateHeader();
        $output = DetectorStem::generateOutput($this->getList());
        break;

      // DETECTOR
      case "detector":
        $class_name = $preferred_detector . "s";
        $header = Detector::generateHeader();
        $output = Detector::generateOutput($this->getList());
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
    $form['element_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $output,
      '#empty' => t('No response options found'),
    ];

    $form['pager'] = [
      '#theme' => 'list-page',
      '#items' => [
        'page' => strval($page),
        'first' => ListKeywordLanguagePage::link($elementtype, $keyword, $language, 1, $pagesize),
        'last' => ListKeywordLanguagePage::link($elementtype, $keyword, $language, $total_pages, $pagesize),
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
