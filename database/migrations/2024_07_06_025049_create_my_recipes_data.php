<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateMyRecipesData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('recipes')->updateOrInsert([
            'title' => 'Chicken Schnitzel',
            'ingredients' => 'some ingredients',
            'instructions' => 'some instructions',
            'image_name' => 'an image name goes here',
            'cleaned_ingredients' => 'cleaned ingreds',
            'active' => 0
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        DB::table('recipes')->where('active', 0)->delete();
    }
}
