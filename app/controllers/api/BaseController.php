<?php namespace Controllers\API;

use \Illuminate\Routing\Controller as IlluminateController;
use \LockerRequest as LockerRequest;
use \Repos\Authority\EloquentRepository as AuthorityRepository;
use \Helpers\Helpers as Helpers;
use \Helpers\Exceptions\NoAuth as NoAuthException;

abstract class BaseController extends IlluminateController {
  protected function getCORSHeaders() {
    return Helpers::getCORSHeaders();
  }

  protected function getAuthority() {
    $user = LockerRequest::getUser();
    $pass = LockerRequest::getPassword();

    if ($user === null || $pass === null) throw new NoAuthException();
    if (!$this->isBase64(LockerRequest::header('Authorization'))) throw new \Exception(
      'Authorization details should be Base 64.'
    );

    return (new AuthorityRepository)->showFromBasicAuth(
      LockerRequest::getUser(),
      LockerRequest::getPassword()
    );
  }

  private function isBase64($value) {
    return base64_encode(base64_decode($value)) === $value;
  }
}
