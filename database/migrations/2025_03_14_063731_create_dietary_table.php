<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateDietaryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dietary', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('dietary')->insert([
            ['name' => 'Gluten Free', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Dairy Free', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Egg Free', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Nut Free', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vegetarian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vegan', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pescatarian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Keto', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Paleo', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dietary');
    }
}
