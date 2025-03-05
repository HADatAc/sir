<?php

namespace Drupal\sir\Form\Review;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Constant;
use Drupal\rep\Utils;
use Drupal\rep\Entity\Tables;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\REPGUI;

class ReviewInstrumentForm extends FormBase {

  protected $instrumentUri;

  protected $instrument;

  protected $container;

  public function getInstrumentUri() {
    return $this->instrumentUri;
  }

  public function setInstrumentUri($uri) {
    return $this->instrumentUri = $uri;
  }

  public function getInstrument() {
    return $this->instrument;
  }

  public function setInstrument($instrument) {
    return $this->instrument = $instrument;
  }

  public function getContainer() {
    return $this->container;
  }

  public function setContainer($container) {
    return $this->container = $container;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_instrument_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {

    // MODAL
    $form['#attached']['library'][] = 'rep/rep_modal';
    $form['#attached']['library'][] = 'core/drupal.dialog';

    $uri_decode=base64_decode($instrumenturi);
    $this->setInstrumentUri($uri_decode);

    $tables = new Tables;
    $languages = $tables->getLanguages();
    $informants = $tables->getInformants();

    $api = \Drupal::service('rep.api_connector');
    $rawresponse = $api->getUri($this->getInstrumentUri());
    $obj = json_decode($rawresponse);

    if ($obj->isSuccessful) {
      $this->setInstrument($obj->body);
      //dpm($this->getInstrument());
    } else {
      \Drupal::messenger()->addError(t("Failed to retrieve Instrument."));
      self::backUrl();
      return;
    }

    $hasInformant = Constant::DEFAULT_INFORMANT;
    if ($this->getInstrument()->hasInformant != NULL && $this->getInstrument()->hasInformant != '') {
      $hasInformant = $this->getInstrument()->hasInformant;
    }

    $hasLanguage = Constant::DEFAULT_LANGUAGE;
    if ($this->getInstrument()->hasLanguage != NULL && $this->getInstrument()->hasLanguage != '') {
      $hasLanguage = $this->getInstrument()->hasLanguage;
    }

    $form['information'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-publication',
    ];

    // INSTRUMENT RELATED

    $form['instrument_information'] = [
      '#type' => 'details',
      '#title' => $this->t('Simulator Form'),
      '#group' => 'information',
    ];

    // Campo de texto desativado que ocupa todo o espaço disponível
    $form['instrument_information']['instrument_parent_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'align-items-center', 'gap-2', 'mt-2'], // Flexbox para alinhar na mesma linha
        'style' => 'width: 100%;margin-bottom:0!important;',
      ],
    ];

    $form['instrument_information']['instrument_parent_wrapper']['instrument_type'] = [
      'top' => [
        '#type' => 'markup',
        '#markup' => '<div class="pt-0 col border border-white">',
      ],
      'main' => [
        '#type' => 'textfield',
        '#title' => $this->t('Parent Type'),
        '#name' => 'instrument_type',
        '#default_value' => Utils::fieldToAutocomplete($this->getInstrument()->superUri, $this->getInstrument()->superClassLabel),
        '#id' => 'instrument_type',
        '#parents' => ['instrument_type'],
        '#attributes' => [
          'class' => ['open-tree-modal', 'flex-grow-1'],
          'style' => "min-width: 0;",
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode(['width' => 800]),
          'data-url' => Url::fromRoute('rep.tree_form', [
            'mode' => 'modal',
            'elementtype' => 'instrument',
          ], ['query' => ['field_id' => 'instrument_type']])->toString(),
          'data-field-id' => 'instrument_type',
          'data-elementtype' => 'instrument',
          'data-search-value' => $this->getInstrument()->superUri ?? '',
        ],
        '#disabled' => TRUE,
      ],
      'bottom' => [
        '#type' => 'markup',
        '#markup' => '</div>',
      ],
    ];

    // Construção da URL
    $elementUri = Utils::namespaceUri($this->getInstrument()->superUri);
    $elementUriEncoded = base64_encode($elementUri);
    $url = Url::fromRoute('rep.describe_element', ['elementuri' => $elementUriEncoded], ['absolute' => TRUE])->toString();

    // Botão para abrir nova janela
    $form['instrument_information']['instrument_parent_wrapper']['instrument_parent_wrapper_button'] = [
      '#type' => 'markup',
      '#markup' => '<a href="' . $url . '" target="_blank" class="btn btn-success text-nowrap" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Check Element') . '</a>',
    ];

