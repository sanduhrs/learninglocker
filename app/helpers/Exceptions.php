<?php namespace Helpers\Exceptions;

class Conflict extends \Exception {}
class Precondition extends \Exception {}

class NotFound extends \Exception {
  public function __construct($id, $class) {
    parent::__construct("Could not find $class with id $id.");
  }
}

class NoAuth extends \Exception {
  public function __construct() {
    parent::__construct('Missing authorization.');
  }
}
