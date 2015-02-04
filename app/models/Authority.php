<?php namespace Models;

use \Jenssegers\Mongodb\Model as Model;

class Authority extends Model {
  protected $collection = 'authorities';

  /**
   * Gets the LRS associated with the Authority.
   * @return String LRS ID.
   */
  public function getLRS() {
    $home_page = explode('/', $this->actor['account']['homePage']);
    return array_pop($home_page);
  }

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
