<?php namespace Repos\Authority;

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

  }

  public function update($id) {

  }

  public function destroy($id) {

  }
}