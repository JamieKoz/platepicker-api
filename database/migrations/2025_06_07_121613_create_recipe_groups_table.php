<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecipeGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('recipe_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['recipe_id', 'sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('recipe_groups');
    }
};
