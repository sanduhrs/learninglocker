<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Locker\XApi\Statement as XAPIStatement;
use \Helpers\Helpers as Helpers;

interface LinkerInterface {
  public function link(array $statements, Authority $authority);
  public function voidStatements(array $statements, Authority $authority);
}

class EloquentLinker implements LinkerInterface {
  private $to_update = [];

  /**
   * Links statements together.
   * @param [XAPIStatement] $statements
   * @param Authority $authority The authority to restrict with.
   */
  public function link(array $statements, Authority $authority) {
    $this->updateReferences($statements, $authority);
    $this->voidStatements($statements, $authority);
  }

  /**
   * Updates statement references.
   * @param [XAPIStatement] $statements
   * @param Authority $authority The authority to restrict with.
   */
  public function updateReferences(array $statements, Authority $authority) {
    $this->to_update = array_values(array_map(function (XAPIStatement $statement) use ($authority) {
      return $this->addRefBy($statement, $authority);
    }, $statements));

    while (count($this->to_update) > 0) {
      $this->updateLinks($this->to_update[0], $authority);
    }
  }

  /**
   * Voids statements that need to be voided.
   * @param [XAPIStatement] $statements
   * @param Authority $authority The authority to restrict with.
   */
  public function voidStatements(array $statements, Authority $authority) {
    return array_map(function (XAPIStatement $statement) use ($authority) {
      return $this->voidStatement($statement, $authority);
    }, $statements);
  }

  /**
   * Voids a statement if it needs to be voided.
   * @param XAPIStatement $statement
   * @param Authority $authority The authority to restrict with.
   */
  private function voidStatement(XAPIStatement $statement, Authority $authority) {
    if (!$this->isVoiding($statement)) return;

    $voided_statement = (new EloquentGetter)
      ->where($authority)
      ->where('statement.id', $statement->getPropValue('object.id'))
      ->first()->toArray();

    if ($voided_statement !== null) {
      if ($this->isVoidingArray($voided_statement['statement'])) throw new \Exception(trans(
        'xapi.errors.void_voider'
      ));

      (new EloquentGetter)
        ->where($authority)
        ->where('statement.id', $voided_statement['statement']['id'])
        ->update(['voided' => true]);
    } else {
      throw new \Exception(trans(
        'xapi.errors.void_voider'
      ));
    }
  }

  /**
   * Determines if a statement (represented as an associative array) is a voiding statement.
   * @param [String => mixed] $statement
   * @return Boolean
   */
  private function isVoidingArray(array $statement) {
    return $this->isVoiding(XAPIStatement::createFromJson(json_encode($statement)));
  }

  /**
   * Determines if a statement (represented as an associative array) is a referencing statement.
   * @param [String => mixed] $statement
   * @return Boolean
   */
  private function isReferencingArray(array $statement) {
    return $this->isVoiding(XAPIStatement::createFromJson(json_encode($statement)));
  }

  /**
   * Determines if a statement is a voiding statement.
   * @param XAPIStatement $statement
   * @return Boolean
   */
  private function isVoiding(XAPIStatement $statement) {
    return (
      Helpers::replaceHTMLDots($statement->getPropValue('verb.id')) === 'http://adlnet.gov/expapi/verbs/voided' &&
      $this->isReferencing($statement)
    );
  }

  /**
   * Determines if a statement is a referencing statement.
   * @param XAPIStatement $statement
   * @return Boolean
   */
  private function isReferencing(XAPIStatement $statement) {
    return $statement->getPropValue('object.objectType') === 'StatementRef';
  }

  /**
   * Adds an array of all the statement ID's of statements that refer to the given statement.
   * @param XAPIStatement $statement
   * @param Authority $authority The authority to restrict with.
   * @return [String => mixed] Statement model with a refBy property.
   */
  private function addRefBy(XAPIStatement $statement, Authority $authority) {
    $statement_id = $statement->getPropValue('id');

    $model = (new EloquentGetter)
      ->where($authority)
      ->where('statement.id', $statement_id)
      ->first()->toArray();

    $model['refBy'] = (new EloquentGetter)
      ->where($authority)
      ->where('statement.object.id', $statement_id)
      ->where('statement.object.objectType', 'StatementRef')
      ->lists('statement.id');

    return $model;
  }

  /**
   * Gets all of the statements referred to by the given statement.
   * @param [String => mixed] $statement
   * @param Authority $authority The authority to restrict with.
   * @return [String => mixed] Referred statements.
   */
  private function getReferredStatement(array $statement, Authority $authority) {
    return (new EloquentGetter)
      ->where($authority)
      ->where('statement.id', $statement['statement']['object']['id'])
      ->first()->toArray();
  }

  /**
   * Updates the links for a given statement
   * @param [String => mixed] $statement
   * @param Authority $authority The authority to restrict with.
   * @param [[String => mixed]]|null $refs Statements referred to by the given statement.
   * @return [String => mixed] Referred statements.
   */
  private function updateLinks(array $statement, Authority $authority, array $refs = null) {
    $statement_copy = $statement;

    if ($refs === null && $this->isReferencingArray($statement['statement'])) {
      $refs = $this->updateLinks($this->getReferredStatement($statement, $authority), $authority);
    }

    // Updates stored refs.
    $refs = array_merge($refs ?: [], isset($statement['refs']) ? $statement['refs'] : []);

    // Saves statement with new refs.
    (new EloquentGetter)->where($authority)->update([
      'refs' => $refs
    ]);

    // Updates referrers refs.
    array_map(function ($ref) {
      $this->updateLinks($ref, $authority, $refs);
    }, isset($statement['refBy']) ? $statement['refBy'] : []);

    // Removes statement from to_update.
    $updated_index = array_search($statement_copy, $this->to_update);
    if ($updated_index !== false) {
      array_splice($this->to_update, $updated_index, 1);
    }

    return $refs;
  }
}
