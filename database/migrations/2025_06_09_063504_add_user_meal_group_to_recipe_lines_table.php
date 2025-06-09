<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserMealGroupToRecipeLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('recipe_lines', function (Blueprint $table) {
            //
            $table->foreignId('user_meal_group_id')->nullable()->constrained()->onDelete('set null');
            $table->index(['user_meal_group_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recipe_lines', function (Blueprint $table) {
            $table->dropForeign(['user_meal_group_id']);
            $table->dropColumn('user_meal_group_id');
            $table->string('group_name')->nullable();
        });
    }
}
