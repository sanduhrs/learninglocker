<?php namespace Locker\Repository\Statement;

class EloquentStatementRepository implements StatementRepository {
  public function aggregate(Authority $authority, array $pipeline) {
    return (new EloquentStatementGetter)->aggregate($authority, $pipeline);
  }

  public function show(Authority $authority, $id, $voided = false, $active = true) {
    return (new EloquentStatementGetter)->show($authority, $id, $voided, $active);
  }

  public function index(Authority $authority, array $options) {
    return (new EloquentStatementGetter)->index($authority, $options);
  }

  public function store(Authority $authority, array $statements) {
    return (new EloquentStatementStorer)->store($authority);
  }
}