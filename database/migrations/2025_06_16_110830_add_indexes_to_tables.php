<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add indexes for recipe search and filtering
        Schema::table('recipes', function (Blueprint $table) {
            $table->index('active', 'recipes_active_index');
            $table->index('cooking_time', 'recipes_cooking_time_index');
            $table->index(['active', 'cooking_time'], 'recipes_active_cooking_time_index');
            $table->index('created_at', 'recipes_created_at_index');
        });

        // Add indexes for recipe-cuisine relationships
        Schema::table('recipes_cuisine', function (Blueprint $table) {
            $table->index('recipe_id', 'recipes_cuisine_recipe_id_index');
            $table->index('cuisine_id', 'recipes_cuisine_cuisine_id_index');
            $table->unique(['recipe_id', 'cuisine_id'], 'recipes_cuisine_recipe_cuisine_unique');
        });

        // Add indexes for recipe-dietary relationships
        Schema::table('recipes_dietary', function (Blueprint $table) {
            $table->index('recipe_id', 'recipes_dietary_recipe_id_index');
            $table->index('dietary_id', 'recipes_dietary_dietary_id_index');
            $table->unique(['recipe_id', 'dietary_id'], 'recipes_dietary_recipe_dietary_unique');
        });

        // Add indexes for recipe categories
        Schema::table('recipe_categories', function (Blueprint $table) {
            $table->index('recipe_id', 'recipe_categories_recipe_id_index');
            $table->index('category_id', 'recipe_categories_category_id_index');
            $table->unique(['recipe_id', 'category_id'], 'recipe_categories_recipe_category_unique');
        });

        // Add indexes for recipe lines (ingredients)
        Schema::table('recipe_lines', function (Blueprint $table) {
            $table->index('recipe_id', 'recipe_lines_recipe_id_index');
            $table->index('ingredient_id', 'recipe_lines_ingredient_id_index');
            $table->index('measurement_id', 'recipe_lines_measurement_id_index');
            $table->index('user_meal_id', 'recipe_lines_user_meal_id_index');
            $table->index(['recipe_id', 'ingredient_id'], 'recipe_lines_recipe_ingredient_index');
        });

        // Add indexes for recipe groups
        Schema::table('recipe_groups', function (Blueprint $table) {
            $table->index('recipe_id', 'recipe_groups_recipe_id_index');
        });

        // Add indexes for user meal groups
        Schema::table('user_meal_groups', function (Blueprint $table) {
            $table->index('user_meal_id', 'user_meal_groups_user_meal_id_index');
        });

        // Add indexes for user meal-cuisine relationships
        Schema::table('user_meals_cuisine', function (Blueprint $table) {
            $table->index('user_meal_id', 'user_meals_cuisine_user_meal_id_index');
            $table->index('cuisine_id', 'user_meals_cuisine_cuisine_id_index');
            $table->unique(['user_meal_id', 'cuisine_id'], 'user_meals_cuisine_user_meal_cuisine_unique');
        });

        // Add indexes for user meal-dietary relationships
        Schema::table('user_meals_dietary', function (Blueprint $table) {
            $table->index('user_meal_id', 'user_meals_dietary_user_meal_id_index');
            $table->index('dietary_id', 'user_meals_dietary_dietary_id_index');
            $table->unique(['user_meal_id', 'dietary_id'], 'user_meals_dietary_user_meal_dietary_unique');
        });

        // Add indexes for user meal categories
        Schema::table('user_meals_categories', function (Blueprint $table) {
            $table->index('user_meal_id', 'user_meals_categories_user_meal_id_index');
            $table->index('category_id', 'user_meals_categories_category_id_index');
            /* $table->unique(['user_meal_id', 'category_id'], 'user_meals_categories_user_meal_category_unique'); */
        });

        // Add indexes for lookup tables to improve name searches
        Schema::table('cuisines', function (Blueprint $table) {
            $table->index('name', 'cuisines_name_index');
            $table->index('value', 'cuisines_value_index');
        });

        Schema::table('dietary', function (Blueprint $table) {
            $table->index('name', 'dietary_name_index');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->index('name', 'ingredients_name_index');
        });

        Schema::table('measurements', function (Blueprint $table) {
            $table->index('name', 'measurements_name_index');
            $table->index('abbreviation', 'measurements_abbreviation_index');
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
            $table->dropIndex('recipes_active_index');
            $table->dropIndex('recipes_cooking_time_index');
            $table->dropIndex('recipes_active_cooking_time_index');
            $table->dropIndex('recipes_created_at_index');
        });

        Schema::table('recipes_cuisine', function (Blueprint $table) {
            $table->dropIndex('recipes_cuisine_recipe_id_index');
            $table->dropIndex('recipes_cuisine_cuisine_id_index');
            $table->dropUnique('recipes_cuisine_recipe_cuisine_unique');
        });

        Schema::table('recipes_dietary', function (Blueprint $table) {
            $table->dropIndex('recipes_dietary_recipe_id_index');
            $table->dropIndex('recipes_dietary_dietary_id_index');
            $table->dropUnique('recipes_dietary_recipe_dietary_unique');
        });

        Schema::table('recipe_categories', function (Blueprint $table) {
            $table->dropIndex('recipe_categories_recipe_id_index');
            $table->dropIndex('recipe_categories_category_id_index');
            $table->dropUnique('recipe_categories_recipe_category_unique');
        });

        Schema::table('recipe_lines', function (Blueprint $table) {
            $table->dropIndex('recipe_lines_recipe_id_index');
            $table->dropIndex('recipe_lines_ingredient_id_index');
            $table->dropIndex('recipe_lines_measurement_id_index');
            $table->dropIndex('recipe_lines_user_meal_id_index');
            $table->dropIndex('recipe_lines_recipe_ingredient_index');
        });

        Schema::table('recipe_groups', function (Blueprint $table) {
            $table->dropIndex('recipe_groups_recipe_id_index');
        });

        Schema::table('user_meal_groups', function (Blueprint $table) {
            $table->dropIndex('user_meal_groups_user_meal_id_index');
        });

        Schema::table('user_meals_cuisine', function (Blueprint $table) {
            $table->dropIndex('user_meals_cuisine_user_meal_id_index');
            $table->dropIndex('user_meals_cuisine_cuisine_id_index');
            $table->dropUnique('user_meals_cuisine_user_meal_cuisine_unique');
        });

        Schema::table('user_meals_dietary', function (Blueprint $table) {
            $table->dropIndex('user_meals_dietary_user_meal_id_index');
            $table->dropIndex('user_meals_dietary_dietary_id_index');
            $table->dropUnique('user_meals_dietary_user_meal_dietary_unique');
        });

        Schema::table('user_meals_categories', function (Blueprint $table) {
            $table->dropIndex('user_meals_categories_user_meal_id_index');
            $table->dropIndex('user_meals_categories_category_id_index');
            $table->dropUnique('user_meals_categories_user_meal_category_unique');
        });

        Schema::table('cuisines', function (Blueprint $table) {
            $table->dropIndex('cuisines_name_index');
            $table->dropIndex('cuisines_value_index');
        });

        Schema::table('dietary', function (Blueprint $table) {
            $table->dropIndex('dietary_name_index');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex('ingredients_name_index');
        });

        Schema::table('measurements', function (Blueprint $table) {
            $table->dropIndex('measurements_name_index');
            $table->dropIndex('measurements_abbreviation_index');
        });
    }
};
