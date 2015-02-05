<?php namespace Models;

use \Jenssegers\Mongodb\Model as Model;

class Authority extends Model {
  protected $collection = 'authorities';
  protected $fillable = ['name', 'homePage', 'description', 'auth', 'credentials'];
  protected $hidden = ['_id'];

  /**
   * Gets the actor associated with the Authority.
   * @return \stdClass Actor.
   */
  public function getActor() {
    return (object) [
      'account' => (object) [
        'name' => $this->name,
        'homePage' => $this->homePage
      ]
    ];
  }
}
