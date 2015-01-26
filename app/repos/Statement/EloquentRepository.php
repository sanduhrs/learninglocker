<?php namespace Repos\Statement;

use \Models\Authority as Authority;

interface Repository {
  public function aggregate(Authority $authority, array $pipeline);
  public function count(Authority $authority);
  public function index(Authority $authority, array $options);
  public function show(Authority $authority, $id, $voided = false, $active = true);
  public function store(Authority $authority, array $statements, array $attachments);
}

class EloquentRepository implements Repository {
  public function aggregate(Authority $authority, array $pipeline) {
    return (new EloquentGetter)->aggregate($authority, $pipeline);
  }

  public function count(Authority $authority) {
    return (new EloquentGetter)->count($authority);
  }

  public function index(Authority $authority, array $options) {
    return (new EloquentGetter)->index($authority, $options);
  }

  public function show(Authority $authority, $id, $voided = false, $active = true) {
    return (new EloquentGetter)->show($authority, $id, $voided, $active);
  }

  public function store(Authority $authority, array $statements, array $attachments) {
    return (new EloquentStorer)->store($statements, $authority, $attachments);
  }
}
