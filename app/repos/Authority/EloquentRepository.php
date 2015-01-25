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
  private function where(Authority $authority) {
    return Authority::where(
      'actor.account.homePage',
      'like',
      $authority->actor->account->homePage.'%'
    );
  }

  public function index(Authority $authority) {
    return $this->where($authority)->get();
  }

  public function store(Authority $authority, $data) {

  }

  public function show(Authority $authority, $id) {
    return $this
      ->where($authority)
      ->where('_id', $id)
      ->first();
  }

  public function showFromBasicAuth($key, $secret) {
    return Authority::where('credentials.key', $key)
      ->where('credentials.secret', $secret)
      ->where('auth', 'basic')
      ->first();
  }

  public function update(Authority $authority, $id, $data) {

  }

  public function destroy(Authority $authority, $id) {

  }
}
