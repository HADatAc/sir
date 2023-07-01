<?php
  namespace Drupal\sir\Controller;

  use Drupal\sir\BrowseListPage;

  class ListPageController2 {

    protected $list;

    protected $list_size;

    public function getList() {
      return $this->list;
    }

    public function setList($list) {
      return $this->list = $list; 
    }

    public function getListSize() {
      return $this->list_size;
    }

    public function setListSize($list_size) {
      return $this->list_size = $list_size; 
    }

    public function page($elementtype, $page, $pagesize) {

      if ($elementtype == "") {
        return array(
          '#theme' => 'list_page',
          '#items' => [],
        );
      }

      $this->setListSize(-1);
      if ($elementtype != NULL) {
        $this->setListSize(BrowseListPage::total($elementtype));
      }

      if (gettype($this->list_size) == 'string') {
        $total_pages = "0";
      } else { 
        if ($this->list_size % $pagesize == 0) {
          $total_pages = $this->list_size / $pagesize;
        } else {
          $total_pages = floor($this->list_size / $pagesize) + 1;
        }
      }

      if ($page < $total_pages) {
        $next_page = $page + 1;
        $next_page_link = BrowseListPage::link($elementtype, $next_page, $pagesize);
      } else {
        $next_page_link = '';
      }

      if ($page > 1) {
        $previous_page = $page - 1;
        $previous_page_link = BrowseListPage::link($elementtype, $previous_page, $pagesize);
      } else {
        $previous_page_link = '';
      }

      $class_name = "";
      switch ($elementtype) {
        case "detector":
          $class_name = "Detectors";
          break;
        case "instrument":
          $class_name = "Instruments";
          break;
        default:
          $class_name = "Objects of Unknown Types";
      }

      $response = array(
        '#theme' => 'list-page',
        '#items' => [
          'page' => strval($page),
          'first' => BrowseListPage::link($elementtype, 1, $pagesize),
          'last' => BrowseListPage::link($elementtype, $total_pages, $pagesize),
          'previous' => $previous_page_link,
          'next' => $next_page_link,
          'last_page' => strval($total_pages),
          'links' => null,
          'title' => '<br>List of ' . $class_name,
        ],
      );
      return $response;

    }
  }
