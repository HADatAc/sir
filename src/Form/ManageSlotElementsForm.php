<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Utils;
use Drupal\rep\Vocabulary\VSTOI;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ManageSlotElementsForm extends FormBase {

  protected $container;

  protected $uriType;

  protected array $crumbs;

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
    $this->setContainer($container);
    //dpm($container);

    // RETRIEVE SLOT_ELEMENTS BY CONTAINER
    $slotElements = $api->parseObjectResponse($api->slotElements($this->getContainer()->uri),'slotElements');

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
      'containerslot_priority' => t('Priority'),
      'containerslot_element' => t("Element"),
    ];

    # POPULATE DATA

    $output = array();
    $uriType = array();
    if ($slotElements != NULL) {
      foreach ($slotElements as $slotElement) {
        $detector = NULL;
        $content = " ";
        $codebook = " ";
        $detectorUri = " ";
        $type = " ";
        $element = " ";
        if (isset($slotElement->hascoTypeUri)) {

          // PROCESS SLOTS THAT ARE CONTAINER SLOTS
          if ($slotElement->hascoTypeUri == VSTOI::CONTAINER_SLOT) {
            $type = Utils::namespaceUri(VSTOI::DETECTOR);
            if ($slotElement->hasDetector != null) {
              $detector = $api->parseObjectResponse($api->getUri($slotElement->hasDetector),'getUri');
              if ($detector != NULL) {
                if (isset($detector->uri)) {
                  $detectorUri = '<b>URI</b>: [' . Utils::namespaceUri($slotElement->hasDetector) . "] ";
                }
                if (isset($detector->detectorStem->hasContent)) {
                  $content = '<b>Item</b>: [' . $detector->detectorStem->hasContent . "]";
                }
                if (isset($detector->codebook->label)) {
                  $codebook = '<b>CB</b>: [' . $detector->codebook->label . "]";
                } 
              }
            }
            $element = $detectorUri . " " . $content . " " . $codebook;

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
        $priority = " ";
        if (isset($slotElement->hasPriority)) {
          $priority = $slotElement->hasPriority;
        }
        $output[$slotElement->uri] = [
          'containerslot_up' => 'Up',     
          'containerslot_down' => 'Down',     
          'containerslot_type' => $type,     
          'containerslot_priority' => $priority,     
          'containerslot_element' => t($element),     
        ];
        $uriType[$slotElement->uri] = ['type' => $slotElement->hascoTypeUri,];
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
    $form['add_containerslot'] = [
      '#type' => 'submit',
      '#value' => $this->t("Add Detector's Slots"),
      '#name' => 'add_containerslots',  
    ];
    $form['add_subcontainer'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add SubContainer'),
      '#name' => 'add_subcontainer',
    ];
    $form['edit_slotelement'] = [
      '#type' => 'submit',
      '#value' => $this->t("Edit Selected"),
      '#name' => 'edit_slotelement',
    ];
    $form['delete_selected_elements'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
      '#name' => 'delete_containerslots',    
      '#attributes' => ['onclick' => 'if(!confirm("Really Delete?")){return false;}'],
    ];
    $form['manage_annotations'] = [
      '#type' => 'submit',
      '#value' => $this->t('Manage Annotations'),
      '#name' => 'manage_annotations',
    ];
    $form['manage_subcontainer_structure'] = [
      '#type' => 'submit',
      '#value' => $this->t('Manage Structure of Selected'),
      '#name' => 'manage_subcontainer_structure',    
      '#attributes' => 
        ['class' =>  
          ['button', 'js-form-submit', 'form-submit', 'btn', 'btn-success'],
        ],
];
    $form['slotelement_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#empty' => t('No response options found'),
    ];
    if ($container->hascoTypeUri == VSTOI::SUBCONTAINER) {
      $form['go_parent_container'] = [
        '#type' => 'submit',
        '#value' => $this->t("Back to Parent"),
        '#name' => 'go_parent_container',
        '#attributes' => 
          ['class' =>  
            ['button', 'js-form-submit', 'form-submit', 'btn', 'btn-success'],
          ],
      ];  
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back to Questionnaire Management'),
      '#name' => 'back',
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br><b>Note 1</b>: Use the green [Manage Structure of Selected] button go inside of subcontainers, e.g., a section.<br>'.
                    '<b>Note 2</b>: Once inside of a subcontainer, use the green [Back to Parent] button to get out of the current subcontainer and into a parent container.<br><br><br>'),
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

    /** 
    // RETRIEVE SELECTED ROWS, IF ANY
    $selected_rows = $form_state->getValue('slotelement_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }
    */

    /** 
    // VERIFY MANAGE SUBCONTAINER
    if ($button_name === 'manage_subcontainer_structure') {
      if (sizeof($rows) < 1) {
      $form_state->setErrorByName('', $this->t('Select the exact subcontainer to be edited.'));
      } else if ((sizeof($rows) > 1)) {
        $form_state->setErrorByName('', $this->t('Select only one subcontainer to edit. No more than one subcontainer can be edited at once.'));
      } else {
        $first = array_shift($rows);
        $type = reset($this->getUriType()[$first]);
        if ($type != VSTOI::SUBCONTAINER) {
          $form_state->setWarningByName($first, $this->t('This option if for subcontainers only. '));
        };
      } 
    }
    */

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
          $api->slotelementDel($uri);
        }
        \Drupal::messenger()->addMessage(t("ContainerSlots has been deleted successfully."));
        $breadcrumbsArg = implode('|',$this->getBreadcrumbs());
        $url = Url::fromRoute('sir.edit_subcontainer');
        $url->setRouteParameter('subcontaineruri', base64_encode($this->getContainer()->uri));
        $url->setRouteParameter('breadcrumbs', $breadcrumbsArg);
        $form_state->setRedirectUrl($url);  
        return;
      } 
    }

    // MANAGE CONTAINER'S ANNOTATIONS
    if ($button_name === 'manage_annotations') {
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

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
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
  

}