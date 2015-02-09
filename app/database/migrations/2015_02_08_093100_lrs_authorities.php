<?php

use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;
use \DB as Mongo;

class LrsAuthorities extends Migration {

	public function up() {
		$now = new \MongoDate(strtotime('now'));
		$lrs_array = Mongo::getMongoDB()->lrs->find();

		foreach ($lrs_array as $lrs) {
			Mongo::getMongoDB()->authorities->insert([
				'name' => $lrs['_id'],
				'homePage' => 'http://learninglocker.net/authority/'.$lrs['_id'],
				'description' => $lrs['title'],
				'auth' => 'basic',
				'credentials' => [
					'username' => $lrs['api']['basic_key'],
					'password' => $lrs['api']['basic_secret']
				],
				'created_at' => $now,
				'updated_at' => $now
			]);
		}
	}

	public function down() {
		$auth_array = Mongo::getMongoDB()->authorities->drop();
	}
}
