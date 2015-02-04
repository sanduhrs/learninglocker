<?php namespace Controllers\API;

use \Illuminate\Routing\Controller as IlluminateController;
use \IlluminateResponse as IlluminateResponse;
use \LockerRequest as LockerRequest;
use \Repos\Authority\EloquentRepository as AuthorityRepository;
use \Helpers\Helpers as Helpers;
use \Helpers\Exceptions\NoAuth as NoAuthException;
use \Exception as Exception;
use \Config as Config;

abstract class BaseController extends IlluminateController {
  /**
   * Gets the CORS Headers.
   * @return array CORS headers.
   */
  protected function getCORSHeaders() {
    return Helpers::getCORSHeaders();
  }

  /**
   * Gets and verifies the authority from the headers.
   * @return \Models\Authority Verified Authority.
   */
  protected function getAuthority() {
    $user = LockerRequest::getUser();
    $pass = LockerRequest::getPassword();
    $auth = LockerRequest::header('Authorization');

    if ($auth === null) throw new NoAuthException();
    if (!$this->isBase64(substr($auth, 6))) throw new \Exception(
      trans('api.errors.base64_auth')
    );

    try {
      return (new AuthorityRepository)->showFromBasicAuth($user, $pass);
    } catch (\Exception $ex) {
      throw new NoAuthException();
    }
  }

  /**
   * Determines if the given $value is a valid Base64 string.
   * @param string $value String to be verified.
   * @return boolean True if valid.
   */
  private function isBase64($value) {
    return base64_encode(base64_decode($value)) === $value;
  }

  /**
   * Constructs an error response.
   * @return \Illuminate\Http\JsonResponse Response containing the exception details.
   */
  protected function errorResponse(Exception $exception, $code = 400, $headers = []) {
    return IlluminateResponse::json([
      'message' => $exception->getMessage(),
      'trace' => Config::get('app.debug') ? $exception->getTrace() : '`debug` must be `true` in the App\'s config to get a trace.'
    ], $code, array_merge($this->getCORSHeaders(), $headers));
  }
}
