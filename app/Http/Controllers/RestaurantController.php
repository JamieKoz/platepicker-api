<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class RestaurantController extends Controller
{
    public function getNearbyRestaurants(Request $request)
    {
        $placeId = $request->query('place_id');
        $cacheKey = "restaurants_{$placeId}";
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }
        // First get the place details
        $placeResponse = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'geometry', // Only get the geometry
            'key' => config('services.google.maps_api_key')
        ]);


        $placeData = $placeResponse->json();

        if (!isset($placeData['result']['geometry']['location'])) {
            return response()->json([
                'error' => 'Location not found',
                'debug' => $placeData // Include debug info
            ], 404);
        }

        $location = $placeData['result']['geometry']['location'];

        // Then search for nearby restaurants
        $placesResponse = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
            'location' => $location['lat'] . ',' . $location['lng'],
            'radius' => 10000,
            'types' => 'restaurant',
            /* 'rankby' => 'rating', */
            'key' => config('services.google.maps_api_key')
        ]);


        if (!isset($placesResponse->json()['results'])) {
            return response()->json([
                'error' => 'No restaurants found',
                'debug' => $placesResponse->json()
            ], 404);
        }

        $restaurants = $placesResponse->json()['results'];

        $restaurants = collect($restaurants)
            ->take(54)
            ->map(function ($restaurant) {
                return [
                    'place_id' => $restaurant['place_id'],
                    'name' => $restaurant['name'],
                    'vicinity' => $restaurant['vicinity'],
                    'rating' => $restaurant['rating'] ?? null,
                    'user_ratings_total' => $restaurant['user_ratings_total'] ?? 0,
                    'price_level' => $restaurant['price_level'] ?? null,
                    'photos' => $restaurant['photos'] ?? [],
                    'opening_hours' => $restaurant['opening_hours'] ?? null,
                ];
            })
            ->values();
        Cache::put($cacheKey, $restaurants, now()->addHour());

        return response()->json($restaurants);
    }

    public function getAddressSuggestions(Request $request)
    {
        $query = $request->query('input');
        $cacheKey = "address_suggestions_{$query}";

        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }
        $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input' => $query,
            'types' => 'address',
            'key' => config('services.google.maps_api_key')
        ]);
        $results = $response->json();
        Cache::put($cacheKey, $results, now()->addHour());
        return response()->json($results);
    }

    public function reverseGeocode(Request $request)
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        $cacheKey = "geocode_{$lat}_{$lng}";
        if(Cache::has($cacheKey)){
            return response()->json(Cache::get($cacheKey));
            }

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => "{$lat},{$lng}",
            'key' => config('services.google.maps_api_key')
        ]);

        $data = $response->json();

        if (!empty($data['results'])) {
            // Get the first result as it's usually the most accurate
            $result = $data['results'][0];
            $responseData = [
                'place_id' => $result['place_id'],
                'description' => $result['formatted_address']
            ];
            Cache::put($cacheKey, $responseData, now()->addHour());

            return response()->json($responseData);
        }

        return response()->json([
            'error' => 'No address found'
        ], 404);
    }
}
