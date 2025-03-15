<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('categories')->insert([
            ['name' => 'Breakfast', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Lunch', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Dinner', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Dessert', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Snack', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('categories');
    }
}
