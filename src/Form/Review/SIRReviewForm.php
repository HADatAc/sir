<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\ListManagerEmailPage;
use Drupal\rep\Utils;
use Drupal\sir\Entity\AnnotationStem;
use Drupal\sir\Entity\DetectorStem;
use Drupal\sir\Entity\Detector;
use Drupal\sir\Entity\ProcessStem;
use Drupal\sir\Entity\Codebook;
use Drupal\sir\Entity\Instrument;
use Drupal\sir\Entity\Process;
use Drupal\sir\Entity\ResponseOption;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Render\Markup;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\sir\Entity\Actuator;
use Drupal\sir\Entity\ActuatorStem;

class SIRReviewForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_list_form';
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

    // IN CASE NOT CORRECT ROLE REDIRECT disabled during develop
    // $current_user = \Drupal::currentUser();
    // if (!$current_user->hasRole('reviewer')) {
    //   \Drupal::messenger()->addError($this->t('You do not have permission to access this page.'));

    //   // Corrigindo a criação da URL.
    //   $url = Url::fromRoute('rep.home')->toString();

    //   // Redirecionamento usando o URL gerado.
    //   $response = new RedirectResponse($url);
    //   $response->send();
    //   exit;
    // }

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
      '#markup' => '<h3 class="mt-5">Manage ' . $this->plural_class_name . ' REVIEWS</h3>',
    ];
    $form['page_subtitle'] = [
      '#type' => 'item',
      '#markup' => $this->t('<h4>@plural_class_name submissions, are being reviewed by <font color="DarkGreen">@manager_name (@manager_email)</font></h4>', [
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

    // Common buttons (only in table view)
    $form['edit_selected_element'] = [
      '#type' => 'submit',
      '#value' => $this->t('Review Selected'),
      '#name' => 'edit_element',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'edit-element-button'],
      ],
    ];

    // Render Table
    $this->buildTableView($form, $form_state, $page, $pagesize);

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
    $preferred_actuator = \Drupal::config('rep.settings')->get('preferred_actuator');

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
        $this->single_class_name = "Process Stem";
        $this->plural_class_name = "Process Stems";
        break;

      // PROCESS
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
    //$this->setList(ListManagerEmailPage::exec($this->element_type, $this->manager_email, $page, $pagesize));
    $this->setList(ListManagerEmailPage::execReview($this->element_type, VSTOI::UNDER_REVIEW, $page, $pagesize));

    // Generate header and output
    $header = $this->generateHeader();
    $output = $this->generateOutput();

    $form['element_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#js_select' => FALSE,
      '#empty' => $this->t('No ' . $this->plural_class_name . ' submissions for review were found'),
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
   * Generate header based on element type.
   */
  protected function generateHeader() {
    switch ($this->element_type) {
      case "instrument":
        return Instrument::generateReviewHeader();
      case "actuatorstem":
        return ActuatorStem::generateHeader();
      case "actuator":
        return Actuator::generateHeader();
      case "detectorstem":
        return DetectorStem::generateReviewHeader();
      case "detector":
        return Detector::generateReviewHeader();
      case "codebook":
        return Codebook::generateReviewHeader();
      case "responseoption":
        return ResponseOption::generateReviewHeader();
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
        return Instrument::generateReviewOutput($this->getList());
      case "actuatorstem":
        return ActuatorStem::generateReviewOutput($this->getList());
      case "actuator":
        return Actuator::generateReviewOutput($this->getList());
      case "detectorstem":
        return DetectorStem::generateReviewOutput($this->getList());
      case "detector":
        return Detector::generateReviewOutput($this->getList());
      case "codebook":
        return Codebook::generateReviewOutput($this->getList());
      case "responseoption":
        return ResponseOption::generateReviewOutput($this->getList());
      case "annotationstem":
        return AnnotationStem::generateOutput($this->getList());
      case "processstem":
        return ProcessStem::generateReviewOutput($this->getList());
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
        'processstem' => 'sir.edit_processstem',
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
        $this->performReview($uri, $form_state);
      } else {
        \Drupal::messenger()->addError($this->t('Cannot review: URI is missing.'));
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
    } elseif (strpos($button_name, 'derive_processstem_') === 0) {
      $uri = $triggering_element['#element_uri'];
      $this->performDeriveProcessStem($uri, $form_state);
    } elseif ($button_name === 'add_element') {
      $this->performAdd($form_state);
    } elseif ($button_name === 'edit_element') {
      $selected_rows = array_filter($form_state->getValue('element_table'));
      if (count($selected_rows) == 1) {
        $selected_uris = array_keys($selected_rows);
        $uri = $selected_uris[0];
        $this->performReview($uri, $form_state);
      } else {
        \Drupal::messenger()->addWarning($this->t('Please select exactly ONE item to review.'));
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
    } elseif ($button_name === 'derive_processstem') {
      $selected_rows = array_filter($form_state->getValue('element_table'));
      if (count($selected_rows) == 1) {
        $selected_uris = array_keys($selected_rows);
        $uri = $selected_uris[0];
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
    }
    $form_state->setRedirectUrl($url);
  }

  /**
   * Perform the review action.
   */
  protected function performReview($uri, FormStateInterface $form_state) {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = \Drupal::request()->getRequestUri();

    if ($this->element_type == 'instrument') {
      $url = Url::fromRoute('sir.review_instrument', ['instrumenturi' => base64_encode($uri)]);
    } elseif ($this->element_type == 'actuatorstem') {
      $url = Url::fromRoute('sir.review_actuatorstem', ['actuatorstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'actuator') {
      $url = Url::fromRoute('sir.review_actuator', ['actuatoruri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'detectorstem') {
      $url = Url::fromRoute('sir.review_detectorstem', ['detectorstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'detector') {
      $url = Url::fromRoute('sir.review_detector', ['detectoruri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'codebook') {
      $url = Url::fromRoute('sir.review_codebook', ['codebookuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'responseoption') {
      $url = Url::fromRoute('sir.review_response_option', ['responseoptionuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'annotationstem') {
      $url = Url::fromRoute('sir.review_annotationstem', ['annotationstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'processstem') {
      $url = Url::fromRoute('sir.review_processstem', ['processstemuri' => base64_encode($uri)]);
    } elseif ($this->element_type == 'process') {
      $url = Url::fromRoute('sir.review_process', ['processuri' => base64_encode($uri)]);
    } else {
      \Drupal::messenger()->addError($this->t('No review route found for this element type.'));
      return;
    }

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

}
