<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMatchUserTable extends Migration
{
  public function up(){
    Schema::create('match_user', function (Blueprint $table) {
      $table->id();
      $table->foreignId('match_id')->constrained();
      $table->foreignId('user_id')->constrained();
      $table->string('company_name');
      $table->integer('avatar');
      $table->boolean('bankrupt')->default(false);
      $table->boolean('dismissed')->default(false);
      $table->json('ceo_parameters')->nullable();
      $table->json('results')->nullable();
      $table->timestamps();
    });
  }

  public function down()
  {
    Schema::dropIfExists('match_user');
  }
}