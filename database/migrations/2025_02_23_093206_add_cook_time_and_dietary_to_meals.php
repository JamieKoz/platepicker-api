<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCookTimeAndDietaryToMeals extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_meals', function (Blueprint $table) {
            $table->string('cooking_time')->nullable()->after('cleaned_ingredients');
        });
        Schema::table('user_meals', function (Blueprint $table) {
            $table->string('serves')->nullable()->after('cooking_time');
        });
        Schema::table('user_meals', function (Blueprint $table) {
            $table->string('dietary')->nullable()->after('serves');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->string('cooking_time')->nullable()->after('cleaned_ingredients');
        });
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('serves')->nullable()->after('cooking_time');
        });
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('dietary')->nullable()->after('serves');
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
            $table->dropColumn('dietary');
        });
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('serves');
        });
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('cooking_time');
        });

        Schema::table('user_meals', function (Blueprint $table) {
            $table->dropColumn('dietary');
        });
        Schema::table('user_meals', function (Blueprint $table) {
            $table->dropColumn('serves');
        });
        Schema::table('user_meals', function (Blueprint $table) {
            $table->dropColumn('cooking_time');
        });
    }
}
