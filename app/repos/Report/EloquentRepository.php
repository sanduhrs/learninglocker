<?php namespace Repos\Report;

use \Models\Report as Report;
use \Models\Authority as Authority;
use \Helpers\Exceptions\NotFound as NotFoundException;
use \Locker\XApi\Helpers as XAPIHelpers;

interface Repository {
  public function index(Authority $authority);
  public function store(Authority $authority, array $data);
  public function show(Authority $authority, $id);
  public function update(Authority $authority, $id, array $data);
  public function destroy(Authority $authority, $id);
}

class EloquentRepository implements Repository {

  /**
   * Constructs a query restricted to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @return \Jenssegers\Mongodb\Eloquent\Builder
   */
  private function where(Authority $authority) {
    return Report::where(
      'authority',
      'like',
      $authority->homePage.'%'
    );
  }

  /**
   * Gets all of the reports accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @return [Authority]
   */
  public function index(Authority $authority) {
    return $this->where($authority)->get()->toArray();
  }

  /**
   * Gets the report with the given ID if it's accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @return Authority
   */
  public function show(Authority $authority, $id) {
    $shown_report = $this
      ->where($authority)
      ->where('_id', $id)
      ->first();

    if ($shown_report === null) throw new NotFoundException($id, 'Report');

    return $shown_report;
  }

  /**
   * Creates a new report.
   * @param Authority $authority The Authority to restrict with.
   * @param [String => mixed] $data Properties of the new authority.
   * @return Authority
   */
  public function store(Authority $authority, array $data) {
    $data['authority'] = $authority->homePage;

    // Validates data.
    XAPIHelpers::checkType('name', 'string', $data['name']);
    XAPIHelpers::checkType('description', 'string', $data['description']);
    XAPIHelpers::checkType('query', 'array', $data['query']);
    XAPIHelpers::checkType('authority', 'string', $data['authority']);

    $report = new Report($data);
    $report->save();
    return $report;
  }

  /**
   * Updates an existing report.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @param [String => mixed] $data Properties to be updated.
   * @return Authority
   */
  public function update(Authority $authority, $id, array $data) {
    $report = $this->show($authority, $id);
    $data['authority'] = $authority->homePage;

    // Validates data.
    if (isset($data['name'])) XAPIHelpers::checkType('name', 'string', $data['name']);
    if (isset($data['description'])) XAPIHelpers::checkType('description', 'string', $data['description']);
    if (isset($data['query'])) XAPIHelpers::checkType('query', 'array', $data['query']);
    if (isset($data['authority'])) XAPIHelpers::checkType('authority', 'string', $data['authority']);

    $report->update($data);
    return $report;
  }

  /**
   * Destroys the report with the given ID if it's accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @return Boolean
   */
  public function destroy(Authority $authority, $id) {
    $report = $this->show($authority, $id);
    return $report->delete();
  }
}
