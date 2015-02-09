<?php namespace Repos\Export;

use \Models\Export as Export;
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
    return Export::where(
      'authority',
      'like',
      $authority->homePage.'%'
    );
  }

  /**
   * Gets all of the exports accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @return [Authority]
   */
  public function index(Authority $authority) {
    return $this->where($authority)->get()->toArray();
  }

  /**
   * Gets the export with the given ID if it's accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @return Authority
   */
  public function show(Authority $authority, $id) {
    $shown_export = $this
      ->where($authority)
      ->where('_id', $id)
      ->first();

    if ($shown_export === null) throw new NotFoundException($id, 'Export');

    return $shown_export;
  }

  /**
   * Creates a new export.
   * @param Authority $authority The Authority to restrict with.
   * @param [String => mixed] $data Properties of the new authority.
   * @return Authority
   */
  public function store(Authority $authority, array $data) {
    $data['authority'] = $authority->homePage;
    $data['map'] = (object) $data['map'];

    // Validates data.
    XAPIHelpers::checkType('name', 'string', $data['name']);
    XAPIHelpers::checkType('description', 'string', $data['description']);
    XAPIHelpers::checkType('map', 'stdClass', $data['map']);
    XAPIHelpers::checkType('authority', 'string', $data['authority']);

    $export = new Export($data);
    $export->save();
    return $export;
  }

  /**
   * Updates an existing export.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @param [String => mixed] $data Properties to be updated.
   * @return Authority
   */
  public function update(Authority $authority, $id, array $data) {
    $export = $this->show($authority, $id);
    $data['authority'] = $authority->homePage;

    // Validates data.
    if (isset($data['name'])) XAPIHelpers::checkType('name', 'string', $data['name']);
    if (isset($data['description'])) XAPIHelpers::checkType('description', 'string', $data['description']);
    if (isset($data['map'])) {
      $data['map'] = (object) $data['map'];
      XAPIHelpers::checkType('map', 'stdClass', $data['map']);
    }
    if (isset($data['authority'])) XAPIHelpers::checkType('authority', 'string', $data['authority']);

    $export->update($data);
    return $export;
  }

  /**
   * Destroys the export with the given ID if it's accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @return Boolean
   */
  public function destroy(Authority $authority, $id) {
    $export = $this->show($authority, $id);
    return $export->delete();
  }
}
