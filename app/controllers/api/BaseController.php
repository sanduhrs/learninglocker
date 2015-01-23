<?php namespace Controllers\API;

use \Illuminate\Routing\Controller as IlluminateController;
use \IlluminateRequest as IlluminateRequest;
use \LockerRequest as LockerRequest;
use \Repos\Authority\EloquentRepository as AuthorityRepository;

abstract class BaseController extends IlluminateController {
  private function getCORSHeaders() {
    return [
      'Access-Control-Allow-Origin' => IlluminateRequest::root(),
      'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
      'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-Experience-API-Version, X-Experience-API-Consistent-Through, Updated',
      'Access-Control-Allow-Credentials' => 'true',
      'X-Experience-API-Consistent-Through' => Helpers::getCurrentDate()
    ];
  }

  protected function getAuthority() {
    return (new AuthorityRepository)->showFromBasicAuth(
      LockerRequest::getUser(),
      LockerRequest::getPassword()
    );
  }
}