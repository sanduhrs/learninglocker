<?php namespace Repos\Statement;

interface Repository {
  public function aggregate(Authority $authority, array $pipeline);
  public function index(Authority $authority, array $options);
  public function show(Authority $authority, $id, $voided = false, $active = true);
  public function store(Authority $authority, array $statements);
}

class EloquentRepository implements Repository {
  public function aggregate(Authority $authority, array $pipeline) {
    return (new EloquentStatementGetter)->aggregate($authority, $pipeline);
  }

  public function index(Authority $authority, array $options) {
    return (new EloquentStatementGetter)->index($authority, $options);
  }

  public function show(Authority $authority, $id, $voided = false, $active = true) {
    return (new EloquentStatementGetter)->show($authority, $id, $voided, $active);
  }

  public function store(Authority $authority, array $statements) {
    return (new EloquentStatementStorer)->store($authority);
  }
}