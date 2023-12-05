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

  protected $containerUri;

  public function getContainerUri() {
    return $this->containerUri;
  }

  public function setContainerUri($uri) {
    return $this->containerUri = $uri; 
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
  public function buildForm(array $form, FormStateInterface $form_state, $containeruri = NULL) {

    # GET CONTENT
    $uri=$containeruri ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setContainerUri($uri_decode);

    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $username = $user->name->value;

    // RETRIEVE CONTAINER BY URI
    $api = \Drupal::service('rep.api_connector');
    $container = $api->parseObjectResponse($api->getUri($this->getContainerUri()),'getUri');

    //dpm($container);

    // RETRIEVE SLOT_ELEMENTS BY CONTAINER
    $slotElements = $api->parseObjectResponse($api->slotElements($this->getContainerUri()),'slotElements');

    #if (sizeof($containerslots) <= 0) {
    #  return new RedirectResponse(Url::fromRoute('sir.add_containerslots', ['containeruri' => base64_encode($this->getContainerUri())])->toString());
    #}

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
      }
    }

    # PUT FORM TOGETHER

    //$form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>Slots Elements of Container <font color="DarkGreen">' . $container->label . '</font></h3>'),
    ];
    if ($container->hascoTypeUri == VSTOI::SUBCONTAINER) {
      $form['go_parent_container'] = [
        '#type' => 'submit',
        '#value' => $this->t("Go Parent Container"),
        '#name' => 'go_parent_container',
      ];  
      $form['space'] = [
        '#type' => 'item',
        '#title' => t(' '),
      ];
    }
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>ContainerSlots maintained by <font color="DarkGreen">' . $username . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['add_containerslot'] = [
      '#type' => 'submit',
      '#value' => $this->t("Add Detector's Slots"),
      '#name' => 'add_containerslots',  
      //'#url' => Url::fromRoute('sir.add_containerslots', ['containeruri' => base64_encode($this->getContainerUri())]),
      //'#attributes' => [
      //  'class' => ['button use-ajax js-form-submit form-submit btn btn-primary'],
      //  'data-dialog-type' => 'modal',
      //  'data-dialog-options' => Json::encode([
      //    'height' => 400,
      //    'width' => 700
      //  ]),
      //],
    ];
    $form['add_subcontainer'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add SubContainer'),
      '#name' => 'add_subcontainer',
    ];
    $form['edit_containerslot'] = [
      '#type' => 'submit',
      '#value' => $this->t("Edit Detector's Slot"),
      '#name' => 'edit_containerslot',
    ];
    $form['edit_subcontainer'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit SubContainer'),
      '#name' => 'edit_subcontainer',
    ];
    $form['delete_selected_elements'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
      '#name' => 'delete_containerslots',    
      '#attributes' => ['onclick' => 'if(!confirm("Really Delete?")){return false;}'],
    ];
    $form['manage_annotations'] = [
      '#type' => 'submit',
      '#value' => $this->t('Manage annotations of Selected'),
      '#name' => 'manage_annotations',
    ];
    $form['slotelement_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#empty' => t('No response options found'),
      //'#ajax' => [
      //  'callback' => '::containerslotAjaxCallback', 
      //  'disable-refocus' => FALSE, 
      //  'event' => 'change',
      //  'wrapper' => 'edit-output', 
      //  'progress' => [
      //    'type' => 'throbber',
      //    'message' => $this->t('Verifying entry...'),
      //  ],
      //]    
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
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

    // ADD CONTAINER_SLOT
    if ($button_name === 'add_containerslots') {
      $url = Url::fromRoute('sir.add_containerslots');
      $url->setRouteParameter('containeruri', base64_encode($this->getContainerUri()));
      $form_state->setRedirectUrl($url);
    }

    // ADD SUBCONTAINER
    if ($button_name === 'add_subcontainer') {
      $url = Url::fromRoute('sir.add_subcontainer');
      $url->setRouteParameter('belongsto', base64_encode($this->getContainerUri()));
      $form_state->setRedirectUrl($url);
    }

    // EDIT CONTAINER_SLOT
    if ($button_name === 'edit_containerslot') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact containerslot to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one containerslot to edit. No more than one containerslot can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.edit_containerslot');
        $url->setRouteParameter('containersloturi', base64_encode($first));
        $form_state->setRedirectUrl($url);
      } 
    }

    // EDIT SUBCONTAINER
    if ($button_name === 'edit_subcontainer') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact subcontainer to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one subcontainer to edit. No more than one subcontainer can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.manage_slotelements', ['containeruri' => base64_encode($first)]);
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE SLOT_ELEMENT
    if ($button_name === 'delete_containerslots') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select slots to be deleted."));      
      } else {
        $api = \Drupal::service('rep.api_connector');
        //dpm($rows);
        foreach($rows as $shortUri) {
          $uri = Utils::plainUri($shortUri);
          $api->slotelementDel($uri);
        }
        \Drupal::messenger()->addMessage(t("ContainerSlots has been deleted successfully."));
        $url = Url::fromRoute('sir.manage_slotelements');
        $url->setRouteParameter('containeruri', base64_encode($this->getContainerUri()));
        $form_state->setRedirectUrl($url);
        } 
    }

    // GO PARENT CONTAINER
    if ($button_name === 'go_parent_container') {
      $url = Url::fromRoute('sir.manage_slotelements', ['containeruri' => base64_encode($container->belongsTo)]);
      $form_state->setRedirectUrl($url);
    }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }  
  }
  
}