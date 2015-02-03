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

  public function link(array $statements, Authority $authority) {
    $this->updateReferences($statements, $authority);
    $this->voidStatements($statements, $authority);
  }

  public function updateReferences(array $statements, Authority $authority) {
    $this->to_update = array_values(array_map(function (XAPIStatement $statement) use ($authority) {
      return $this->addRefBy($statement, $authority);
    }, $statements));

    while (count($this->to_update) > 0) {
      $this->updateLinks($this->to_update[0], $authority);
    }
  }

  public function voidStatements(array $statements, Authority $authority) {
    return array_map(function (XAPIStatement $statement) use ($authority) {
      return $this->voidStatement($statement, $authority);
    }, $statements);
  }

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

  private function isVoidingArray(array $statement) {
    return $this->isVoiding(XAPIStatement::createFromJson(json_encode($statement)));
  }

  private function isReferencingArray(array $statement) {
    return $this->isVoiding(XAPIStatement::createFromJson(json_encode($statement)));
  }

  private function isVoiding(XAPIStatement $statement) {
    return (
      Helpers::replaceHTMLDots($statement->getPropValue('verb.id')) === 'http://adlnet.gov/expapi/verbs/voided' &&
      $this->isReferencing($statement)
    );
  }

  private function isReferencing(XAPIStatement $statement) {
    return $statement->getPropValue('object.objectType') === 'StatementRef';
  }

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

  private function getReferredStatement(array $statement, Authority $authority) {
    return (new EloquentGetter)
      ->where($authority)
      ->where('statement.id', $statement['statement']['object']['id'])
      ->first()->toArray();
  }

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
