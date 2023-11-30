<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\ListManagerEmailPage;
use Drupal\sir\Entity\AnnotationStem;
use Drupal\sir\Entity\Annotation;
use Drupal\sir\Entity\DetectorStem;
use Drupal\sir\Entity\Detector;
use Drupal\sir\Entity\Codebook;
use Drupal\sir\Entity\Instrument;
use Drupal\sir\Entity\ResponseOption;

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
  public function buildForm(array $form, FormStateInterface $form_state, $elementtype=NULL, $page=NULL, $pagesize=NULL) {

    // GET manager EMAIL
    $this->manager_email = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $this->manager_name = $user->name->value;

    // GET TOTAL NUMBER OF ELEMENTS AND TOTAL NUMBER OF PAGES
    $this->element_type = $elementtype;
    $this->setListSize(-1);
    if ($this->element_type != NULL) {
      $this->setListSize(ListManagerEmailPage::total($this->element_type, $this->manager_email));
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

    // RETRIEVE ELEMENTS
    $this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));

    $this->single_class_name = "";
    $this->plural_class_name = "";
    switch ($this->element_type) {

      // INSTRUMENT
      case "instrument":
        $this->single_class_name = "Questionnaire";
        $this->plural_class_name = "Questionnaires";
        $header = Instrument::generateHeader();
        $output = Instrument::generateOutput($this->getList());    
        break;

      // DETECTORSTEM
      case "detectorstem":
        $this->single_class_name = "Item Stem";
        $this->plural_class_name = "Item Stems";
        $header = DetectorStem::generateHeader();
        $output = DetectorStem::generateOutput($this->getList());    
        break;

      // DETECTOR
      case "detector":
        $this->single_class_name = "Item";
        $this->plural_class_name = "Items";
        $header = Detector::generateHeader();
        $output = Detector::generateOutput($this->getList());    
        break;

     // CODEBOOK
      case "codebook":
        $this->single_class_name = "Codebook";
        $this->plural_class_name = "Codebooks";
        $header = Codebook::generateHeader();
        $output = Codebook::generateOutput($this->getList());    
        break;

      // RESPONSE OPTION
      case "responseoption":
        $this->single_class_name = "Response Option";
        $this->plural_class_name = "Response Options";
        $header = ResponseOption::generateHeader();
        $output = ResponseOption::generateOutput($this->getList());    
        break;

      // ANNOTATION STEM
      case "annotationstem":
        $this->single_class_name = "Annotation Stem";
        $this->plural_class_name = "Annotation Stems";
        $header = AnnotationStem::generateHeader();
        $output = AnnotationSten::generateOutput($this->getList());    
        break;

      // ANNOTATION
      case "annotation":
        $this->single_class_name = "Annotation";
        $this->plural_class_name = "Annotations";
        $header = Annotation::generateHeader();
        $output = Annotation::generateOutput($this->getList());    
        break;

      default:
        $this->single_class_name = "Object of Unknown Type";
        $this->plural_class_name = "Objects of Unknown Types";
    }

    // PUT FORM TOGETHER
    $form['page_title'] = [
      '#type' => 'item',
      '#title' => $this->t('<h3>Manage ' . $this->plural_class_name . '</h3>'),
    ];
    $form['page_subtitle'] = [
      '#type' => 'item',
      '#title' => $this->t('<h4>' . $this->plural_class_name . ' maintained by <font color="DarkGreen">' . $this->manager_name . ' (' . $this->manager_email . ')</font></h4>'),
    ];
    $form['add_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add New ' . $this->single_class_name),
      '#name' => 'add_element',
    ];
    if ($this->element_type == 'detectorstem') {
      $form['derive_detectorstem'] = [
        '#type' => 'submit',
        '#value' => $this->t('Derive New Item Stem from Selected'),
        '#name' => 'derive_detectorstem',
      ];
    }
    $form['edit_selected_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Selected ' . $this->single_class_name),
      '#name' => 'edit_element',
    ];
    $form['delete_selected_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected ' . $this->plural_class_name),
      '#name' => 'delete_element',
    ];
    if ($this->element_type == 'instrument') {
        $form['manage_detectorslots'] = [
        '#type' => 'submit',
        '#value' => $this->t('Manage Detector Slots of Selected Questionnaire'),
        '#name' => 'manage_detectorslots',
      ];
    }
    if ($this->element_type == 'codebook') {
      $form['manage_responseoptionslots'] = [
        '#type' => 'submit',
        '#value' => $this->t('Manage Response Option Slots of Selected Codebook'),
        '#name' => 'manage_responseoptionslots',
      ];  
    }
    $form['element_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => t('No ' . $this->plural_class_name . ' found'),
    ];
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
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
    ];
    $form['space'] = [
      '#type' => 'item',
      '#value' => $this->t('<br><br><br>'),
    ];
 
    return $form;
  }

  /**
   * {@inheritdoc}
   */   
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];
  
    // RETRIEVE SELECTED ROWS, IF ANY
    $selected_rows = $form_state->getValue('element_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // ADD ELEMENT
    if ($button_name === 'add_element') {
      if ($this->element_type == 'instrument') {
        $url = Url::fromRoute('sir.add_instrument');
      } else if ($this->element_type == 'detectorstem') {
        $url = Url::fromRoute('sir.add_detectorstem');
        $url->setRouteParameter('sourcedetectorstemuri', 'EMPTY');
      } else if ($this->element_type == 'detector') {
        $url = Url::fromRoute('sir.add_detector');
        $url->setRouteParameter('sourcedetectoruri', 'EMPTY');
        $url->setRouteParameter('detectorsloturi', 'EMPTY');  
      } else if ($this->element_type == 'codebook') {
        $url = Url::fromRoute('sir.add_codebook');
      } else if ($this->element_type == 'responseoption') {
        $url = Url::fromRoute('sir.add_response_option');
        $url->setRouteParameter('responseoptionsloturi', 'EMPTY');
      }
      $form_state->setRedirectUrl($url);
    }  

    // EDIT ELEMENT
    if ($button_name === 'edit_element') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact " . $this->single_class_name . " to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("No more than one " . $this->single_class_name . " can be edited at once."));      
      } else {
        $first = array_shift($rows);
        if ($this->element_type == 'instrument') {
          $url = Url::fromRoute('sir.edit_instrument', ['instrumenturi' => base64_encode($first)]);
        } else if ($this->element_type == 'detectorstem') {
          $url = Url::fromRoute('sir.edit_detectorstem', ['detectorstemuri' => base64_encode($first)]);
        } else if ($this->element_type == 'detector') {
          $url = Url::fromRoute('sir.edit_detector', ['detectoruri' => base64_encode($first)]);
        } else if ($this->element_type == 'codebook') {
          $url = Url::fromRoute('sir.edit_codebook', ['codebookuri' => base64_encode($first)]);
        } else if ($this->element_type == 'responseoption') {
          $url = Url::fromRoute('sir.edit_response_option', ['responseoptionuri' => base64_encode($first)]);
        }
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE ELEMENT
    if ($button_name === 'delete_element') {
      if (sizeof($rows) <= 0) {
        \Drupal::messenger()->addMessage(t("At least one " . $this->single_class_name . " needs to be selected to be deleted."));      
      } else {
        $api = \Drupal::service('rep.api_connector');
        foreach($rows as $uri) {
          if ($this->element_type == 'instrument') {
            $api->instrumentDel($uri);
          } else if ($this->element_type == 'detectorstem') {
            $api->detectorStemDel($uri);
          } else if ($this->element_type == 'detector') {
            $api->detectorDel($uri);
         } else if ($this->element_type == 'codebook') {
            $api->codebookDel($uri);
          } else if ($this->element_type == 'responseoption') {
            $api->responseOptionDel($uri);
          }
        }
        \Drupal::messenger()->addMessage(t("Selected " . $this->plural_class_name . " has/have been deleted successfully."));      
      }
    }  

    // DERIVE DETECTOR
    if ($button_name === 'derive_detectorstem') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact item stem to be derived."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one item stem to be derived. No more than one item stem can be derived at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.add_detectorstem');
        $url->setRouteParameter('sourcedetectorstemuri', base64_encode($first));
        $url->setRouteParameter('detectorsloturi', 'EMPTY');
        $form_state->setRedirectUrl($url);
      }
    }  
    
    // MANAGE RESPONSE OPTION SLOTS
    if ($button_name === 'manage_responseoptionslots') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact codebook which response option slots are going to be managed."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("The response option slots of no more than one codebook can be managed at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.manage_responseoption_slots', ['codebookuri' => base64_encode($first)]);
        $form_state->setRedirectUrl($url);
      } 
      return;
    }
    
    // MANAGE DETECTOR_SLOTS
    if ($button_name === 'manage_detectorslots') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact questionnaire which detectorslots are going to be managed."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one questionnaire. Items of no more than one questionnaire can be managed at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.manage_detectorslots', ['instrumenturi' => base64_encode($first)]);
        $form_state->setRedirectUrl($url);
      } 
    }
    
    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $url = Url::fromRoute('sir.search');
      $form_state->setRedirectUrl($url);
    }  

  }
  

}