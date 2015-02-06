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
      return $this->getModel($statement, $authority);
    }, $statements));

    while (count($this->to_update) > 0) {
      $this->upLink($this->to_update[0], [], $authority);
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
   * Gets the statement as an associative array from the database.
   * @param XAPIStatement $statement
   * @param Authority $authority The authority to restrict with.
   * @return [String => mixed] Statement model.
   */
  private function getModel(XAPIStatement $statement, Authority $authority) {
    $statement_id = $statement->getPropValue('id');

    $model = (new EloquentGetter)
      ->where($authority)
      ->where('statement.id', $statement_id)
      ->first()->toArray();

    return $model;
  }

  /**
   * Goes up the reference chain until it reaches the top then goes down setting references.
   * @param [String => mixed] $statement
   * @param [String] $visited IDs of statements visisted in the current chain (avoids infinite loop).
   * @param Authority $authority The authority to restrict with.
   * @return [[String => mixed]]
   */
  private function upLink(array $statement, array $visited, Authority $authority) {
    if (in_array($statement['statement']['id'], $visited)) return [];

    $visited[] = $statement['statement']['id'];
    $up_refs = $this->upRefs($statement, $authority);
    if (count($up_refs) > 0) {
      $downed = [];
      return array_map(function ($up_ref) use ($authority, $visited, $downed) {
        if (in_array($up_ref, $downed)) return;
        $downed = array_merge($downed, $this->upLink($up_ref, $visited, $authority));
      }, $up_refs);
    } else {
      return $this->downLink($statement, [], $authority);
    }
  }

  /**
   * Goes down the reference chain setting references (refs).
   * @param [String => mixed] $statement
   * @param [String] $visited IDs of statements visisted in the current chain (avoids infinite loop).
   * @param Authority $authority The authority to restrict with.
   * @return [[String => mixed]]
   */
  private function downLink(array $statement, array $visited, Authority $authority) {
    if (in_array($statement['statement']['id'], $visited)) return [];

    $visited[] = $statement['statement']['id'];
    $down_ref = $this->downRef($statement, $authority);
    if ($down_ref !== null) {
      $refs = $this->downLink($down_ref->toArray(), $visited, $authority);
      $this->setRefs($statement, $refs, $authority);
      $this->unQueue($statement);
      return array_merge([$statement], $refs);
    } else {
      $this->unQueue($statement);
      return [$statement];
    }
  }

  /**
   * Gets the statements referencing the given statement.
   * @param [String => mixed] $statement
   * @param Authority $authority The authority to restrict with.
   * @return [[String => mixed]]
   */
  private function upRefs(array $statement, Authority $authority) {
    return (new EloquentGetter)
      ->where($authority)
      ->where('statement.object.id', $statement['statement']['id'])
      ->where('statement.object.objectType', 'StatementRef')
      ->get()->toArray();
  }

  /**
   * Gets the statement referred to by the given statement.
   * @param [String => mixed] $statement
   * @param Authority $authority The authority to restrict with.
   * @return \Models\Statement
   */
  private function downRef(array $statement, Authority $authority) {
    if (!$this->isReferencingArray($statement['statement'])) return null;
    return (new EloquentGetter)
      ->where($authority)
      ->where('statement.id', $statement['statement']['object']['id'])
      ->first();
  }

  /**
   * Updates the refs for the given statement.
   * @param [String => mixed] $statement
   * @param [[String => mixed]] $refs Statements that are referenced by the given statement.
   * @param Authority $authority The authority to restrict with. 
   */
  private function setRefs(array $statement, array $refs, Authority $authority) {
    return (new EloquentGetter)
      ->where($authority)
      ->where('statement.id', $statement['statement']['id'])
      ->update([
        'refs' => $refs
      ]);
  }

  /**
   * Unqueues the statement so that it doesn't get relinked.
   * @param [String => mixed] $statement
   */
  private function unQueue(array $statement) {
    $updated_index = array_search($statement, $this->to_update);
    if ($updated_index !== false) {
      array_splice($this->to_update, $updated_index, 1);
    }
  }
}
