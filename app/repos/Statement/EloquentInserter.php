<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Locker\XApi\Statement as XAPIStatement;
use \MongoDate as MongoDate;
use \Helpers\Exceptions\Conflict as ConflictException;
use \Repos\Document\ActivityProfile\EloquentRepository as ActivityProfileRepo;

interface InserterInterface {
  public function insert(array $statements, Authority $authority);
}

class EloquentInserter implements InserterInterface {
  public function insert(array $statements, Authority $authority) {
    $statement_models = array_map(function (XAPIStatement $statement) use ($authority) {
      $this->checkDuplicate($statement, $authority);
      $this->storeActivityProfile($statement, $authority);

      return $this->constructModel($statement, $authority);
    }, $statements);

    return $this->insertModels($statement_models, $authority);
  }

  private function checkDuplicate(XAPIStatement $statement, Authority $authority) {
    $duplicate = (new EloquentGetter)
      ->where($authority)
      ->where('statement.id', $statement->getPropValue('id'))
      ->first();

    if ($duplicate === null) return;

    $duplicate = XAPIStatement::createFromJson(json_encode($duplicate));
    $this->checkMatch($statement, $duplicate);
  }

  private function checkMatch(XAPIStatement $statement, XAPIStatement $matcher) {
    $new_statement = json_decode($new_statement_obj->toJson(), true);
    $old_statement = json_decode($old_statement_obj->toJson(), true);
    array_multisort($new_statement);
    array_multisort($old_statement);
    ksort($new_statement);
    ksort($old_statement);
    unset($new_statement['stored']);
    unset($old_statement['stored']);
    unset($new_statement['authority']);
    unset($old_statement['authority']);
    if ($new_statement !== $old_statement) {
      $new_statement = $new_statement_obj->toJson();
      $old_statement = $old_statement_obj->toJson();
      throw new ConflictException(
        "Conflicts\r\n`$new_statement`\r\n`$old_statement`."
      );
    };
  }

  private function storeActivityProfile(XAPIStatement $statement, Authority $authority) {
    if ($statement->getPropValue('object.definition') === null) return;

    return (new ActivityProfileRepo)->store(
      $authority,
      [
        'id' => $statement->getPropValue('object.id'),
        'definition' => $statement->getPropValue('object.definition')
      ]
    );
  }

  private function constructModel(XAPIStatement $statement, Authority $authority) {
    return [
      'lrs' => [
        '_id' => $authority->getLRS(),
      ],
      'statement' => $statement->getValue(),
      'active' => false,
      'voided' => false,
      'timestamp' => new MongoDate(strtotime($statement->getPropValue('timestamp')))
    ];
  }

  private function insertModels(array $statement_models, Authority $authority) {
    return (new EloquentGetter)
      ->where($authority)
      ->insert($statement_models);
  }
}
