<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCuisinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cuisines', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('value')->unique();
            $table->timestamps();
        });

        DB::table('cuisines')->insert([
            ['name' => 'American', 'value' => 'american', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Brazilian', 'value' => 'brazilian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Caribbean', 'value' => 'caribbean', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Chinese', 'value' => 'chinese', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'French', 'value' => 'french', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Indian', 'value' => 'indian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Italian', 'value' => 'italian', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Japanese', 'value' => 'japanese', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Korean', 'value' => 'korean', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mediterranean', 'value' => 'mediterranean', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mexican', 'value' => 'mexican', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Spanish', 'value' => 'spanish', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Thai', 'value' => 'thai', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Turkish', 'value' => 'turkish', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vietnamese', 'value' => 'vietnamese', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cuisines');
    }
}
