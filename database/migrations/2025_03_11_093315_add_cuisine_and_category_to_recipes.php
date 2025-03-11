<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCuisineAndCategoryToRecipes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_meals', function (Blueprint $table) {
            $table->string('category')->nullable()->after('dietary');
            $table->string('cuisine')->nullable()->after('category');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->string('category')->nullable()->after('dietary');
            $table->string('cuisine')->nullable()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('cuisine');
            $table->dropColumn('category');
        });

        Schema::table('user_meals', function (Blueprint $table) {
            $table->dropColumn('cuisine');
            $table->dropColumn('category');
        });
    }
}
