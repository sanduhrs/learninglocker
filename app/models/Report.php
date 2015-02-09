<?php namespace Models;

use \Jenssegers\Mongodb\Model as Model;

class Report extends Model {
  protected $collection = 'reports';
  protected $fillable = ['name', 'description', 'query', 'authority'];
  protected $hidden = ['authority'];
}
