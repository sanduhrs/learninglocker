<?php namespace Repos\Authority;

use \Models\Authority as Authority;
use \Helpers\Helpers as Helpers;
use \Helpers\Exceptions\NotFound as NotFoundException;

interface Repository {
  public function index(Authority $authority);
  public function store(Authority $authority, array $data);
  public function show(Authority $authority, $id);
  public function showFromBasicAuth($username, $password);
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
    $shown_authority = $this
      ->where($authority)
      ->where('_id', $id)
      ->first();

    if ($shown_authority === null) throw new NotFoundException('Authority', $id);

    return $shown_authority;
  }

  /**
   * Creates a new authority.
   * @param Authority $authority The Authority to restrict with.
   * @param [String => mixed] $data Properties of the new authority.
   * @return Authority
   */
  public function store(Authority $authority, array $data) {
    // Validates the given auth type.
    $acceptable_auths = ['basic'];
    if (isset($data['auth']) && in_array($data['auth'], $acceptable_auths)) throw new \Exception(
      trans('api.errors.auth_type', [
        'types' => implode(', ', $acceptable_auths),
        'auth' => $data['auth']
      ])
    );

    // Creates a new authority.
    $new_authority = new Authority($data);
    $new_authority->save();

    // Constructs the property for the new authority.
    $name = (string) $new_authority->_id;
    $data = array_merge($data, [
      'name' => $name,
      'homePage' => $authority->homePage.'/'.$name,
      'auth' => 'basic',
      'description' => ''
    ]);

    // Generates credentials.
    switch ($data['auth']) {
      default: $data['credentials'] = $this->createBasicAuth();
    }

    // Updates the Authority.
    $new_authority->update($data);
    return $new_authority;
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
   * @param [String => mixed] $data Properties to be updated.
   * @return Authority
   */
  public function update(Authority $authority, $id, array $data) {
    $updated_authority = $this->show($authority, $id);
    $updated_authority->update($data);
    return $updated_authority;
  }

  /**
   * Destroys the authority with the given ID if it's accessible to the given authority.
   * @param Authority $authority The Authority to restrict with.
   * @param String $id ID to match.
   * @return Boolean
   */
  public function destroy(Authority $authority, $id) {
    $updated_authority = $this->show($authority, $id);
    return $updated_authority->delete();
  }

  /**
   * Creates basic auth credentials.
   * @return [String => String]
   */
  private function createBasicAuth() {
    return [
      'username' => Helpers::getRandomSha(),
      'password' => Helpers::getRandomSha()
    ];
  }
}
