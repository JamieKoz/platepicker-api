<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InsertRecipes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insert:recipes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert recipes from JSON file into the database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $filePath = storage_path('app/data.json');

        // Check if the file exists
        if (!file_exists($filePath)) {
            $this->error('JSON file not found at: ' . $filePath);
            return;
        }

        // Read the JSON file
        $json = file_get_contents($filePath);
        if ($json === false) {
            $this->error('Failed to read JSON file.');
            return;
        }

        // Decode the JSON data
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Failed to decode JSON data: ' . json_last_error_msg());
            return;
        }

        // Check if the data is an array
        if (!is_array($data)) {
            $this->error('JSON data is not an array.');
            return;
        }

        // Iterate through the data and insert into the database
        foreach ($data as $record) {
            DB::table('recipes')->insert([
                'title' => $record['Title'],
                'ingredients' => json_encode($record['Ingredients']),
                'instructions' => $record['Instructions'],
                'image_name' => $record['Image_Name'],
            ]);
        }

        $this->info('Recipes inserted successfully!');
    }
}
