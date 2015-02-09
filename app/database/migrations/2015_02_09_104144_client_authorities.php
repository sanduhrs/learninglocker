<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use \DB as Mongo;

class ClientAuthorities extends Migration {

	public function up() {
		$now = new \MongoDate(strtotime('now'));
		$clients = Mongo::getMongoDB()->client->find();

		foreach ($clients as $client) {
			Mongo::getMongoDB()->authorities->insert([
				'name' => $client['_id'],
				'homePage' => 'http://learninglocker.net/authority/'.$client['lrs_id'].'/'.$client['_id'],
				'auth' => 'basic',
				'credentials' => [
					'username' => $client['api']['basic_key'],
					'password' => $client['api']['basic_secret']
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
