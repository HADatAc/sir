<?php

namespace Drupal\sir\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Config\ConfigFactoryInterface;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new YourModuleRouteSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  protected function alterRoutes(RouteCollection $collection) {

    $config = $this->configFactory->get('sir.settings');
    $sir_home = $config->get('sir_home');
    $preferred_instrument = \Drupal::config('rep.settings')->get('preferred_instrument');
    $preferred_detector = \Drupal::config('rep.settings')->get('preferred_detector');

    if($sir_home == '1'){
      if ($route = $collection->get('view.frontpage.page_1')) {
        $route->setDefault('_controller', '\Drupal\sir\Controller\InitializationController::index');
      }
    }

    if ($route = $collection->get('sir.add_instrument')) {
      $route->setDefault('_title', 'Add ' . $preferred_instrument);
    }
    if ($route = $collection->get('sir.edit_instrument')) {
      $route->setDefault('_title', 'Edit ' . $preferred_instrument);
    }
    if ($route = $collection->get('sir.add_detectorstem')) {
      $route->setDefault('_title', 'Add ' . $preferred_detector . ' Stem');
    }
    if ($route = $collection->get('sir.edit_detectorstem')) {
      $route->setDefault('_title', 'Edit ' . $preferred_detector . ' Stem');
    }
    if ($route = $collection->get('sir.add_detector')) {
      $route->setDefault('_title', 'Add ' . $preferred_detector);
    }
    if ($route = $collection->get('sir.add_containerslot_detector')) {
      $route->setDefault('_title', 'Add ' . $preferred_detector);
    }
    if ($route = $collection->get('sir.edit_detector')) {
      $route->setDefault('_title', 'Edit ' . $preferred_detector);
    }
  }

}
