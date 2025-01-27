<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserMealTallyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_meal_tally', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->foreignId('recipe_id')->constrained('recipes')->onDelete('cascade');
            $table->integer('tally')->default(0);
            $table->timestamp('last_selected_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'recipe_id']);
            $table->index('user_id');
            $table->index('selection_count');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_meal_tally');
    }
}
