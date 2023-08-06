<?php

namespace Drupal\sir\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\sir\BrowseListPage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'List Pager' Block.
 *
 * @Block(
 *   id = "list_pager_block",
 *   admin_label = @Translation("List Pager block"),
 *   category = @Translation("HADatAc"),
 *   context = {
 *     "node" = @ContextDefinition(
 *       "entity:node",
 *       label = @Translation("Current Node"),
 *       required = FALSE
 *     )
 *   }
 * )
 */
class ListPagerBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;
  
  /**
   * The uri parameter of the http request.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $uri;
  
  /**
   * The content of current list.
   *
   * @var array()
   */
  protected $list;

  protected $classuri;
  
  protected $page;
  
  protected $pagesize;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match, $classuri, $page, $pagesize, $list) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->classuri = $classuri;
    $this->page = $page;
    $this->pagesize = $pagesize;
    $this->list = $list; 
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $service = \Drupal::service('hadatac.new_list');
    $route_match = $container->get('current_route_match');
    if (!isset($route_match)) {
    } else {
      $classuri_request = $route_match->getParameter('classuri');
      $page_request = $route_match->getParameter('page');
      $pagesize_request = $route_match->getParameter('pagesize');
    }
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $classuri_request,
      $page_request,
      $pagesize_request,
      $service->getList()
    );
  } 

  public function listHtml() {
    if ($this->list == NULL || count($this->list) <= 0) {
      return "[empty list]";
    }
    $resp = "<ul>";
    foreach ($this->list as $row) {
      $resp = $resp . "<li>" . $row . "</li>";
    }
    $resp = $resp . "</ul>";
    return $resp;
  }

  /**
    * {@inheritdoc}
    */
  public function build() {                                                   
    $build = [];
    $this->list = BrowseListPage::exec($this->classuri, $this->page, $this->pagesize);
    $build['#title'] = "List Pager";
    $build['content'] = [
      '#markup'  => $this->listHtml($this->list),                
    ];

    return $build;
  }

  public function getCacheMaxAge() {
    return 0;
  }

}
    