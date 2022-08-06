<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGamesTable extends Migration
{
  public function up()
  {
    Schema::create('games', function (Blueprint $table) {
      $table->id();
      $table->integer('segment_id');
      $table->foreignId('status_id')->references('id')->on('game_statuses')->constrained();
      $table->string('version');
      $table->json('game_parameters');
      $table->string('creator_id');
      $table->string('goverment_id')->nullable();
      $table->integer('current_stage')->nullable()->default(0);
      $table->json('goverment_parameters')->nullable();
      $table->json('results')->nullable();
      $table->timestamps();
    });
  }

  public function down()
  {
    Schema::dropIfExists('games');
  }
}
