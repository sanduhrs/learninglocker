<?php namespace Models;

use \Jenssegers\Mongodb\Model as Model;

class Authority extends Model {
  protected $collection = 'authorities';

  public function getLRS() {
    $home_page = explode('/', $this->actor['account']['homePage']);
    return array_pop($home_page);
  }
}
