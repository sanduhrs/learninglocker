<?php

use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;
use \DB as Mongo;
use \Models\Authority as Authority;
use \Helpers\Helpers as Helpers;

class CreateAuthorities extends Migration {

	public function up() {
		Mongo::getMongoDB()->createCollection('authorities');
    $authority = new Authority([
      'description' => 'root',
      'auth' => 'basic',
      'credentials' => [
        'username' => Helpers::getRandomSha(),
        'password' => Helpers::getRandomSha()
      ]
    ]);
    $authority->save();
    $authority->name = $authority->_id;
    $authority->homePage = 'http://learninglocker.net/authority/'.$authority->name;
    $authority->save();
	}

	public function down() {
		Mongo::getMongoDB()->authorities->drop();
	}
}
