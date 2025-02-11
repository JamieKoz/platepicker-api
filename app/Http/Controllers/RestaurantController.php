<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class RestaurantController extends Controller
{
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

        /* if (Cache::has($cacheKey)) { */
        /*     return response()->json(Cache::get($cacheKey)); */
        /* } */

        // Directly search for nearby restaurants
        $placesResponse = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
            'location' => "{$lat},{$lng}",
            'radius' => 12000,
            'type' => 'restaurant',
            'key' => config('services.google.maps_api_key')
        ]);

        if (!isset($placesResponse->json()['results'])) {
            return response()->json([
                'error' => 'No restaurants found'
            ], 404);
        }

        $restaurants = collect($placesResponse->json()['results'])
        ->filter(function ($restaurant) {
            $blacklist = ['bp', 'bp truckstop', '7-eleven', ];
            return !in_array(strtolower($restaurant['name']), $blacklist);
        })
            ->take(25)
            ->map(function ($restaurant) {
                // Get detailed place information for each restaurant
                $detailsResponse = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => $restaurant['place_id'],
                    'fields' => 'photos',
                    'key' => config('services.google.maps_api_key')
                ]);

                $details = $detailsResponse->json();

                // Combine photos from both sources and limit to 5
                $photos = collect($details['result']['photos'] ?? [])
                    ->merge($restaurant['photos'] ?? [])
                    ->unique('photo_reference')
                    ->take(5)
                    ->values()
                    ->all();

                return [
                    'place_id' => $restaurant['place_id'],
                    'name' => $restaurant['name'],
                    'vicinity' => $restaurant['vicinity'],
                    'rating' => $restaurant['rating'] ?? null,
                    'user_ratings_total' => $restaurant['user_ratings_total'] ?? 0,
                    'price_level' => $restaurant['price_level'] ?? null,
                    'photos' => $photos,
                    'opening_hours' => $restaurant['opening_hours'] ?? null,
                ];
            })
            ->shuffle()->values();

        Cache::put($cacheKey, $restaurants, now()->addHour());
        return response()->json($restaurants);
    }

    public function getNearbyRestaurants(Request $request)
    {
        $placeId = $request->query('place_id');
        $cacheKey = "restaurants_{$placeId}";

        /* if (Cache::has($cacheKey)) { */
        /*     return response()->json(Cache::get($cacheKey)); */
        /* } */

        // Get location first
        $placeResponse = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'geometry',
            'key' => config('services.google.maps_api_key')
        ]);

        $placeData = $placeResponse->json();

        if (!isset($placeData['result']['geometry']['location'])) {
            return response()->json([
                'error' => 'Location not found'
            ], 404);
        }

        $location = $placeData['result']['geometry']['location'];

        // Get nearby restaurants
        $placesResponse = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
            'location' => $location['lat'] . ',' . $location['lng'],
            'radius' => 12000,
            'type' => 'restaurant',
            'key' => config('services.google.maps_api_key')
        ]);

        if (!isset($placesResponse->json()['results'])) {
            return response()->json([
                'error' => 'No restaurants found'
            ], 404);
        }

        $restaurants = collect($placesResponse->json()['results'])
            ->filter(function ($restaurant) {
                $blacklist = ['bp', 'bp truckstop', 'station', 'convenience store'];
                return !in_array(strtolower($restaurant['name']), $blacklist);
            })
            ->take(25)
            ->map(function ($restaurant) {
                // Get detailed place information for each restaurant
                $detailsResponse = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                    'place_id' => $restaurant['place_id'],
                    'fields' => 'photos',
                    'key' => config('services.google.maps_api_key')
                ]);

                $details = $detailsResponse->json();

                $photos = collect($details['result']['photos'] ?? [])
                    ->merge($restaurant['photos'] ?? [])
                    ->unique('photo_reference')
                    ->take(5)
                    ->values()
                    ->all();

                return [
                    'place_id' => $restaurant['place_id'],
                    'name' => $restaurant['name'],
                    'vicinity' => $restaurant['vicinity'],
                    'rating' => $restaurant['rating'] ?? null,
                    'user_ratings_total' => $restaurant['user_ratings_total'] ?? 0,
                    'price_level' => $restaurant['price_level'] ?? null,
                    'photos' => $photos,
                    'opening_hours' => $restaurant['opening_hours'] ?? null,
                ];
            })
            ->shuffle()->values();

        Cache::put($cacheKey, $restaurants, now()->addHour());
        return response()->json($restaurants);
    }
}
