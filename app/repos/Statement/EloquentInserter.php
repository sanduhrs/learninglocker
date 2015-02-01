<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Locker\XApi\Statement as XAPIStatement;
use \MongoDate as MongoDate;
use \Helpers\Helpers as Helpers;
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

    $duplicate = XAPIStatement::createFromJson(json_encode($duplicate->statement));
    $this->checkMatch($statement, $duplicate);
  }

  private function checkMatch(XAPIStatement $statement, XAPIStatement $matcher) {
    $new_statement = json_decode($statement->toJson(), true);
    $old_statement = json_decode($matcher->toJson(), true);
    array_multisort($new_statement);
    array_multisort($old_statement);
    ksort($new_statement);
    ksort($old_statement);
    unset($new_statement['stored']);
    unset($old_statement['stored']);
    unset($new_statement['authority']);
    unset($old_statement['authority']);
    if ($new_statement !== $old_statement) {
      $new_statement = $statement->toJson();
      $old_statement = $matcher->toJson();
      throw new ConflictException(
        "Conflicts\r\n`".$statement->toJson()."`\r\n`".$matcher->toJson()."`."
      );
    };
  }

  private function storeActivityProfile(XAPIStatement $statement, Authority $authority) {
    if ($statement->getPropValue('object.definition') === null) return;

    return (new ActivityProfileRepo)->store(
      $authority,
      [
        'activityId' => $statement->getPropValue('object.id'),
        'content_info' => [
          'content' => json_encode($statement->getPropValue('object.definition')),
          'contentType' => 'application/json'
        ]
      ]
    );
  }

  private function constructModel(XAPIStatement $statement, Authority $authority) {
    return [
      'lrs' => [
        '_id' => $authority->getLRS(),
      ],
      'statement' => Helpers::replaceDots($statement->getValue()),
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
