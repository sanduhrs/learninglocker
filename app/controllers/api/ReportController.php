<?php namespace Controllers\API;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;
use \Repos\Report\EloquentRepository as ReportRepository;

class ReportController extends BaseController {
  /**
   * GETs all of the Reports available to currently authenticated Authority.
   * @return \Illuminate\Http\JsonResponse
   */
  public function index() {
    return IlluminateResponse::json((new ReportRepository)->index(
      $this->getAuthority()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Stores/creates (POSTs) a Report for the currently authenticated Authority.
   * @return \Illuminate\Http\JsonResponse
   */
  public function store() {
    return IlluminateResponse::json((new ReportRepository)->store(
      $this->getAuthority(),
      LockerRequest::getParams()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Gets a single Report using the id in the url.
   * The Report must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id) {
    return IlluminateResponse::json((new ReportRepository)->show(
      $this->getAuthority(),
      $id
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Updates a single Report using the id in the url and the data in the form.
   * The Report must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function update($id) {
    return IlluminateResponse::json((new ReportRepository)->update(
      $this->getAuthority(),
      $id,
      LockerRequest::getParams()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Deletes a single Report using the id in the url.
   * The Report must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\ResponseTrait
   */
  public function destroy($id) {
    (new ReportRepository)->destroy($this->getAuthority(), $id);
    return IlluminateResponse::make('', 204, $this->getCORSHeaders());
  }
}
