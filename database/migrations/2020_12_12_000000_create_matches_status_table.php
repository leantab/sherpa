<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMatchesStatusTable extends Migration
{
  public function up(){
    Schema::create('match_statuses', function (Blueprint $table) {
      $table->id();
      $table->string('status');
      $table->timestamps();
    });

    DB::table('match_statuses')->insert(['id' => 1, 'status' => 'pending']);
    DB::table('match_statuses')->insert(['id' => 2, 'status' => 'active']);
    DB::table('match_statuses')->insert(['id' => 3, 'status' => 'completed']);
    
  }

  public function down()
  {
    Schema::dropIfExists('matches');
  }
}