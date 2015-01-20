<?php namespace Controllers\API;

use \locker\Request as LockerRequest;

class StatementController extends BaseController {
  public function aggregate() {
    $pipeline = json_decode(
      LockerRequest::getParam('pipeline'),
      true
    ) ?: [['$match' => []]];
    return \Response::json(StatementRepository::aggregate($this->getAuthority(), $pipeline)); 
  }
}