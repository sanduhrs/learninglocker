<?php

use Illuminate\Foundation\Testing\TestCase as IlluminateTestCase;
use \Models\Authority as Authority;

class TestCase extends IlluminateTestCase {

  public function createApplication() {
    $unitTesting = true;
    $testEnvironment = 'testing';
    return require __DIR__ . '/../../bootstrap/start.php';
  }

  public function setUp() {
    parent::setUp();
    $this->authority = $this->createAuthority();
  }

  private function createAuthority() {
    $authority = new Authority([
      'description' => 'StatementRefTest',
      'auth' => 'basic',
      'credentials' => (object) [
        'username' => 'username',
        'password' => 'password'
      ]
    ]);

    $authority->save();
    $authority->name = $authority->_id;
    $authority->homePage = 'http://learninglocker/authority/StatementRefTest/'.$authority->name;
    $authority->save();
    return $authority;
  }

  protected function makeRequestHeaders($auth, $version='1.0.1') {
    return [
      'PHP_AUTH_USER' => $auth['api_key'],
      'PHP_AUTH_PW' => $auth['api_secret'],
      'HTTP_X-Experience-API-Version' => $version
    ];
  }

}