<?php namespace Controllers\API;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;
use \Repos\Lrs\EloquentRepository as LrsRepository;

class LrsController extends BaseController {
  /**
   * GETs all of the Lrss available to currently authenticated Authority.
   * @return \Illuminate\Http\JsonResponse
   */
  public function index() {
    return IlluminateResponse::json((new LrsRepository)->index(
      $this->getAuthority()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Stores/creates (POSTs) a Lrs for the currently authenticated Authority.
   * @return \Illuminate\Http\JsonResponse
   */
  public function store() {
    return IlluminateResponse::json((new LrsRepository)->store(
      $this->getAuthority(),
      LockerRequest::getParams()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Gets a single Lrs using the id in the url.
   * The Lrs must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id) {
    return IlluminateResponse::json((new LrsRepository)->show(
      $this->getAuthority(),
      $id
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Updates a single Lrs using the id in the url and the data in the form.
   * The Lrs must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function update($id) {
    return IlluminateResponse::json((new LrsRepository)->update(
      $this->getAuthority(),
      $id,
      LockerRequest::getParams()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Deletes a single Lrs using the id in the url.
   * The Lrs must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\ResponseTrait
   */
  public function destroy($id) {
    (new LrsRepository)->destroy($this->getAuthority(), $id);
    return IlluminateResponse::make('', 204, $this->getCORSHeaders());
  }
}
