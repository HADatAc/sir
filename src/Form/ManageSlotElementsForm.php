<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\rep\Vocabulary\REPGUI;
use Drupal\Component\Serialization\Json;

class ManageSlotElementsForm extends FormBase {

  protected $container;

  protected $uriType;

  protected array $crumbs;

  public $topleftOriginal;
  public $topcenterOriginal;
  public $toprightOriginal;
  public $lineBelowTopOrigonal;
  public $lineAboveBottomOriginal;
  public $bottomleftOriginal;
  public $bottomcenterOriginal;
  public $bottomrightOriginal;

  public function getContainer() {
    return $this->container;
  }

  public function setContainer($container) {
    return $this->container = $container;
  }

  public function getUriType() {
    return $this->uriType;
  }

  public function setUriType($uriType) {
    return $this->uriType = $uriType;
  }

  public function getBreadcrumbs() {
    return $this->crumbs;
  }

  public function setBreadcrumbs(array $crumbs) {
    return $this->crumbs = $crumbs;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'manage_slot_elements_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $containeruri = NULL, $breadcrumbs = NULL) {

    // ^Load Libraries
    $form['#attached']['library'][] = 'sir/sir_manageSlotsElementsForm';

    # SET CONTEXT
    $uri=$containeruri ?? 'default';
    $uri=base64_decode($uri);
    if ($breadcrumbs == "_") {
      $crumbs = array();
    } else {
      $crumbs = explode('|',$breadcrumbs);
    }
    $this->setBreadcrumbs($crumbs);

    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $username = $user->name->value;

    // RETRIEVE CONTAINER BY URI
    $api = \Drupal::service('rep.api_connector');
    $container = $api->parseObjectResponse($api->getUri($uri),'getUri');
    if ($container == NULL) {
      \Drupal::messenger()->addError(t("Cannot read annotations from null container."));
      $this->backToSlotElement($form_state);
    }
    $this->setContainer($container);
    //dpm($container);

    // RETRIEVE CONTAINER'S ANNOTATIONS
    // CHECK if instrument
    if ($this->getContainer()->hascoTypeUri === VSTOI::INSTRUMENT) {
      $isQuestionnaire = Utils::hasQuestionnaireAncestor($uri);
    } else {
      // Case Container must check if container from where it belongs is a Questionair
      $isQuestionnaire = Utils::hasQuestionnaireAncestor($this->getContainer()->belongsTo);
    }

    if ($isQuestionnaire) {
      if ($this->getContainer()->hascoTypeUri == VSTOI::INSTRUMENT) {
        $this->topleftOriginal = $this->retrieveAnnotation(VSTOI::PAGE_TOP_LEFT);
        $this->topcenterOriginal = $this->retrieveAnnotation(VSTOI::PAGE_TOP_CENTER);
        $this->toprightOriginal = $this->retrieveAnnotation(VSTOI::PAGE_TOP_RIGHT);
        $this->lineBelowTopOrigonal = $this->retrieveAnnotation(VSTOI::PAGE_LINE_BELOW_TOP);
        $this->lineAboveBottomOriginal = $this->retrieveAnnotation(VSTOI::PAGE_LINE_ABOVE_BOTTOM);
        $this->bottomleftOriginal = $this->retrieveAnnotation(VSTOI::PAGE_BOTTOM_LEFT);
        $this->bottomcenterOriginal = $this->retrieveAnnotation(VSTOI::PAGE_BOTTOM_CENTER);
        $this->bottomrightOriginal = $this->retrieveAnnotation(VSTOI::PAGE_BOTTOM_RIGHT);
      } else {
        $this->topleftOriginal = $this->retrieveAnnotation(VSTOI::TOP_LEFT);
        $this->topcenterOriginal = $this->retrieveAnnotation(VSTOI::TOP_CENTER);
        $this->toprightOriginal = $this->retrieveAnnotation(VSTOI::TOP_RIGHT);
        $this->lineBelowTopOrigonal = $this->retrieveAnnotation(VSTOI::LINE_BELOW_TOP);
        $this->lineAboveBottomOriginal = $this->retrieveAnnotation(VSTOI::LINE_ABOVE_BOTTOM);
        $this->bottomleftOriginal = $this->retrieveAnnotation(VSTOI::BOTTOM_LEFT);
        $this->bottomcenterOriginal = $this->retrieveAnnotation(VSTOI::BOTTOM_CENTER);
        $this->bottomrightOriginal = $this->retrieveAnnotation(VSTOI::BOTTOM_RIGHT);
      }

      // CREATE LABELS
      $topleftLabel = $this->labelPreparation($this->topleftOriginal);
      $topcenterLabel = $this->labelPreparation($this->topcenterOriginal);
      $toprightLabel = $this->labelPreparation($this->toprightOriginal);
      $linebelowtopLabel = $this->labelPreparation($this->lineBelowTopOrigonal);
      $lineabovebottomLabel = $this->labelPreparation($this->lineAboveBottomOriginal);
      $bottomleftLabel = $this->labelPreparation($this->bottomleftOriginal);
      $bottomcenterLabel = $this->labelPreparation($this->bottomcenterOriginal);
      $bottomrightLabel = $this->labelPreparation($this->bottomrightOriginal);
    }

    // RETRIEVE SLOT_ELEMENTS BY CONTAINER
    $slotElements = $api->parseObjectResponse($api->slotElements($this->getContainer()->uri),'slotElements');

    // RETRIEVE PREFERRED TERM FOR INSTRUMENT
    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');

    #if (sizeof($containerslots) <= 0) {
    #  return new RedirectResponse(Url::fromRoute('sir.add_containerslots', ['containeruri' => base64_encode($this->getContainerUri())])->toString());
    #}

    //dpm($container);
    //dpm($slotElements);

    # BUILD HEADER

    $header = [
      'containerslot_up' => t('Up'),
      'containerslot_down' => t('Down'),
      'containerslot_type' => t('Type'),
      'containerslot_id' => t('ID'),
      'containerslot_priority' => t('Priority'),
      'containerslot_element' => t("Element"),
    ];

    # POPULATE DATA
    $root_url = \Drupal::request()->getBaseUrl();
    $output = array();
    $uriType = array();
    if ($slotElements != NULL) {
      foreach ($slotElements as $slotElement) {

        // dpm($slotElement);
        if ($slotElement != NULL) {
          $detector = NULL;
          $content = " ";
          $codebook = " ";
          $detectorUri = " ";
          $type = " ";
          $element = " ";
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
                  $type = $component->hascoTypeUri === VSTOI::DETECTOR ? 'Detector':'Actuator';
                  if (isset($component->uri)) {
                    $componentUri = t('<b>'.$type.'</b>: [<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($component->uri).'">' . $component->typeLabel . '</a>] ');
                  }
                  if (isset($component->isAttributeOf)) {
                    $content = '<b>Attribute Of</b>: [<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode(Utils::uriFromAutocomplete($component->isAttributeOf)).'">'. Utils::getLabelFromURI($component->isAttributeOf) . "</a>]";
                  } else {
                    $content = '<b>Attribute Of</b>: [EMPTY]';
                  }
                  if (isset($component->codebook->label)) {
                    $codebook = '<b>CB</b>: [<a target="_new" href="'.$root_url.REPGUI::DESCRIBE_PAGE.base64_encode($component->codebook->uri).'">' . $component->codebook->label . "</a>]";
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
          'containerslot_up' => 'Up',
          'containerslot_down' => 'Down',
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
    $this->setUriType($uriType);

    # PUT FORM TOGETHER

    $path = "";
    $length = count($this->getBreadcrumbs());
    for ($i = 0; $i < $length; $i++) {
        $path .= '<font color="DarkGreen">' . $this->getBreadcrumbs()[$i] . '</font>';
        if ($i < $length - 1) {
            $path .= ' > ';
        }
    }

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>Slots Elements of Container ' . $path . '</h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>ContainerSlots maintained by <font color="DarkGreen">' . $username . ' (' . $uemail . ')</font></h4>'),
    ];

    //SHOW TOP PAGE/SECTION STRUCTURE
    // Create a wrapper container
    $isInstrument = ($container->hascoTypeUri === VSTOI::INSTRUMENT);
    if ($isQuestionnaire) {
      $form['top_annotations_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['top-annotations-wrapper', 'mb-4']],
      ];

      // Header with toggle functionality
      $form['top_annotations_wrapper']['header'] = [
        '#type' => 'markup',
        '#markup' => '<div class="collapsible-header">
                        <span class="fw-bold">'.($isInstrument ? 'PAGE':'').' HEADER</span>
                        <span class="collapse-icon">▼</span>
                      </div>',
        '#allowed_tags' => ['div', 'span'],
      ];

      // Content wrapper (initially hidden)
      $form['top_annotations_wrapper']['content'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['collapsible-content', 'd-none']], // Hide by default
      ];

      if ($this->getContainer()->hascoTypeUri == VSTOI::INSTRUMENT || $this->getContainer()->hascoTypeUri === VSTOI::SUBCONTAINER) {
        // First row (3 columns) inside collapsible section
        $form['top_annotations_wrapper']['content']['first_row'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['row', 'no-margin']],
        ];

        $form['top_annotations_wrapper']['content']['first_row']['col_1'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-4']],
        ];

        $form['top_annotations_wrapper']['content']['first_row']['col_1']['annotation_topleft'] = [
          '#type' => 'textfield',
          '#title' => ($isInstrument ? 'PageTopLeft':'TopLeft'),
          '#default_value' => $topleftLabel,
          '#autocomplete_route_name' => 'sir.annotation_autocomplete',
          '#autocomplete_route_parameters' => [
            'parent_type' => ($isInstrument ? 'instrument' : 'subcontainer'),
          ],
          '#attributes' => [
            'class' => ['form-control', 'floating-label-input', 'mt-4'],
            'placeholder' => ($isInstrument ? 'PageTopLeft':'TopLeft'),
            'id' => ($isInstrument ? 'PageTopLeft':'TopLeft')
          ],
          '#prefix' => '<div class="floating-container">',
          '#suffix' => '</div>',
        ];

        $form['top_annotations_wrapper']['content']['first_row']['col_2'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-4']],
        ];

        $form['top_annotations_wrapper']['content']['first_row']['col_2']['annotation_topcenter'] = [
          '#type' => 'textfield',
          '#title' => ($isInstrument ? 'PageTopCenter':'TopCenter'),
          '#default_value' => $topcenterLabel,
          '#autocomplete_route_name' => 'sir.annotation_autocomplete',
          '#attributes' => [
            'class' => ['form-control', 'floating-label-input', 'mt-4'],
            'placeholder' => ($isInstrument ? 'PageTopCenter':'TopCenter'),
            'id' => ($isInstrument ? 'PageTopCenter':'TopCenter')
          ],
          '#prefix' => '<div class="floating-container">',
          '#suffix' => '</div>',
        ];

        $form['top_annotations_wrapper']['content']['first_row']['col_3'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-4']],
        ];

        $form['top_annotations_wrapper']['content']['first_row']['col_3']['annotation_topright'] = [
          '#type' => 'textfield',
          '#title' => ($isInstrument ? 'PageTopRight':'TopRight'),
          '#default_value' => $toprightLabel,
          '#autocomplete_route_name' => 'sir.annotation_autocomplete',
          '#attributes' => [
            'class' => ['form-control', 'floating-label-input', 'mt-4'],
            'placeholder' => ($isInstrument ? 'PageTopRight':'TopRight'),
            'id' => ($isInstrument ? 'PageTopRight':'TopRight'),
          ],
          '#prefix' => '<div class="floating-container">',
          '#suffix' => '</div>',
        ];

        // Second row (1 full-width column) inside collapsible section
        $form['top_annotations_wrapper']['content']['second_row'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['row', 'no-margin']],
        ];

        $form['top_annotations_wrapper']['content']['second_row']['col_full'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-12']],
        ];

        $form['top_annotations_wrapper']['content']['second_row']['col_full']['annotation_linebelowtop'] = [
          '#type' => 'textfield',
          '#title' => ($isInstrument ? 'PageLineBelowTop':'LineBelowTop'),
          '#default_value' => $linebelowtopLabel,
          '#autocomplete_route_name' => 'sir.annotation_autocomplete',
          '#attributes' => [
            'class' => ['form-control', 'floating-label-input', 'mt-4'],
            'placeholder' => ($isInstrument ? 'PageLineBelowTop':'LineBelowTop'),
            'id' => ($isInstrument ? 'PageLineBelowTop':'LineBelowTop'),
          ],
          '#prefix' => '<div class="floating-container">',
          '#suffix' => '</div>',
        ];
      }
    }

    //PAGE OR SECTION CONTENT
    //BUTTONS
    $form['add_containerslot'] = [
      '#type' => 'submit',
      '#value' => $this->t("Add Components's Slots"),
      '#name' => 'add_containerslots',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-element-button'],
      ],
    ];
    $form['add_subcontainer'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add SubContainer'),
      '#name' => 'add_subcontainer',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'add-element-button'],
      ],
    ];
    $form['edit_slotelement'] = [
      '#type' => 'submit',
      '#value' => $this->t("Edit Selected"),
      '#name' => 'edit_slotelement',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'edit-element-button'],
      ],
    ];
    $form['delete_selected_elements'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
      '#name' => 'delete_containerslots',
      '#attributes' => [
        'onclick' => 'if(!confirm("Really Delete?")){return false;}',
        'class' => ['btn', 'btn-primary', 'delete-element-button'],
      ],
    ];
    $form['manage_annotations'] = [
      '#type' => 'submit',
      '#value' => $this->t('Manage Annotations'),
      '#name' => 'manage_annotations',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'manage_annotations-button'],
      ],
    ];
    // $form['manage_annotation_placement'] = [
    //   '#type' => 'submit',
    //   '#value' => $this->t('Manage Annotation Placement'),
    //   '#name' => 'manage_annotation_placement',
    //   '#attributes' => [
    //     'class' => ['btn', 'btn-primary', 'manage_annotation_placement'],
    //   ],
    // ];
    $form['manage_subcontainer_structure'] = [
      '#type' => 'submit',
      '#value' => $this->t('Manage Structure of Selected'),
      '#name' => 'manage_subcontainer_structure',
      '#attributes' =>
        [
          'class' =>
            ['button', 'js-form-submit', 'form-submit', 'btn', 'btn-success', 'manage_slotelements-button'],
          'style' => 'background-color: yellowgreen;'
        ],
    ];

    $form['slotelement_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#empty' => t('No response options found'),
    ];

    //SHOW BOTTOM PAGE/SECTION STRUCTURE
    if ($isQuestionnaire) {
      $form['bottom_annotations_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['top-annotations-wrapper', 'mb-4']],
      ];

      // Header with toggle functionality
      $form['bottom_annotations_wrapper']['footer'] = [
        '#type' => 'markup',
        '#markup' => '<div class="collapsible-footer">
                        <span class="fw-bold">'.($isInstrument ? 'PAGE':'').' FOOTER</span>
                        <span class="collapse-icon">▼</span>
                      </div>',
        '#allowed_tags' => ['div', 'span'],
      ];

      // Content wrapper (initially hidden)
      $form['bottom_annotations_wrapper']['content'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['collapsible-content', 'd-none']], // Hide by default
      ];

      if ($this->getContainer()->hascoTypeUri == VSTOI::INSTRUMENT || $this->getContainer()->hascoTypeUri === VSTOI::SUBCONTAINER) {

        // First row (1 full-width column) inside collapsible section
        $form['bottom_annotations_wrapper']['content']['first_row'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['row', 'no-margin']],
        ];

        $form['bottom_annotations_wrapper']['content']['first_row']['col_full'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-12']],
        ];

        $form['bottom_annotations_wrapper']['content']['first_row']['col_full']['annotation_lineabovebottom'] = [
          '#type' => 'textfield',
          '#title' => ($isInstrument ? 'PageLineBelowTop':'LineBelowTop'),
          '#default_value' => $lineabovebottomLabel,
          '#autocomplete_route_name' => 'sir.annotation_autocomplete',
          '#attributes' => [
            'class' => ['form-control', 'floating-label-input', 'mt-4'],
            'placeholder' => ($isInstrument ? 'PageLineBelowTop':'LineBelowTop'),
            'id' => ($isInstrument ? 'PageLineBelowTop':'LineBelowTop'),
          ],
          '#prefix' => '<div class="floating-container">',
          '#suffix' => '</div>',
        ];

        // Second row (3 columns) inside collapsible section
        $form['bottom_annotations_wrapper']['content']['second_row'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['row', 'no-margin']],
        ];

        $form['bottom_annotations_wrapper']['content']['second_row']['col_1'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-4']],
        ];

        $form['bottom_annotations_wrapper']['content']['second_row']['col_1']['annotation_bottomleft'] = [
          '#type' => 'textfield',
          '#title' => ($isInstrument ? 'PageBottomLeft':'BottomLeft'),
          '#default_value' => $bottomleftLabel,
          '#autocomplete_route_name' => 'sir.annotation_autocomplete',
          '#attributes' => [
            'class' => ['form-control', 'floating-label-input', 'mt-4'],
            'placeholder' => ($isInstrument ? 'PageBottomLeft':'BottomLeft'),
            'id' => ($isInstrument ? 'PageBottomLeft':'BottomLeft'),
          ],
          '#prefix' => '<div class="floating-container">',
          '#suffix' => '</div>',
        ];

        $form['bottom_annotations_wrapper']['content']['second_row']['col_2'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-4']],
        ];

        $form['bottom_annotations_wrapper']['content']['second_row']['col_2']['annotation_bottomcenter'] = [
          '#type' => 'textfield',
          '#title' => ($isInstrument ? 'PageBottomCenter':'BottomCenter'),
          '#default_value' => $bottomcenterLabel,
          '#autocomplete_route_name' => 'sir.annotation_autocomplete',
          '#attributes' => [
            'class' => ['form-control', 'floating-label-input', 'mt-4'],
            'placeholder' => ($isInstrument ? 'PageBottomCenter':'BottomCenter'),
            'id' => ($isInstrument ? 'PageBottomCenter':'BottomCenter'),
          ],
          '#prefix' => '<div class="floating-container">',
          '#suffix' => '</div>',
        ];

        $form['bottom_annotations_wrapper']['content']['second_row']['col_3'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['col-md-4']],
        ];

        $form['bottom_annotations_wrapper']['content']['second_row']['col_3']['annotation_bottomright'] = [
          '#type' => 'textfield',
          '#title' => ($isInstrument ? 'PageBottomRight':'BottomRight'),
          '#default_value' => $bottomrightLabel,
          '#autocomplete_route_name' => 'sir.annotation_autocomplete',
          '#attributes' => [
            'class' => ['form-control', 'floating-label-input', 'mt-4'],
            'placeholder' => ($isInstrument ? 'PageBottomRight':'BottomRight'),
            'id' => ($isInstrument ? 'PageBottomRight':'BottomRight'),
          ],
          '#prefix' => '<div class="floating-container">',
          '#suffix' => '</div>',
        ];
      }
    }

    if ($container->hascoTypeUri == VSTOI::SUBCONTAINER) {
      $form['go_parent_container'] = [
        '#type' => 'submit',
        '#value' => $this->t("Back to Parent"),
        '#name' => 'go_parent_container',
        '#attributes' =>
          ['class' =>
            ['button', 'js-form-submit', 'form-submit', 'btn', 'btn-success', 'back_parent-button'],
            'style' => 'background-color: yellowgreen;'
          ],
      ];
    }

    //END FORM
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back to ' . $preferred_instrument . ' Management'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br><b>Note 1</b>: Use the green [Manage Structure of Selected] button go inside of subcontainers, e.g., a section.<br>'.
                    '<b>Note 2</b>: Once inside of a subcontainer, use the green [Back to Parent] button to get out of the current subcontainer and into a parent container.<br><br><br>'),
    ];

    // Add a hidden "Save" button
    $form['auto_save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'd-none'], // Hide button
        'id' => 'auto-save-button',
      ],
    ];

      // Add a hidden "Save" button
    $form['auto_save_trigger'] = [
      '#type' => 'hidden',
      '#value' => '',
      '#name' => 'auto_save_trigger',
      '#attributes' => [
        'id' => 'auto-save-trigger',
      ],
    ];

    return $form;
  }

  public function containerslotAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_rows = $form_state->getValue('slotelement_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $submitted_values = $form_state->cleanValues()->getValues();
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // RETRIEVE TRIGGERING BUTTON
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];

    // RETRIEVE SELECTED ROWS, IF ANY
    $selected_rows = $form_state->getValue('slotelement_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // BUILD BACK URL INCLUDING BREADCRUMBS
    $backUrl = Url::fromRoute('sir.manage_slotelements',
    ['containeruri' => base64_encode($this->getContainer()->uri),
     'breadcrumbs' => base64_encode(implode('|',$this->getBreadcrumbs())),]);

    // ADD CONTAINER_SLOT
    if ($button_name === 'add_containerslots') {
      $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
      $url = Url::fromRoute('sir.add_containerslots');
      $url->setRouteParameter('containeruri', base64_encode($this->getContainer()->uri));
      $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
      $form_state->setRedirectUrl($url);
    }

    // ADD SUBCONTAINER
    if ($button_name === 'add_subcontainer') {
      $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
      $url = Url::fromRoute('sir.add_subcontainer');
      $url->setRouteParameter('belongsto', base64_encode($this->getContainer()->uri));
      $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
      $form_state->setRedirectUrl($url);
    }

    // EDIT SLOT_ELEMENT
    if ($button_name === 'edit_slotelement') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact containerslot to be edited."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("Select only one containerslot to edit. No more than one containerslot can be edited at once."));
      } else {
        $first = array_shift($rows);
        $type = reset($this->getUriType()[$first]);
        $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
        if ($type == VSTOI::SUBCONTAINER) {
          $url = Url::fromRoute('sir.edit_subcontainer');
          $url->setRouteParameter('subcontaineruri', base64_encode($first));
          $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
          $form_state->setRedirectUrl($url);
        } else {
          $url = Url::fromRoute('sir.edit_containerslot');
          $url->setRouteParameter('containersloturi', base64_encode($first));
          $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
          $form_state->setRedirectUrl($url);
        };
      }
      return;
    }

    // MANAGE SUBCONTAINER
    if ($button_name === 'manage_subcontainer_structure') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addWarning(t("Select the exact subcontainer to be edited."));
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addWarning(t("Select only one subcontainer to edit. No more than one subcontainer can be edited at once."));
      } else {
        $first = array_shift($rows);
        $type = reset($this->getUriType()[$first]);
        if ($type == VSTOI::SUBCONTAINER) {
          $api = \Drupal::service('rep.api_connector');
          $subcontainer = $api->parseObjectResponse($api->getUri($first),'getUri');
          $newCrumbs = $this->getBreadcrumbs();
          $newCrumbs[] = $subcontainer->label;
          $url = Url::fromRoute('sir.manage_slotelements',
            ['containeruri' => base64_encode($first),
             'breadcrumbs' => implode('|',$newCrumbs),]);
          $form_state->setRedirectUrl($url);
        } else {
          \Drupal::messenger()->addWarning(t("This option if for subcontainers only. "));
        };
      }
      return;
    }

    // DELETE SLOT_ELEMENT
    if ($button_name === 'delete_containerslots') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select slots to be deleted."));
        return;
      } else {
        $api = \Drupal::service('rep.api_connector');
        //dpm($rows);
        foreach($rows as $shortUri) {
          $uri = Utils::plainUri($shortUri);
          $api->elementDel('slotelement',$uri);
        }
        \Drupal::messenger()->addMessage(t("ContainerSlots has been deleted successfully."));
        //$breadcrumbsArg = implode('|',$this->getBreadcrumbs());
        //$url = Url::fromRoute('sir.edit_subcontainer');
        //$url->setRouteParameter('subcontaineruri', base64_encode($this->getContainer()->uri));
        //$url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
        //$form_state->setRedirectUrl($url);
        return;
      }
    }

    // MANAGE CONTAINER'S ANNOTATIONS
    if ($button_name === 'manage_annotations') {
      $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
      $url = Url::fromRoute('sir.manage_annotations');
      $url->setRouteParameter('elementtype', 'annotation');
      $url->setRouteParameter('page', '1');
      $url->setRouteParameter('pagesize', '10');
      $url->setRouteParameter('containeruri', base64_encode($this->getContainer()->uri));
      $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
      // $uid = \Drupal::currentUser()->id();
      // $previousUrl = \Drupal::request()->getRequestUri();
      // Utils::trackingStoreUrls($uid, $previousUrl, 'sir.manage_slotelements');
      $form_state->setRedirectUrl($url);
      return;
    }

    // MANAGE CONTAINER'S ANNOTATION PLACEMENT
    if ($button_name === 'manage_annotation_placement') {
      $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
      $url = Url::fromRoute('sir.manage_container_annotations');
      $url->setRouteParameter('containeruri', base64_encode($this->getContainer()->uri));
      $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
      $form_state->setRedirectUrl($url);
      return;
    }

    // GO PARENT CONTAINER
    if ($button_name === 'go_parent_container') {
      $newCrumbs = $this->getBreadcrumbs();
      if (count($newCrumbs) <= 1) {
        $breadcrumbsArg = "_";
      } else {
        unset($newCrumbs[count($newCrumbs) - 1]);
        $this->setBreadcrumbs($newCrumbs);
        $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
      }
      $url = Url::fromRoute('sir.manage_slotelements');
      $url->setRouteParameter('containeruri', base64_encode($this->getContainer()->belongsTo));
      $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
      $form_state->setRedirectUrl($url);
      return;
    }


    $auto_save_trigger = $form_state->cleanValues()->getUserInput('auto_save_trigger');
    // If triggered by auto-save
    if ($button_name === 'save') {

      $msg = '';
      switch ($auto_save_trigger['auto_save_trigger']) {
        case 'PageTopLeft':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_topleft'), $this->topleftOriginal, VSTOI::PAGE_TOP_LEFT, $form_state);
          break;
        case 'PageTopCenter':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_topcenter'), $this->topcenterOriginal, VSTOI::PAGE_TOP_CENTER, $form_state);
          break;
        case 'PageTopRight':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_topright'), $this->toprightOriginal, VSTOI::PAGE_TOP_RIGHT, $form_state);
          break;
        case 'PageLineBellowTop':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_linebelowtop'), $this->lineBelowTopOrigonal, VSTOI::PAGE_LINE_BELOW_TOP, $form_state);
          break;
        case 'PageLineAboveBottom':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_lineabovebottom'), $this->lineAboveBottomOriginal, VSTOI::PAGE_LINE_ABOVE_BOTTOM, $form_state);
          break;
        case 'PageBottomLeft':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_bottomleft'), $this->bottomleftOriginal, VSTOI::PAGE_BOTTOM_LEFT, $form_state);
          break;
        case 'PageBottomCenter':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_bottomcenter'), $this->bottomcenterOriginal, VSTOI::PAGE_BOTTOM_CENTER, $form_state);
          break;
        case 'PageBottomRight':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_bottomright'), $this->bottomrightOriginal, VSTOI::PAGE_BOTTOM_RIGHT, $form_state);
          break;

        // SUB-CONTAINER
        case 'TopLeft':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_topleft'), $this->topleftOriginal, VSTOI::TOP_LEFT, $form_state);
          break;
        case 'TopCenter':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_topcenter'), $this->topcenterOriginal, VSTOI::TOP_CENTER, $form_state);
          break;
        case 'TopRight':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_topright'), $this->toprightOriginal, VSTOI::TOP_RIGHT, $form_state);
          break;
        case 'LineBellowTop':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_linebelowtop'), $this->lineBelowTopOrigonal, VSTOI::LINE_BELOW_TOP, $form_state);
          break;
        case 'LineAboveBottom':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_lineabovebottom'), $this->lineAboveBottomOriginal, VSTOI::LINE_ABOVE_BOTTOM, $form_state);
          break;
        case 'BottomLeft':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_bottomleft'), $this->bottomleftOriginal, VSTOI::BOTTOM_LEFT, $form_state);
          break;
        case 'BottomCenter':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_bottomcenter'), $this->bottomcenterOriginal, VSTOI::BOTTOM_CENTER, $form_state);
          break;
        case 'BottomRight':
          $msg = $this->saveAnnotation($form_state->getValue('annotation_bottomright'), $this->bottomrightOriginal, VSTOI::BOTTOM_RIGHT, $form_state);
          break;

        default:
          $msg = "No Position was detected!";
          break;
      }

    if ($msg != "") {
      \Drupal::messenger()->addMessage(t($msg));
    }
    return;
  }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      self::backUrl();
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  private static function backToSlotElementUrl($containeruri, array $breadcrumbs) {
    $newCrumbs = $breadcrumbs;
    if (count($newCrumbs) <= 1) {
      $breadcrumbsArg = "_";
    } else {
      unset($newCrumbs[count($newCrumbs) - 1]);
      $this->setBreadcrumbs($newCrumbs);
      $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
    }
    $url = Url::fromRoute('sir.manage_slotelements');
    $url->setRouteParameter('containeruri', base64_encode($containeruri));
    $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  private function saveAnnotation($newValue, $original, $position, FormStateInterface $form_state) {

    $api = \Drupal::service('rep.api_connector');

    if (($newValue == NULL || $newValue == "") && $original == NULL) {
      return "";
    }

    $annotationUri = Utils::uriFromAutocomplete($newValue);

    //dpm($original);

    if ($original->uri !== NULL) {

      try {

        // UPDATE EXISTING ANNOTATION POSITION TO NOT VISIBLE
        $annotationJson = '{"uri":"'.$original->uri.'",'.
          '"typeUri":"'.VSTOI::ANNOTATION.'",'.
          '"hascoTypeUri":"'.VSTOI::ANNOTATION.'",'.
          '"hasAnnotationStem":"'.$original->hasAnnotationStem.'",'.
          '"hasPosition":"'.VSTOI::NOT_VISIBLE.'",'.
          '"hasContentWithStyle":"'.$original->hasContentWithStyle.'",'.
          '"comment":"'.$original->comment.'",'.
          '"belongsTo":"'.$original->belongsTo.'",'.
          '"hasSIRManagerEmail":"'.$original->hasSIRManagerEmail.'"}';

        $api->annotationDel($original->uri);
        $api->annotationAdd($annotationJson);

        // SET NEW ANNOTATION TO POSITION
        //Get Content of Annotation
        $result = $api->parseObjectResponse($api->getUri($annotationUri),'getUri');

        $annotationNewJson = '{"uri":"'.$result->uri.'",'.
        '"typeUri":"'.VSTOI::ANNOTATION.'",'.
        '"hascoTypeUri":"'.VSTOI::ANNOTATION.'",'.
        '"hasAnnotationStem":"'.$result->hasAnnotationStem.'",'.
        '"hasPosition":"'.$position.'",'.
        '"hasContentWithStyle":"'.$result->hasContentWithStyle.'",'.
        '"comment":"'.$result->comment.'",'.
        '"belongsTo":"'.$result->belongsTo.'",'.
        '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'"}';

        $api->annotationDel($result->uri);
        $api->annotationAdd($annotationNewJson);

      } catch(\Exception $e){
        \Drupal::messenger()->addError(t("An error occurred while updating the Annotation: ".$e->getMessage()));
        $this->backToSlotElement($form_state);
      }

    } else {

      try {

        // SET NEW ANNOTATION TO POSITION
        //Get Content of Annotation
        $result = $api->parseObjectResponse($api->getUri($annotationUri),'getUri');
        //$result = $annotation->isSuccessful ? $annotation->body : [];

        // dpm($result);

        $annotationNewJson = '{"uri":"'.$result->uri.'",'.
        '"typeUri":"'.VSTOI::ANNOTATION.'",'.
        '"hascoTypeUri":"'.VSTOI::ANNOTATION.'",'.
        '"hasAnnotationStem":"'.$result->hasAnnotationStem.'",'.
        '"hasPosition":"'.$position.'",'.
        '"hasContentWithStyle":"'.$result->hasContentWithStyle.'",'.
        '"comment":"'.$result->comment.'",'.
        '"belongsTo":"'.$result->belongsTo.'",'.
        '"hasSIRManagerEmail":"'.$result->hasSIRManagerEmail.'"}';

        // dpm($annotationNewJson);

        $api->annotationDel($result->uri);
        $api->annotationAdd($annotationNewJson);

      } catch(\Exception $e){
        \Drupal::messenger()->addError(t("An error occurred while updating the Annotation: ".$e->getMessage()));
        $this->backToSlotElement($form_state);
      }
    }

    return "Annotation added for ".Utils::namespaceUri($position).". ";


  }

  /**
   * {@inheritdoc}
   */
  private function backToSlotElement(FormStateInterface $form_state) {
    $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
    $url = Url::fromRoute('sir.manage_slotelements');
    $url->setRouteParameter('containeruri', base64_encode($this->getContainer()->uri));
    $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
    $form_state->setRedirectUrl($url);
    return;
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAnnotation(String $position) {

    $api = \Drupal::service('rep.api_connector');
    $rawelement = $api->annotationByContainerAndPosition($this->getContainer()->uri,$position);
    if ($rawelement == NULL) {
      return NULL;
    }
    $elements = $api->parseObjectResponse($rawelement,'annotationByContainerAndPosition');
    if ($elements != NULL && sizeof($elements) >= 1) {
      return $elements[0];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function labelPreparation($annotation) {

    //dpm($annotation);

    if ($annotation == NULL ||
        $annotation->uri == NULL ||
        $annotation->uri == "" ||
        $annotation->annotationStem == NULL ||
        $annotation->annotationStem->hasContent == NULL ||
        $annotation->annotationStem->hasContent == ""
      ){
      return "";
    }

    return Utils::trimAutoCompleteString(html_entity_decode($annotation->comment),$annotation->uri);
  }

  function backUrl() {
    $uid = \Drupal::currentUser()->id();
    $previousUrl = Utils::trackingGetPreviousUrl($uid, 'sir.manage_slotelements');
    if ($previousUrl) {
      $response = new RedirectResponse($previousUrl);
      $response->send();
      return;
    }
  }



}
