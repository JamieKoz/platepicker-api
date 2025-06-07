<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRecipeGroupToRecipeLinesTable extends Migration
{
    public function up()
    {
        Schema::table('recipe_lines', function (Blueprint $table) {
            // Remove the group_name column if it exists
            if (Schema::hasColumn('recipe_lines', 'group_name')) {
                $table->dropColumn('group_name');
            }

            // Add foreign key to recipe_groups
            $table->foreignId('recipe_group_id')->nullable()->constrained()->onDelete('set null');

            $table->index(['recipe_group_id', 'sort_order']);
        });
    }

    public function down()
    {
        Schema::table('recipe_lines', function (Blueprint $table) {
            $table->dropForeign(['recipe_group_id']);
            $table->dropColumn('recipe_group_id');
            $table->string('group_name')->nullable();
        });
    }
}
