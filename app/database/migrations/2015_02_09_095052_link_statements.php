<?php

use \Illuminate\Database\Schema\Blueprint;
use \Illuminate\Database\Migrations\Migration;
use \Models\Statement as Statement;
use \Models\Authority as Authority;
use \Repos\Statement\EloquentLinker as StatementLinker;
use \Locker\XApi\Statement as XAPIStatement;

class LinkStatements extends Migration {
	public function up() {
		$authorities = (new Authority)->get();

        // Remove all inactive statements.
        Statement::where('active', '=', false)->delete();

        // Migrates the statements for each LRS.
        foreach ($authorities as $authority) {
          Statement::where('lrs._id', $authority->name)->chunk(300, function ($statements) use ($authority) {
            $statements_array = [];

            // Sets `active` and `voided` properties.
            foreach ($statements as $statement) {
            	$statement->voided = isset($statement->voided) ? $statement->voided : false;
            	$statement->active = isset($statement->active) ? $statement->active : true;
                $statement->statement['authority'] = $authority->getActor();
            	$statement->save();
            	$statements_array[] = XAPIStatement::createFromJson(json_encode($statement->statement));
            }

            // Uses the repository to migrate the statements.
            (new StatementLinker)->link($statements_array, $authority);

            // Outputs status.
            $statement_count = $statements->count();
            echo "Migrated $statement_count statements in `{$authority->description}`.";
          });
        }
        echo 'All statements migrated.';
	}

	public function down() {
		// Remove all inactive statements.
		Statement::where('active', '=', false)->delete();
		Statement::where('active', '=', true)->update([
			'refs' => null,
			'voided' => false
		]);
	}
}
