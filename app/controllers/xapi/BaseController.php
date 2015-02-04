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

  /**
   * Selects which method should be run for the given request.
   * @return \Illuminate\Http\ResponseTrait Result of the method.
   */
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
    } catch (NoAuthException $ex) {
      return $this->errorResponse($ex, 401);
    } catch (\Exception $ex) {
      return $this->errorResponse($ex);
    }
  }

  /**
   * Gets the method from the request.
   * @return String Method (i.e. PUT/POST/etc).
   */
  protected function getMethod() {
    return LockerRequest::getParam(
      'method',
      IlluminateRequest::server('REQUEST_METHOD')
    );
  }

  /**
   * Checks that the xAPI version header is valid.
   */
  protected function checkVersion() {
    $version = new XAPIVersion(LockerRequest::header('X-Experience-API-Version'));
    Helpers::validateAtom($version, 'X-Experience-API-Version');
  }
}

