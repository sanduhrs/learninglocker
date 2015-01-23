<?php namespace Model;

use \Jenssegers\Mongodb\Model as DBModel;

class Statement extends DBModel {
  protected $collection = 'statements';
}