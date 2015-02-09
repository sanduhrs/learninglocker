<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IndexAuthorityStatements extends Migration {

	public function up() {
    Schema::table('statements', function (Blueprint $table) {
    	$table->dropIndex('lrs._id');
      $table->dropIndex(['lrs._id', 'statement.object.id']);
      $table->dropIndex(['lrs._id', 'statement.verb.id']);
      $table->dropIndex(['lrs._id', 'statement.actor.mbox']);
      $table->dropIndex(['lrs._id', 'timestamp']);

      $table->index('statement.authority.homePage');
      $table->index(['statement.authority.homePage', 'statement.object.id']);
      $table->index(['statement.authority.homePage', 'statement.verb.id']);
      $table->index(['statement.authority.homePage', 'statement.actor.mbox']);
      $table->index(['statement.authority.homePage', 'timestamp']);
      $table->index(['statement.stored']);
      $table->index(['statement.stored', 'statement.authority.homePage']);
    });
  }

  public function down() {
    Schema::table('statements', function (Blueprint $table) {
    	$table->index('lrs._id');
      $table->index(['lrs._id', 'statement.object.id']);
      $table->index(['lrs._id', 'statement.verb.id']);
      $table->index(['lrs._id', 'statement.actor.mbox']);
      $table->index(['lrs._id', 'timestamp']);

      $table->dropIndex('statement.authority.homePage');
      $table->dropIndex(['statement.authority.homePage', 'statement.object.id']);
      $table->dropIndex(['statement.authority.homePage', 'statement.verb.id']);
      $table->dropIndex(['statement.authority.homePage', 'statement.actor.mbox']);
      $table->dropIndex(['statement.authority.homePage', 'timestamp']);
      $table->dropIndex(['statement.stored']);
      $table->dropIndex(['statement.stored', 'statement.authority.homePage']);
    });
  }
}
