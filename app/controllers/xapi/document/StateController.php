<?php namespace Controllers\XAPI\Document;

class StateController extends BaseController {
  protected static $document_identifier = 'stateId';
  protected static $document_repo = '\Repos\Document\State\EloquentRepository';
  protected static $document_type = 'state';
}

