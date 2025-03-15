<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserMealsDietaryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_meals_dietary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_meals_id')->constrained()->onDelete('cascade');
            $table->foreignId('dietary_id')->constrained('dietary')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_meals_dietary');
    }
}
