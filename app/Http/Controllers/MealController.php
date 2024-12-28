<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;

class MealController extends Controller
{
    public function showMeal(Request $request)
    {
        // Retrieve the blocklist from the session or initialize it as an empty array
        // TODO: Move blocklist to database
        // Make blocklist editable for the user
        $blocklist = session('blocklist', []);
        $category = $request->input('category', 'all');
        $client = new Client();

        $meal1 = $this->getMeal($client, $blocklist, $category);
        $meal2 = $this->getMeal($client, $blocklist, $category, $meal1['strMeal']);

        return view('meal', [
            'meal1' => $meal1,
            'meal2' => $meal2,
            'blocklist' => $blocklist,
            'selectedCategory' => $category
        ]);
    }

    public function chooseMeal(Request $request)
    {
        $blocklist = session('blocklist', []);
        $chosenMeal = $request->input('chosen_meal');

        // Add the chosen meal to the blocklist
        $blocklist[] = $chosenMeal;

        // Store the updated blocklist in the session
        session(['blocklist' => $blocklist]);

        return redirect()->route('meal', ['category' => $request->input('category')]);
    }

    private function getMeal($client, $blocklist, $category, $excludedMeal = null)
    {
        do {
            $response = $client->get('https://www.themealdb.com/api/json/v1/1/random.php');
            $mealData = json_decode($response->getBody(), true);
            $meal = $mealData['meals'][0];
        } while (
            in_array($meal['strMeal'], $blocklist) ||
            ($category == 'Dessert' && $meal['strCategory'] != 'Dessert') ||
            ($category != 'Dessert' && $meal['strCategory'] == 'Dessert') ||
            ($excludedMeal && $meal['strMeal'] == $excludedMeal)
        );

        return $meal;
    }
}
