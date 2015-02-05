<?php namespace Controllers\XAPI\Document;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;

class AgentProfileController extends BaseController {
  protected static $document_identifier = 'profileId';
  protected static $document_repo = '\Repos\Document\AgentProfile\EloquentRepository';
  protected static $document_type = 'agentProfile';

  /**
   * Constructs a Person that fulfils the given parameters.
   * https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md#combined-information-get
   * @return \Illuminate\Http\JsonResponse Person represented in JSON.
   */
  public function search() {
    return IlluminateResponse::json((new static::$document_repo)->getPerson(
      $this->getAuthority(),
      LockerRequest::getParams()
    ), 200, $this->getCORSHeaders());
  }
}

