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

class ManageContainerAnnotationsForm extends FormBase {

  protected $container;
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
    return 'manage_container_annotations_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $containeruri = NULL, $breadcrumbs = NULL) {

    // SET CONTEXT
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

    // RETRIEVE CONTAINER'S ANNOTATIONS

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
    //dpm($this->topleftOriginal);
    //dpm($this->toprightOriginal);

    // CREATE LABELS
    $topleftLabel = $this->labelPreparation($this->topleftOriginal);
    $topcenterLabel = $this->labelPreparation($this->topcenterOriginal);
    $toprightLabel = $this->labelPreparation($this->toprightOriginal);
    $linebelowtopLabel = $this->labelPreparation($this->lineBelowTopOrigonal);
    $lineabovebottomLabel = $this->labelPreparation($this->lineAboveBottomOriginal);
    $bottomleftLabel = $this->labelPreparation($this->bottomleftOriginal);
    $bottomcenterLabel = $this->labelPreparation($this->bottomcenterOriginal);
    $bottomrightLabel = $this->labelPreparation($this->bottomrightOriginal);

    //dpm($topleftLabel);
    //dpm($topcenterLabel);

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
      '#title' => t('<h3>Annotations of Container ' . $path . '</h3>'),
    ];
    $form['subtitle'] = [
      '#type' => 'item',
      '#title' => t('<h4>Annotations maintained by <font color="DarkGreen">' . $username . ' (' . $uemail . ')</font></h4>'),
    ];
    $form['header'] = [
      '#type' => 'item',
      '#title' => t('Header'),
    ];
    if ($this->getContainer()->hascoTypeUri == VSTOI::INSTRUMENT) {
      $form['annotation_topleft'] = [
        '#type' => 'textfield',
        '#title' => $this->t('PageTopLeft'),
        '#default_value' => $topleftLabel,
        '#autocomplete_route_name' => 'sir.annotation_autocomplete',
        // '#autocomplete_route_parameters' => [
        //   'containeruri' => base64_encode($this->getContainer()->uri), // Encode if needed
        //   'manageremail' => \base64_encode($uemail), // Get current user's email
        // ],
      ];
      $form['annotation_topcenter'] = [
        '#type' => 'textfield',
        '#title' => $this->t('PageTopCenter'),
        '#default_value' => $topcenterLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_topright'] = [
        '#type' => 'textfield',
        '#title' => $this->t('PageTopRight'),
        '#default_value' => $toprightLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_linebelowtop'] = [
        '#type' => 'textfield',
        '#title' => $this->t('PageLineBelowTop'),
        '#default_value' => $linebelowtopLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['footer'] = [
        '#type' => 'item',
        '#title' => t('<br>Footer'),
      ];
      $form['annotation_lineabovebottom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('PageLineAboveBottom'),
        '#default_value' => $lineabovebottomLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_bottomleft'] = [
        '#type' => 'textfield',
        '#title' => $this->t('PageBottomLeft'),
        '#default_value' => $bottomleftLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_bottomcenter'] = [
        '#type' => 'textfield',
        '#title' => $this->t('PageBottomCenter'),
        '#default_value' => $bottomcenterLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_bottomright'] = [
        '#type' => 'textfield',
        '#title' => $this->t('PageBottomRight'),
        '#default_value' => $bottomrightLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
    } else {
      $form['annotation_topleft'] = [
        '#type' => 'textfield',
        '#title' => $this->t('TopLeft'),
        '#default_value' => $topleftLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_topcenter'] = [
        '#type' => 'textfield',
        '#title' => $this->t('TopCenter'),
        '#default_value' => $topcenterLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_topright'] = [
        '#type' => 'textfield',
        '#title' => $this->t('TopRight'),
        '#default_value' => $toprightLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_linebelowtop'] = [
        '#type' => 'textfield',
        '#title' => $this->t('LineBelowTop'),
        '#default_value' => $linebelowtopLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['footer'] = [
        '#type' => 'item',
        '#title' => t('<br>Footer'),
      ];
      $form['annotation_lineabovebottom'] = [
        '#type' => 'textfield',
        '#title' => $this->t('LineAboveBottom'),
        '#default_value' => $lineabovebottomLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_bottomleft'] = [
        '#type' => 'textfield',
        '#title' => $this->t('BottomLeft'),
        '#default_value' => $bottomleftLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_bottomcenter'] = [
        '#type' => 'textfield',
        '#title' => $this->t('BottomCenter'),
        '#default_value' => $bottomcenterLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
      $form['annotation_bottomright'] = [
        '#type' => 'textfield',
        '#title' => $this->t('BottomRight'),
        '#default_value' => $bottomrightLabel,
        '#autocomplete_route_name' => 'sir.annotation_stem_autocomplete',
      ];
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#name' => 'save',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'save-button'],
      ],
    ];
    $form['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#name' => 'back',
      '#attributes' => [
        'class' => ['btn', 'btn-primary', 'back-button'],
      ],
    ];
    $form['bottom_space'] = [
      '#type' => 'item',
      '#title' => t('<br><br>'),
    ];

    return $form;
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

    if ($annotation == NULL ||
        $annotation->uri == NULL || $annotation->uri == "" ||
        $annotation->annotationStem == NULL ||
        $annotation->annotationStem->hasContent == NULL ||
        $annotation->annotationStem->hasContent == "") {
      return "";
    }

    return Utils::trimAutoCompleteString($annotation->annotationStem->hasContent,$annotation->annotationStem->uri);
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

    // SAVE
    if ($button_name === 'save') {

      $msg = "";

      // SAVE CONTAINER'S ANNOTATIONS
      if ($this->getContainer()->hascoTypeUri == VSTOI::INSTRUMENT) {
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_topleft'),
          $this->topleftOriginal,
          VSTOI::PAGE_TOP_LEFT,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_topcenter'),
          $this->topcenterOriginal,
          VSTOI::PAGE_TOP_CENTER,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_topright'),
          $this->toprightOriginal,
          VSTOI::PAGE_TOP_RIGHT,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_linebelowtop'),
          $this->linebelowtopOriginal,
          VSTOI::PAGE_LINE_BELOW_TOP,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_lineabovebottom'),
          $this->lineabovebottomOriginal,
          VSTOI::PAGE_LINE_ABOVE_BOTTOM,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_bottomleft'),
          $this->bottomleftOriginal,
          VSTOI::PAGE_BOTTOM_LEFT,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_bottomcenter'),
          $this->bottomcenterOriginal,
          VSTOI::PAGE_BOTTOM_CENTER,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_bottomright'),
          $this->bottomrightOriginal,
          VSTOI::PAGE_BOTTOM_RIGHT,
          $form_state);
      } else {
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_topleft'),
          $this->topleftOriginal,
          VSTOI::TOP_LEFT,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_topcenter'),
          $this->topcenterOriginal,
          VSTOI::TOP_CENTER,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_topright'),
          $this->toprightOriginal,
          VSTOI::TOP_RIGHT,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_linebelowtop'),
          $this->linebelowtopOriginal,
          VSTOI::LINE_BELOW_TOP,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_lineabovebottom'),
          $this->lineabovebottomOriginal,
          VSTOI::LINE_ABOVE_BOTTOM,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_bottomleft'),
          $this->bottomleftOriginal,
          VSTOI::BOTTOM_LEFT,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_bottomcenter'),
          $this->bottomcenterOriginal,
          VSTOI::BOTTOM_CENTER,
          $form_state);
        $msg .= $this->saveAnnotation(
          $form_state->getValue('annotation_bottomright'),
          $this->bottomrightOriginal,
          VSTOI::BOTTOM_RIGHT,
          $form_state);
      }

      if ($msg != "") {
        \Drupal::messenger()->addMessage(t($msg));
      }
      $this->backToSlotElement($form_state);
    }

    // BACK TO MAIN PAGE
    if ($button_name === 'back') {
      $this->backToSlotElement($form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  private function saveAnnotation($newValue, $original, $position, FormStateInterface $form_state) {

    $api = \Drupal::service('rep.api_connector');

    if (($newValue == NULL || $newValue == "") && $original == NULL) {
      return "";
    }

    $annotationStemUri = Utils::uriFromAutocomplete($newValue);

    if ($original == NULL) {

      // ADD NEW ANNOTATION
      try {

        $belongsTo = $this->getContainer()->uri;
        $useremail = \Drupal::currentUser()->getEmail();

        // CREATE A NEW ANNOTATION
        $newAnnotationUri = Utils::uriGen('annotation');
        $annotationJson = '{"uri":"'.$newAnnotationUri.'",'.
          '"typeUri":"'.VSTOI::ANNOTATION.'",'.
          '"hascoTypeUri":"'.VSTOI::ANNOTATION.'",'.
          '"hasAnnotationStem":"'.$annotationStemUri.'",'.
          '"hasPosition":"'.$position.'",'.
          '"belongsTo":"'.$belongsTo.'",'.
          '"hasSIRManagerEmail":"'.$useremail.'"}';
        $api->annotationAdd($annotationJson);

        return "Annotation added for ".Utils::namespaceUri($position).". ";

      } catch(\Exception $e) {
        \Drupal::messenger()->addError(t("An error occurred while adding the Annotation: ".$e->getMessage()));
        $this->backToSlotElement($form_state);
      }

    } else {

      // UPDATE EXISTING ANNOTATION

      // UPDATE IF CURRENT AND ORIGINAL ANNOTATION STEM URIS ARE DIFFERENT
      if ($annotationStemUri != $original->annotationStem->uri) {

        // DELETE EXISTING ANNOTATION
        if (($annotationStemUri == NULL || $annotationStemUri == "") &&
            ($original->annotationStem->uri != NULL && $original->annotationStem->uri != "")) {

          try {

            // DELETE ANNOTATION
            $api = \Drupal::service('rep.api_connector');
            $api->annotationDel($original->uri);
            return "Annotation deleted for ".Utils::namespaceUri($position).". ";

          } catch(\Exception $e){
            \Drupal::messenger()->addError(t("An error occurred while updating the Annotation: ".$e->getMessage()));
            $this->backToSlotElement($form_state);
          }
        } else {

          try {

            $uid = \Drupal::currentUser()->id();
            $useremail = \Drupal::currentUser()->getEmail();

            $annotationJson = '{"uri":"'.$original->uri.'",'.
              '"typeUri":"'.VSTOI::ANNOTATION.'",'.
              '"hascoTypeUri":"'.VSTOI::ANNOTATION.'",'.
              '"hasAnnotationStem":"'.$annotationStemUri.'",'.
              '"hasPosition":"'.$position.'",'.
              '"hasContentWithStyle":"'.$original->hasContentWithStyle.'",'.
              '"comment":"'.$original->comment.'",'.
              '"belongsTo":"'.$original->belongsTo.'",'.
              '"hasSIRManagerEmail":"'.$useremail.'"}';
            //dpm($annotationJson);

            // UPDATE BY DELETING AND CREATING
            $api = \Drupal::service('rep.api_connector');
            $api->annotationDel($original->uri);
            $updatedAnnotation = $api->annotationAdd($annotationJson);
            return "Annotation updated for ".Utils::namespaceUri($position).". ";

          } catch(\Exception $e){
            \Drupal::messenger()->addError(t("An error occurred while updating the Annotation: ".$e->getMessage()));
            $this->backToSlotElement($form_state);
          }

        }
      }
    }
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

}
