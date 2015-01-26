<?php namespace Helpers\Exceptions;

class NotFound extends \Exception {
  public function __construct($id, $class) {
    parent::__construct("Could not find $class with id $id.");
  }
}

class ConflictException extends \Exception {}