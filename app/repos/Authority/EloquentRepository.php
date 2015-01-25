<?php namespace Repos\Authority;

use \Models\Authority as Authority;

interface Repository {
  public function index();
  public function store();
  public function show($id);
  public function showFromBasicAuth($key, $secret);
  public function update($id);
  public function destroy($id);
}

class EloquentRepository implements Repository {
  public function index() {

  }

  public function store() {

  }

  public function show($id) {

  }

  public function showFromBasicAuth($key, $secret) {
    return Authority::where('credentials.key', $key)
      ->where('credentials.secret', $secret)
      ->where('auth', 'basic')
      ->first();
  }

  public function update($id) {

  }

  public function destroy($id) {

  }
}
