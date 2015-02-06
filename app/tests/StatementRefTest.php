<?php

use \Models\Statement as Statement;
use \Repos\Statement\EloquentRepository as StatementRepo;

class StatementRefTest extends TestCase {

  private function sendStatements($statements) {
    return (new StatementRepo)->store($this->authority, json_decode(json_encode($statements)), []);
  }

  protected function generateStatement($statement) {
    return array_merge($statement, [
      'actor' => [
        'mbox' => 'mailto:test@example.com'
      ],
      'verb' => [
        'id' => 'http://www.example.com/verbs/test',
      ],
    ]);
  }

  private function createReferenceStatement($reference_id, $statement = []) {
    return $this->generateStatement(array_merge($statement, [
      'object' => [
        'objectType' => 'StatementRef',
        'id' => $this->generateUUID($reference_id)
      ]
    ]));
  }

  private function createIdStatement($id, $statement = []) {
    return $this->generateStatement(array_merge($statement, [
      'id' => $this->generateUUID($id)
    ]));
  }

  private function checkStatement($id, $expected_references = [], $expected_referrers = []) {
    $uuid = $this->generateUUID($id);
    $statement = (new StatementRepo)->show($this->authority, $this->generateUUID($id));

    //$queries = DB::getQueryLog();

    $expected_references = array_map(function ($ref) {
      return $this->generateUUID($ref);
    }, $expected_references);

    $expected_referrers = array_map(function ($ref) {
      return $this->generateUUID($ref);
    }, $expected_referrers);

    // Checks $expected_references.
    $references = array_map(function ($ref) {
      return $ref->statement->id;
    }, isset($statement->refs) ? $statement->refs : []);

    // Checks $expected_referrers.
    $referrers = (new Statement)
      ->select('statement.id')
      ->where('statement.object.id', '=', $uuid)
      ->where('statement.object.objectType', '=', 'StatementRef')
      ->get()->toArray();
    $referrers = array_map(function ($ref) {
      return $ref['statement']['id'];
    }, $referrers);

    \Log::info([
      'id' => $id,
      'expected_referrers' => $expected_referrers,
      'referrers' => $referrers,
      'expected_references' => $expected_references,
      'references' => $references
    ]);
    $this->assertEmpty(array_diff($expected_referrers, $referrers));
    $this->assertEmpty(array_diff($expected_references, $references));
  }

  private function generateUUID($id) {
    $len = strlen($id);
    $start = str_repeat('0', 8 - $len);
    return $id . $start . '-0000-0000-b000-000000000000';
  }

  public function testInsert1() {
    \Log::info('START TEST 1');
    $this->sendStatements([
      $this->createIdStatement('A', $this->createReferenceStatement('E'))
    ]);

    $this->checkStatement('A', [], []);
    \Log::info('END TEST 1');
  }

  public function testInsert2() {
    \Log::info('START TEST 2');
    $this->sendStatements([
      $this->createIdStatement('A', $this->createReferenceStatement('E'))
    ]);

    $this->sendStatements([
      $this->createIdStatement('C', $this->createReferenceStatement('A')),
      $this->createIdStatement('D', $this->createReferenceStatement('B'))
    ]);

    $this->checkStatement('A', [], ['C']);
    $this->checkStatement('C', ['A'], []);
    $this->checkStatement('D', [], []);
    \Log::info('END TEST 2');
  }

  public function testInsert3() {
    \Log::info('START TEST 3');
    $this->sendStatements([
        $this->createIdStatement('A', $this->createReferenceStatement('E'))
    ]);

    $this->sendStatements([
        $this->createIdStatement('C', $this->createReferenceStatement('A')),
        $this->createIdStatement('D', $this->createReferenceStatement('B'))
    ]);

    $this->sendStatements([
      $this->createIdStatement('B', $this->createReferenceStatement('A'))
    ]);

    $this->checkStatement('A', [], ['B', 'C']);
    $this->checkStatement('B', ['A'], ['D']);
    $this->checkStatement('C', ['A'], []);
    $this->checkStatement('D', ['B', 'A'], []);
    \Log::info('END TEST 3');
  }

  public function testInsert4() {
    \Log::info('START TEST 4');
    $this->sendStatements([
        $this->createIdStatement('A', $this->createReferenceStatement('E'))
    ]);

    $this->sendStatements([
        $this->createIdStatement('C', $this->createReferenceStatement('A')),
        $this->createIdStatement('D', $this->createReferenceStatement('B'))
    ]);

    $this->sendStatements([
        $this->createIdStatement('B', $this->createReferenceStatement('A'))
    ]);

    $this->sendStatements([
      $this->createIdStatement('E', $this->createReferenceStatement('D'))
    ]);

    $this->checkStatement('A', ['E', 'D', 'B'], ['B', 'C']);
    $this->checkStatement('B', ['A', 'E', 'D'], ['D']);
    $this->checkStatement('C', ['A', 'E', 'D', 'B'], []);
    $this->checkStatement('D', ['B', 'A', 'E'], ['E']);
    $this->checkStatement('E', ['D', 'B', 'A'], ['A']);
    \Log::info('END TEST 4');
  }

  public function testInsert5() {
    \Log::info('START TEST 5');
    $this->sendStatements([
        $this->createIdStatement('A', $this->createReferenceStatement('E'))
    ]);

    $this->sendStatements([
        $this->createIdStatement('C', $this->createReferenceStatement('A')),
        $this->createIdStatement('D', $this->createReferenceStatement('B'))
    ]);

    $this->sendStatements([
        $this->createIdStatement('B', $this->createReferenceStatement('A'))
    ]);

    $this->sendStatements([
      $this->createIdStatement('E', $this->createReferenceStatement('D'))
    ]);

    $this->sendStatements([
      $this->createIdStatement('F', $this->createReferenceStatement('D'))
    ]);

    $this->checkStatement('A', ['E', 'D', 'B'], ['B', 'C']);
    $this->checkStatement('B', ['A', 'E', 'D'], ['D']);
    $this->checkStatement('C', ['A', 'E', 'D', 'B'], []);
    $this->checkStatement('D', ['B', 'A', 'E'], ['E']);
    $this->checkStatement('E', ['D', 'B', 'A'], ['A']);
    $this->checkStatement('F', ['D', 'B', 'A', 'E'], []);
    \Log::info('END TEST 5');
  }

  public function tearDown() {
    parent::tearDown();
    (new StatementRepo)->where($this->authority)->delete();
    if ($this->authority) $this->authority->delete();
  }

}