    $form['instrument_information']['instrument_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $this->getInstrument()->label,
      '#disabled' => TRUE,
    ];
    $form['instrument_information']['instrument_abbreviation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Abbreviation'),
      '#default_value' => $this->getInstrument()->hasShortName,
      '#disabled' => TRUE,
    ];
    $form['instrument_information']['instrument_informant'] = [
      '#type' => 'select',
      '#title' => $this->t('Informant'),
      '#options' => $informants,
      '#default_value' => $hasInformant,
      '#disabled' => TRUE,
    ];
    $form['instrument_information']['instrument_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#options' => $languages,
      '#default_value' => $hasLanguage,
      '#disabled' => TRUE,
    ];
    $form['instrument_information']['instrument_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' =>
        ($this->getInstrument()->hasStatus === VSTOI::CURRENT || $this->getInstrument()->hasStatus === VSTOI::DEPRECATED) ?
        $this->getInstrument()->hasVersion + 1 : $this->getInstrument()->hasVersion,
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];
    $form['instrument_information']['instrument_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getInstrument()->comment,
      '#disabled' => TRUE,
    ];

    $form['instrument_information']['instrument_webdocument_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['d-flex', 'align-items-center', 'gap-2', 'mt-2'],
        'style' => 'width: 100%;margin-bottom:0!important;',
      ],
    ];

    $form['instrument_information']['instrument_webdocument_wrapper']['instrument_webdocument'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Web Document'),
      '#default_value' => $this->getInstrument()->hasWebDocument,
      '#disabled' => TRUE,
      '#attributes' => [
        'class' => ['flex-grow-1'],
        'style' => "min-width: 0;",
        'placeholder' => 'http://',
      ]
    ];

    if (strlen($this->getInstrument()->hasWebDocument) > 0)
      $form['instrument_information']['instrument_webdocument_wrapper']['instrument_webdocument_wrapper_button'] = [
        '#type' => 'markup',
        '#markup' => '<a href="' . $this->getInstrument()->hasWebDocument . '" target="_blank" class="btn btn-success text-nowrap" style="min-width: 160px; height: 38px; display: flex; align-items: center; justify-content: center;">' . $this->t('Visit Web Document') . '</a>',
      ];

    $form['instrument_information']['instrument_hasSIRManagerEmail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Owner'),
      '#default_value' => $this->getInstrument()->hasSIRManagerEmail,
      '#disabled' => TRUE,
    ];

    // **************
    // CONTAINER AREA
    // **************
    $form['instrument_structure'] = [
      '#type' => 'details',
      '#title' => $this->t('Container'),
      '#group' => 'information',
    ];

    # BUILD HEADER
    $header = [
      'containerslot_type' => t('Type'),
      'containerslot_id' => t('ID'),
      'containerslot_priority' => t('Priority'),
      'containerslot_element' => t("Element"),
    ];

    # POPULATE DATA
    $uri=$this->getInstrument()->uri;
    $api = \Drupal::service('rep.api_connector');
    $container = $api->parseObjectResponse($api->getUri($uri),'getUri');
    if ($container == NULL) {

      // Give message to the user saying that there is no structure for current Simulator
      $form['instrument_structure']['no_structure_warning'] = [
        '#type' => 'item',
        '#value' => t('This Simulator has no Structure bellow!')
      ];

      return;
    }

    $form['instrument_structure']['scope'] = [
      '#type' => 'item',
      '#title' => t('<h4>Slots Elements of Container <font color="DarkGreen">' . $this->getInstrument()->label . '</font>, maintained by <font color="DarkGreen">' . $this->getInstrument()->hasSIRManagerEmail . '</font></h4>'),
      '#wrapper_attributes' => [
        'class' => 'mt-3'
      ],
    ];

    $this->setContainer($container);
    $slotElements = $api->parseObjectResponse($api->slotElements($this->getContainer()->uri),'slotElements');
    $root_url = \Drupal::request()->getBaseUrl();
    $output = array();
    $uriType = array();
    if ($slotElements != NULL) {
      foreach ($slotElements as $slotElement) {

        // dpm($slotElement);
        if ($slotElement != NULL) {
          $content = " ";
          $codebook = " ";
          $type = " ";
          $element = " ";
          $componentUri = " ";
          $uri = "uri"; // this variable is used as index, thus it cannot be am empty string
          if (isset($slotElement->uri) && ($slotElement->uri != NULL)) {
            $uri = $slotElement->uri;
          }
          if (isset($slotElement->hascoTypeUri)) {

            // PROCESS SLOTS THAT ARE CONTAINER SLOTS
            if ($slotElement->hascoTypeUri == VSTOI::CONTAINER_SLOT) {

              if ($slotElement->hasComponent != null) {

                $component = $api->parseObjectResponse($api->getUri($slotElement->hasComponent),'getUri');
                // $type = Utils::namespaceUri(VSTOI::DETECTOR);
                // Ter em atenção que o componente agora vai ser um atributo que vai conter dentro qual é o tipo do atributo (detector/actuator)
                if ($component != NULL) {
                  $type = Utils::namespaceUri($component->hascoTypeUri);
                  if (isset($component->uri)) {
                    $componentUri = t('<b>'.$type.'</b>: [<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($component->uri).'">' . $component->typeLabel . '</a>('.Utils::plainStatus($component->hasStatus).')]');
                  }
                  if (isset($component->isAttributeOf)) {
                    $attributOfStatus = $api->parseObjectResponse($api->getUri(Utils::uriFromAutocomplete($component->isAttributeOf)),'getUri');
                    $content = '<b>Attribute Of</b>: [<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode(Utils::uriFromAutocomplete($component->isAttributeOf)).'">'. Utils::namespaceUri($component->isAttributeOf) . "</a>(".(Utils::plainStatus($attributOfStatus->hasStatus)??"Current").")]";
                  } else {
                    $content = '<b>Attribute Of</b>: [EMPTY]';
                  }
                  if (isset($component->codebook->label)) {
                    $codebook = '<b>CB</b>: [<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($component->codebook->uri).'">' . $component->codebook->label . "</a>(".Utils::plainStatus($component->codebook->hasStatus).")]";
                  } else {
                    $codebook = '<b>CB</b>: [EMPTY]';
                  }
                }
              }
              $element = $componentUri . " " . $content . " " . $codebook;

            // PROCESS SLOTS THAT ARE SUBCONTAINERS
            } else if ($slotElement->hascoTypeUri == VSTOI::SUBCONTAINER) {
              $type = Utils::namespaceUri($slotElement->hascoTypeUri);
              $name = " ";
              if (isset($slotElement->label)) {
                $name = '<b>Name</b>: ' . $slotElement->label;
              }
              $element = $name;
            } else {
              $type = "(UNKNOWN)";
            }
          }
        }
        $priority = " ";
        if (isset($slotElement->hasPriority)) {
          $priority = $slotElement->hasPriority;
        }
        $label = " ";
        if (isset($slotElement->label)) {
          $label = t('<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($slotElement->uri).'">' . $slotElement->label . '</a>');
        }
        $output[$uri] = [
          // 'containerslot_up' => 'Up',
          // 'containerslot_down' => 'Down',
          'containerslot_type' => $type,
          'containerslot_id' => $label,
          'containerslot_priority' => $priority,
          'containerslot_element' => t($element),
        ];
        if (isset($slotElement->hascoTypeUri)) {
          $uriType[$uri] = ['type' => $slotElement->hascoTypeUri,];
        }
      }
    }

    $form['instrument_structure']['slotelement_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $output,
      '#empty' => t('No response options found'),
      '#js_select' => FALSE,   // Remove "select all"
    ];

    // // **************
    // // DETECTOR AREA
    // // **************
    // $form['instrument_detector'] = [
    //   '#type' => 'details',
    //   '#title' => $this->t('Detectors'),
    //   '#group' => 'information',
    // ];

    // // **************
    // // ACTUATOR AREA
    // // **************
    // $form['instrument_actuator'] = [
    //   '#type' => 'details',
    //   '#title' => $this->t('Actuators'),
    //   '#group' => 'information',
    // ];

    // // ******************
    // // SUB-CONTAINER AREA
    // // ******************
    // $form['instrument_subcontainers'] = [
    //   '#type' => 'details',
    //   '#title' => $this->t('Sub-Container'),
    //   '#group' => 'information',
    // ];


    // **************
    // REVIEWER AREA
    // **************
    $form['instrument_hasreviewnote'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Review Notes'),
      '#default_value' => $this->getInstrument()->hasReviewNote,
    ];

    $form['instrument_haseditoremail'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Reviewer Email'),
      '#default_value' => \Drupal::currentUser()->getEmail(),
      '#attributes' => [
        'disabled' => 'disabled',
      ],
    ];

    $form['review_approve'] = [
      '#type' => 'submit',
      '#value' => $this->t('Approve'),
      '#name' => 'review_approve',
      '#attributes' => [
        'class' => ['btn', 'btn-success', 'aprove-button'],
      ],
    ];
    $form['review_reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
      '#name' => 'review_reject',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'cancel-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
    ];


    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name != 'back') {
      if(strlen($form_state->getValue('instrument_name')) < 1) {
        $form_state->setErrorByName('instrument_name', $this->t('Please enter a valid name'));
      }
      if(strlen($form_state->getValue('instrument_abbreviation')) < 1) {
        $form_state->setErrorByName('instrument_abbreviation', $this->t('Please enter a valid abbreviation'));
      }
      if(strlen($form_state->getValue('instrument_language')) < 1) {
        $form_state->setErrorByName('instrument_language', $this->t('Please enter a valid language'));
      }
      if(strlen($form_state->getValue('instrument_version')) < 1) {
        $form_state->setErrorByName('instrument_version', $this->t('Please enter a valid version'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    if ($button_name === 'back') {
      self::backUrl();
      return;
    }

    try{
      $uid = \Drupal::currentUser()->id();
      $useremail = \Drupal::currentUser()->getEmail();

      $instrumentJson = '{"uri":"'.$this->getInstrumentUri().'",'.
        '"superUri":"'.Utils::uriFromAutocomplete($form_state->getValue('instrument_type')).'",'.
        '"hascoTypeUri":"'.VSTOI::INSTRUMENT.'",'.
        '"hasStatus":"'.$this->getInstrument()->hasStatus.'",'.
        '"label":"'.$form_state->getValue('instrument_name').'",'.
        '"hasShortName":"'.$form_state->getValue('instrument_abbreviation').'",'.
        '"hasInformant":"'.$form_state->getValue('instrument_informant').'",'.
        '"hasLanguage":"'.$form_state->getValue('instrument_language').'",'.
        '"hasVersion":"'.$form_state->getValue('instrument_version').'",'.
        '"hasWebDocument":"'.$form_state->getValue('instrument_webdocument').'",'.
        '"comment":"'.$form_state->getValue('instrument_description').'",'.
        '"hasSIRManagerEmail":"'.$useremail.'"}';

      //dpm($instrumentJson);
      //return false;

      // UPDATE BY DELETING AND CREATING
      $api = \Drupal::service('rep.api_connector');
      $api->instrumentDel($this->getInstrumentUri());
      $api->instrumentAdd($instrumentJson);

      \Drupal::messenger()->addMessage(t("Instrument has been updated successfully."));
      self::backUrl();
      return;

    }catch(\Exception $e){
      \Drupal::messenger()->addError(t("An error occurred while updating the Instrument: ".$e->getMessage()));
      self::backUrl();
      return;
    }

  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, \Drupal::request()->getRequestUri());
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }


}
