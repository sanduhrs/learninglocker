<?php namespace Repos\Statement;

interface GetterInterface {
  public function aggregate(Authority $authority, array $pipeline);
  public function index(Authority $authority, array $options);
  public function show(Authority $authority, $id, $voided = false, $active = true);
}

class Getter implements GetterInterface {
  public function aggregate(Authority $authority, array $pipeline) {
    
  }

  public function index(Authority $authority, array $options) {

  }

  public function show(Authority $authority, $id, $voided = false, $active = true) {

  }
}