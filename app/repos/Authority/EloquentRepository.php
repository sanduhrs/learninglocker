<?php namespace Repos\Authority;

use \Models\Authority as Authority;

interface Repository {
  public function index(Authority $authority);
  public function store(Authority $authority, $data);
  public function show(Authority $authority, $id);
  public function showFromBasicAuth($key, $secret);
  public function update(Authority $authority, $id, $data);
  public function destroy(Authority $authority, $id);
}

class EloquentRepository implements Repository {
  /**
   * Constructs a query restricted to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @return \Jenssegers\Mongodb\Eloquent\Builder
   */
  private function where(Authority $authority) {
    return Authority::where(
      'homePage',
      'like',
      $authority->homePage.'%'
    );
  }

  /**
   * Gets all of the authorities accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @return [Authority]
   */
  public function index(Authority $authority) {
    return $this->where($authority)->get()->toArray();
  }

  /**
   * Gets the authority with the given ID if it's accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @return Authority
   */
  public function show(Authority $authority, $id) {
    return $this
      ->where($authority)
      ->where('_id', $id)
      ->first();
  }

  /**
   * Creates a new authority.
   * @param Authority $authority The Authority to restrict with.
   * @param AssocArray $data Properties of the new authority.
   * @return Authority
   */
  public function store(Authority $authority, $data) {

  }

  /**
   * Gets the authority with the given username and password.
   * @param String $username Username to match.
   * @param String $password Password to match.
   * @return Authority
   */
  public function showFromBasicAuth($username, $password) {
    return Authority::where('credentials.username', $username)
      ->where('credentials.password', $password)
      ->where('auth', 'basic')
      ->first();
  }

  /**
   * Updates an existing authority.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @param AssocArray $data Properties to be updated.
   * @return Authority
   */
  public function update(Authority $authority, $id, $data) {

  }

  /**
   * Destroys the authority with the given ID if it's accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @return Boolean
   */
  public function destroy(Authority $authority, $id) {

  }
}
