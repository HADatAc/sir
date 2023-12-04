<?php

namespace Drupal\sir\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rep\Utils;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ManageDetectorSlotsForm extends FormBase {

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
    return 'manage_detectorslots_form';
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

    // RETRIEVE DETECTOR_SLOTS BY INSTRUMENT
    $detectorslot_list = $api->detectorslotList($this->getInstrumentUri());
    $obj = json_decode($detectorslot_list);
    $detectorslots = [];
    if ($obj->isSuccessful) {
      $detectorslots = $obj->body;
    }

    #if (sizeof($detectorslots) <= 0) {
    #  return new RedirectResponse(Url::fromRoute('sir.add_detectorslots', ['instrumenturi' => base64_encode($this->getInstrumentUri())])->toString());
    #}

    #dpm($detectors);

    # BUILD HEADER

    $header = [
      'detectorslot_up' => t('Up'),
      'detectorslot_down' => t('Down'),
      'detectorslot_type' => t('Type'),
      'detectorslot_priority' => t('Priority'),
      'detectorslot_content' => t("Item Stem's Content"),
      'detectorslot_codebook' => t("Item's Codebook"),
      'detectorslot_detector' => t("Item's URI"),
    ];

    # POPULATE DATA

    $output = array();
    foreach ($detectorslots as $detectorslot) {
      $detector = NULL;
      $content = "";
      $codebook = "";
      if ($detectorslot->hasDetector != null) {
        $rawdetector = $api->getUri($detectorslot->hasDetector);
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
      if (isset($detectorslot->hasPriority)) {
        $priority = $detectorslot->hasPriority;
      }
      $type = "";
      if (isset($detectorslot->hostType)) {
        $type = $detectorslot->hostType;
      }
      $detectorUriStr = "";
      if ($detectorslot->hasDetector != NULL && $detectorslot->hasDetector != '') {
        $detectorUriStr = Utils::namespaceUri($detectorslot->hasDetector);
      }
      $output[$detectorslot->uri] = [
        'detectorslot_up' => 'Up',     
        'detectorslot_down' => 'Down',     
        'detectorslot_type' => Utils::namespaceUri($type),     
        'detectorslot_priority' => $priority,     
        'detectorslot_content' => $content,     
        'detectorslot_codebook' => $codebook,
        'detectorslot_detector' => $detectorUriStr     
      ];
    }

    # PUT FORM TOGETHER

    //$form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $form['scope'] = [
      '#type' => 'item',
      '#title' => t('<h3>DetectorSlots of Instrument <font color="DarkGreen">' . $instrument->label . '</font></h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>DetectorSlots maintained by <font color="DarkGreen">' . $name . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['add_detectorslot'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Detector Slot'),
      '#name' => 'add_detectorslots',  
      //'#url' => Url::fromRoute('sir.add_detectorslots', ['instrumenturi' => base64_encode($this->getInstrumentUri())]),
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
    $form['edit_detectorslot'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit Detector Slot'),
      '#name' => 'edit_detectorslot',
    ];
    $form['edit_subcontainer'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit SubContainer'),
      '#name' => 'edit_detectorslot',
    ];
    $form['delete_selected_elements'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
      '#name' => 'delete_detectorslots',    
      '#attributes' => ['onclick' => 'if(!confirm("Really Delete?")){return false;}'],
    ];
    $form['detectorslot_table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $output,
      '#empty' => t('No response options found'),
      //'#ajax' => [
      //  'callback' => '::detectorslotAjaxCallback', 
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

  public function detectorslotAjaxCallback(array &$form, FormStateInterface $form_state) {
    $selected_rows = $form_state->getValue('detectorslot_table');
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
    $selected_rows = $form_state->getValue('detectorslot_table');
    $rows = [];
    foreach ($selected_rows as $index => $selected) {
      if ($selected) {
        $rows[$index] = $index;
      }
    }

    // ADD DETECTOR_SLOT
    if ($button_name === 'add_detectorslots') {
      $url = Url::fromRoute('sir.add_detectorslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getInstrumentUri()));
      $form_state->setRedirectUrl($url);
    }

    // ADD SUBCONTAINER
    if ($button_name === 'add_subcontainer') {
      $url = Url::fromRoute('sir.add_subcontainer');
      $url->setRouteParameter('parenturi', base64_encode($this->getInstrumentUri()));
      $form_state->setRedirectUrl($url);
    }

    // EDIT DETECTOR_SLOT
    if ($button_name === 'edit_detectorslot') {
      if (sizeof($rows) < 1) {
        \Drupal::messenger()->addMessage(t("Select the exact detectorslot to be edited."));      
      } else if ((sizeof($rows) > 1)) {
        \Drupal::messenger()->addMessage(t("Select only one detectorslot to edit. No more than one detectorslot can be edited at once."));      
      } else {
        $first = array_shift($rows);
        $url = Url::fromRoute('sir.edit_detectorslot');
        $url->setRouteParameter('detectorsloturi', base64_encode($first));
        $form_state->setRedirectUrl($url);
      } 
    }

    // DELETE DETECTOR_SLOTS
    if ($button_name === 'delete_detectorslots') {
      $api = \Drupal::service('rep.api_connector');
      $api->detectorslotDel($this->getInstrumentUri());
    
      \Drupal::messenger()->addMessage(t("DetectorSlots has been deleted successfully."));
      $url = Url::fromRoute('sir.manage_detectorslots');
      $url->setRouteParameter('instrumenturi', base64_encode($this->getInstrumentUri()));
      $form_state->setRedirectUrl($url);
  }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $form_state->setRedirectUrl(Utils::selectBackUrl('instrument'));
    }  
  }
  
}