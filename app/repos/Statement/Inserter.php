<?php namespace Repos\Statement;

interface InserterInterface {
  public function insert(array $statements, Authority $authority);
}

class Inserter implements InserterInterface {
  public function insert(array $statements, Authority $authority) {
    // Map statements to Eloquent models.
      // Remove existing statements and duplicates (check conflicts too).
      // Validate voids with existing statements and inserting statements.
      // Store the activity Profile.
      // Construct Eloquent models.
    
    // Insert.
  }

  private function checkDuplicate(XAPIStatement $statement, Authority $authority, array $statements) {

  }

  private function validateVoid(XAPIStatement $statement, Authority $authority, array $statements) {

  }

  private function storeActivityProfile(XAPIStatement $statement, Authority $authority) {

  }

  private function constructModels(XAPIStatement $statement) {

  }

  private function insertModels(XAPIStatement $statement, Authority $authority) {
    
  }
}