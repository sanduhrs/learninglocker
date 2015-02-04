<?php namespace Controllers\XAPI\Document;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;

class ActivityProfileController extends BaseController {
  protected static $document_identifier = 'profileId';
  protected static $document_repo = '\Repos\Document\ActivityProfile\EloquentRepository';
  protected static $document_type = 'activityProfile';

  /**
   * Gets the ActivityProfile that fulfils the given parameters in full JSON form.
   * @return \Illuminate\Http\JsonResponse ActivityProfile represented in JSON.
   */
  public function full() {
    return IlluminateResponse::json(
      (new static::$document_repo)->show($this->getAuthority(), LockerRequest::getParams()),
      200,
      $this->getCORSHeaders()
    );
  }
}

