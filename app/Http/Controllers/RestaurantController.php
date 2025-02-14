<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\RestaurantService;

class RestaurantController extends Controller
{
    private $restaurantService;
    private const CACHE_DURATION = 3600; // 1 hour

    public function __construct(RestaurantService $restaurantService)
    {
        $this->restaurantService = $restaurantService;

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
        $cacheKey = "reverse_geocode_{$lat}_{$lng}";

        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $restaurants = $this->restaurantService->fetchAndProcessRestaurants($lat, $lng);
        Cache::put($cacheKey, $restaurants, now()->addSeconds(self::CACHE_DURATION));

        return response()->json($restaurants);
    }
    // @TODO Better pattern match for duplicate restaurants.
    // Would be ideal to check if the vicinity is in the name then to remove it
    // and then hash the name. Hashes might be quicker to match.
    // So for example we got 'Red Rooster Gladstone Park' and 'Gladstone park' is
    // in the objects vicinity property, then we hash 'Red Rooster' and we can then check for that
    // This func has become a bit of a mess.
    // 1. We get results from all pages until we have enough results in our response.
    // 2. We filter out garbage results where we can like hotels.
    // 3. We blacklist duplicates from the results so Subway doesnt come up 10 times and we only use the nearest.
    // 4. We merge the image results together with the restaurant so we can have 5 images per place.
    // 5. We cache the result so this doesn't take 10 years to retrieve.

    public function getNearbyRestaurants(Request $request)
    {
        $placeId = $request->query('place_id');
        $cacheKey = "restaurants_{$placeId}";

        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

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

        $restaurants = $this->restaurantService->fetchAndProcessRestaurants($location['lat'], $location['lng']);

        Cache::put($cacheKey, $restaurants, now()->addSeconds(self::CACHE_DURATION));

        return response()->json($restaurants);
    }
}
