<?php

use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;
use \Models\Statement as Statement;

class ConvertTimestamp extends Migration {
	public function up() {
		Statement::chunk(1000, function ($statements) {
			foreach ($statements as $s){
				if (!isset($s->timestamp)) {
					$s->timestamp = new \MongoDate(strtotime($s->statement['timestamp']));
        	$s->save();
				}
      }
      echo count($statements) . ' converted.';
		});
	}

	public function down() {
		// Having the timestamp doesn't break backwards compatibility.
		// Hence no need for down.
	}
}
