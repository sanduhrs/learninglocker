<?php namespace Models;

use \Jenssegers\Mongodb\Model as Model;

class Export extends Model {
  protected $collection = 'exports';
  protected $fillable = ['name', 'description', 'map', 'authority'];
  protected $hidden = ['authority'];
}
