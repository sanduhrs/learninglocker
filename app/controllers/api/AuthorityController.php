<?php namespace Controllers\API;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;
use \Repos\Authority\EloquentRepository as AuthorityRepository;

class AuthorityController extends BaseController {
  /**
   * GETs all of the Authorities available to currently authenticated Authority.
   * @return \Illuminate\Http\JsonResponse
   */
  public function index() {
    return IlluminateResponse::json((new AuthorityRepository)->index(
      $this->getAuthority()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Stores/creates (POSTs) a sub-authority of the currently authenticated Authority.
   * @return \Illuminate\Http\JsonResponse
   */
  public function store() {
    return IlluminateResponse::json((new AuthorityRepository)->store(
      $this->getAuthority(),
      LockerRequest::getParams()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Gets a single Authority using the id in the url.
   * The Authority must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id) {
    return IlluminateResponse::json((new AuthorityRepository)->show(
      $this->getAuthority(),
      $id
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Updates a single Authority using the id in the url and the data in the form.
   * The Authority must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function update($id) {
    return IlluminateResponse::json((new AuthorityRepository)->update(
      $this->getAuthority(),
      $id,
      LockerRequest::getParams()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Deletes a single Authority using the id in the url.
   * The Authority must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\ResponseTrait
   */
  public function destroy($id) {
    (new AuthorityRepository)->destroy($this->getAuthority(), $id);
    return IlluminateResponse::make('', 204, $this->getCORSHeaders());
  }
}
