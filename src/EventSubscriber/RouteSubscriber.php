<?php

namespace Drupal\sir\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('view.frontpage.page_1')) {
      $route->setDefault('_controller', '\Drupal\sir\Controller\InstrumentController::index');
    }
  }
}