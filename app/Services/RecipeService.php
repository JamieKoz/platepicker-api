<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class RecipeService
{
    public function getRecipeByName(string $name)
    {
        $recipe = Recipe::query()->where('title', $name)->get();

        return $recipe;
    }

    public function getRecipeByNameInactive(string $name)
    {
        $recipe = Recipe::query()->where('title', $name)->where('active', 1)->get();

        return $recipe;
    }

    public function getRandomRecipesActive($count = 27)
    {
        $recipes = Recipe::inRandomOrder()->where('active', 1)->take($count)->get();
        foreach ($recipes as $recipe) {
            $recipe->image_url = config('cloudfront.url') . '/food-images/' . $recipe->image_name;
        }
        return $recipes;
    }

    public function getRecipeList()
    {
        return Recipe::orderBy('title', 'asc')->paginate(25);
    }

    public function toggleStatus($mealId)
    {
        $recipe = Recipe::findOrFail($mealId);
        $recipe->active = !$recipe->active;
        $recipe->save();
        return $recipe;
    }

    public function search($searchTerm){

        return Recipe::orderBy('title', 'ASC')->where('title', 'LIKE', '%' . $searchTerm . '%')->paginate(10);
    }

        public function uploadImageToS3($file, $filename)
    {
        try {
            $path = Storage::disk('s3')->put(
                config('cloudfront.bucket_path'),
                $file,
                $filename,
                'private' // Changed to private since we're using CloudFront
            );

            return $this->getImageUrl($filename);
        } catch (\Exception $e) {
            Log::error('S3 upload failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function getImageUrl($filename)
    {
        if (empty($filename)) {
            return null;
        }

        $cloudFrontUrl = rtrim(config('cloudfront.url'), '/');
        $bucketPath = trim(config('cloudfront.bucket_path'), '/');

        return "{$cloudFrontUrl}/{$bucketPath}/{$filename}";
    }

}
