<?php namespace Model;

use \Jenssegers\Mongodb\Model as DBModel;

class Authority extends DBModel {
  protected $collection = 'authorities';
}