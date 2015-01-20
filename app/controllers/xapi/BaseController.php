<?php namespace Controllers\XAPI;

use \Illuminate\Http\Response as IlluminateResponse;
use \Illuminate\Http\Request as IlluminateRequest;
use \Controllers\API\BaseController as APIController;
use \locker\Request as LockerRequest;

abstract class BaseController extends APIController {
  abstract protected function get();
  abstract protected function update();
  abstract protected function store();
  abstract protected function destroy();

  public function selectMethod() {
    try {
      switch ($this->getMethod()) {
        case 'HEAD':
        case 'GET': return $this->get();
        case 'PUT': return $this->update();
        case 'POST': return $this->store();
        case 'DELETE': return $this->destroy();
      }
    } catch (\Exception $e) {
      return IlluminateResponse::json([
        'message' => $e->getMessage(),
        'trace' => $e->getTrace()
      ], 400);
    }
  }

  private function getMethod() {
    return LockerRequest::getParam(
      'method',
      IlluminateRequest::server('REQUEST_METHOD')
    );
  }
}

