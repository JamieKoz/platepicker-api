<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropUserMealsForeignConstraint extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // For SQLite, we need to recreate the table without the foreign key
        // First, get the table structure
        $table = DB::select("PRAGMA table_info(user_meals)");

        // Create a temporary table with the same structure but without the foreign key
        Schema::create('user_meals_temp', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // No foreign key constraint here
            $table->foreignId('recipe_id')->nullable()->constrained('recipes')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('ingredients')->nullable();
            $table->text('instructions')->nullable();
            $table->string('image_name')->nullable();
            $table->text('cleaned_ingredients')->nullable();
            $table->boolean('active')->default(true);
            $table->string('cooking_time')->nullable()->after('cleaned_ingredients');
            $table->string('serves')->nullable()->after('cooking_time');
            $table->string('dietary')->nullable()->after('serves');

            $table->timestamps();
        });

        // Copy data from the original table
        DB::statement('INSERT INTO user_meals_temp SELECT * FROM user_meals');

        // Drop the original table
        Schema::dropIfExists('user_meals');

        // Rename the temporary table to the original name
        Schema::rename('user_meals_temp', 'user_meals');
    }

    public function down()
    {
        // In the down method, you'd need to recreate the table with the foreign key
        // This is just a placeholder - adjust according to your original schema
        Schema::table('user_meals', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
}
