<?php namespace Helpers\Exceptions;

class Conflict extends \Exception {}
class Precondition extends \Exception {}

class NotFound extends \Exception {
  public function __construct($id, $class) {
    parent::__construct(trans('api.errors.not_found', [
      'id' => $id,
      'class' => $class
    ]));
  }
}

class NoAuth extends \Exception {
  public function __construct() {
    parent::__construct(trans('api.errors.missing_auth'));
  }
}
