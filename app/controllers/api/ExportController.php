<?php namespace Controllers\API;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;
use \Repos\Export\EloquentRepository as ExportRepository;

class ExportController extends BaseController {
  /**
   * GETs all of the Exports available to currently authenticated Authority.
   * @return \Illuminate\Http\JsonResponse
   */
  public function index() {
    return IlluminateResponse::json((new ExportRepository)->index(
      $this->getAuthority()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Stores/creates (POSTs) a Export for the currently authenticated Authority.
   * @return \Illuminate\Http\JsonResponse
   */
  public function store() {
    return IlluminateResponse::json((new ExportRepository)->store(
      $this->getAuthority(),
      LockerRequest::getParams()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Gets a single Export using the id in the url.
   * The Export must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id) {
    return IlluminateResponse::json((new ExportRepository)->show(
      $this->getAuthority(),
      $id
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Updates a single Export using the id in the url and the data in the form.
   * The Export must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function update($id) {
    return IlluminateResponse::json((new ExportRepository)->update(
      $this->getAuthority(),
      $id,
      LockerRequest::getParams()
    ), 200, $this->getCORSHeaders());
  }

  /**
   * Deletes a single Export using the id in the url.
   * The Export must be accessible to the currently authenticated Authority.
   * @param String $id
   * @return \Illuminate\Http\ResponseTrait
   */
  public function destroy($id) {
    (new ExportRepository)->destroy($this->getAuthority(), $id);
    return IlluminateResponse::make('', 204, $this->getCORSHeaders());
  }
}
