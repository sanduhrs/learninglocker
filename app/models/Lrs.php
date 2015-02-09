<?php namespace Models;

use \Jenssegers\Mongodb\Model as Model;

class Lrs extends Model {
  protected $collection = 'lrs';
  protected $fillable = ['name', 'description', 'authority'];
  protected $hidden = ['authority'];
}
