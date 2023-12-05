<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Utils;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ManageContainerSlotsForm extends FormBase {

  protected $instrumentUri;

  public function getInstrumentUri() {
    return $this->instrumentUri;
  }

  public function setInstrumentUri($uri) {
    return $this->instrumentUri = $uri; 
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'manage_containerslots_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $instrumenturi = NULL) {

    # GET CONTENT
    $uri=$instrumenturi ?? 'default';
    $uri_decode=base64_decode($uri);
    $this->setInstrumentUri($uri_decode);

    $uemail = \Drupal::currentUser()->getEmail();
    $uid = \Drupal::currentUser()->id();
    $user = \Drupal\user\Entity\User::load($uid);
    $name = $user->name->value;

    // RETRIEVE INSTRUMENT BY URI
    $api = \Drupal::service('rep.api_connector');
    $rawinstrument = $api->getUri($this->getInstrumentUri());
    $objinstrument = json_decode($rawinstrument);
    $instrument = NULL;
    if ($objinstrument->isSuccessful) {
      $instrument = $objinstrument->body;
    }

    // RETRIEVE CONTAINER_SLOTS BY INSTRUMENT
    $containerslot_list = $api->containerslotList($this->getInstrumentUri());
    $obj = json_decode($containerslot_list);
    $containerslots = [];
    if ($obj->isSuccessful) {
      $containerslots = $obj->body;
    }

    #if (sizeof($containerslots) <= 0) {
    #  return new RedirectResponse(Url::fromRoute('sir.add_containerslots', ['instrumenturi' => base64_encode($this->getInstrumentUri())])->toString());
    #}

    #dpm($detectors);

    # BUILD HEADER

    $header = [
      'containerslot_up' => t('Up'),
      'containerslot_down' => t('Down'),
      'containerslot_type' => t('Type'),
      'containerslot_priority' => t('Priority'),
      'containerslot_content' => t("Item Stem's Content"),
      'containerslot_codebook' => t("Item's Codebook"),
      'containerslot_detector' => t("Item's URI"),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($containerslots as $containerslot) {
      $detector = NULL;
      $content = "";
      $codebook = "";
      if ($containerslot->hasDetector != null) {
        $rawdetector = $api->getUri($containerslot->hasDetector);
        $objdetector = json_decode($rawdetector);
        if ($objdetector->isSuccessful) {
          $detector = $objdetector->body;
          if (isset($detector->detectorStem->hasContent)) {
            $content = $detector->detectorStem->hasContent;
          }
          if (isset($detector->codebook->label)) {
            $codebook = $detector->codebook->label;
          } 
        }
      }
      $priority = "";
      if (isset($containerslot->hasPriority)) {
        $priority = $containerslot->hasPriority;
      }
      $type = "";
      if (isset($containerslot->hostType)) {
        $type = $containerslot->hostType;
      }
      $detectorUriStr = "";
      if ($containerslot->hasDetector != NULL && $containerslot->hasDetector != '') {
        $detectorUriStr = Utils::namespaceUri($containerslot->hasDetector);
      }
      $output[$containerslot->uri] = [
        'containerslot_up' => 'Up',     
        'containerslot_down' => 'Down',     
        'containerslot_type' => Utils::namespaceUri($type),     
        'containerslot_priority' => $priority,     
        'containerslot_content' => $content,     
        'containerslot_codebook' => $codebook,
        'containerslot_detector' => $detectorUriStr     
      ];
    }

    # PUT FORM TOGETHER

    //$form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>ContainerSlots of Instrument <font color="DarkGreen">' . $instrument->label . '</font></h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>ContainerSlots maintained by <font color="DarkGreen">' . $name . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['add_containerslot'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Container Slot'),
      '#name' => 'add_containerslots',  
      //'#url' => Url::fromRoute('sir.add_containerslots', ['instrumenturi' => base64_encode($this->getInstrumentUri())]),
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
      '#value' => $this->t('Edit Container Slot'),
      '#name' => 'edit_containerslot',
    ];
    $form['edit_subcontainer'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit SubContainer'),
      '#name' => 'edit_containerslot',
    ];
    $form['delete_selected_elements'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
      '#name' => 'delete_containerslots',    
      '#attributes' => ['onclick' => 'if(!confirm("Really Delete?")){return false;}'],
    ];
    $form['containerslot_table'] = [
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
    $selected_rows = $form_state->getValue('containerslot_table');
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
    $selected_rows = $form_state->getValue('containerslot_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // ADD CONTAINER_SLOT
    if ($button_name === 'add_containerslots') {
      $url = Url::fromRoute('sir.add_containerslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getInstrumentUri()));
      $form_state->setRedirectUrl($url);
    }

    // ADD SUBCONTAINER
    if ($button_name === 'add_subcontainer') {
      $url = Url::fromRoute('sir.add_subcontainer');
      $url->setRouteParameter('belongsto', base64_encode($this->getInstrumentUri()));
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

    // DELETE CONTAINER_SLOTS
    if ($button_name === 'delete_containerslots') {
      $api = \Drupal::service('rep.api_connector');
      $api->containerslotDel($this->getInstrumentUri());
    
      \Drupal::messenger()->addMessage(t("ContainerSlots has been deleted successfully."));
      $url = Url::fromRoute('sir.manage_slotelements');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getInstrumentUri()));
      $form_state->setRedirectUrl($url);
  }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }  
  }
  
}