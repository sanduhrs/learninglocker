<?php namespace Controllers\XAPI\Document;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;

class ActivityProfileController extends BaseController {
  protected static $document_identifier = 'profileId';
  protected static $document_repo = '\Repos\Document\ActivityProfile\EloquentRepository';
  protected static $document_type = 'activityProfile';

  public function full() {
    return IlluminateResoonse::json(
      (new static::$document_repo)->show($this->getAuthority(), LockerRequest::getParams())
    );
  }
}

