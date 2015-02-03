<?php namespace Controllers\XAPI;

use \IlluminateResponse as IlluminateResponse;
use \IlluminateRequest as IlluminateRequest;
use \Controllers\API\BaseController as APIController;
use \LockerRequest as LockerRequest;
use \Locker\XApi\Version as XAPIVersion;
use \Helpers\Exceptions\NoAuth as NoAuthException;
use \Helpers\Helpers as Helpers;

abstract class BaseController extends APIController {
  abstract protected function get();
  abstract protected function update();
  abstract protected function store();
  abstract protected function destroy();

  public function selectMethod() {
    try {
      $this->checkVersion();
      switch ($this->getMethod()) {
        case 'HEAD':
        case 'GET': return $this->get();
        case 'PUT': return $this->update();
        case 'POST': return $this->store();
        case 'DELETE': return $this->destroy();
      }
    } catch (NoAuthException $e) {
      return IlluminateResponse::json([
        'message' => $e->getMessage(),
        'trace' => $e->getTrace()
      ], 401, $this->getCORSHeaders());
    } catch (\Exception $e) {
      return IlluminateResponse::json([
        'message' => $e->getMessage(),
        'trace' => $e->getTrace()
      ], 400, $this->getCORSHeaders());
    }
  }

  protected function getMethod() {
    return LockerRequest::getParam(
      'method',
      IlluminateRequest::server('REQUEST_METHOD')
    );
  }

  protected function checkVersion() {
    $version = new XAPIVersion(LockerRequest::header('X-Experience-API-Version'));
    Helpers::validateAtom($version);
  }
}

