<?php namespace Controllers\XAPI\Document;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;

class StateController extends BaseController {
  protected static $document_identifier = 'stateId';
  protected static $document_repo = '\Repos\Document\State\EloquentRepository';
  protected static $document_type = 'state';

  public function destroy() {
    $single_delete = LockerRequest::hasParam($this->identifier);

    (new static::$document_repo)->destroy(
      $authority,
      $data ?: []
    );

    return IlluminateResponse::json(null, 204);
  }
}

