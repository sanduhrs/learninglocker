<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use \DB as Mongo;

class CreateStatements extends Migration {

	public function up() {
		Mongo::getMongoDB()->createCollection('statements');
	}

	public function down() {
		Mongo::getMongoDB()->statements->drop();
	}
}
