<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\RestaurantService;

class RestaurantController extends Controller
{
    private $restaurantService;
    private const CACHE_DURATION = 86400; // 24 hours
    private const PHOTOS_CACHE_DURATION = 86400; // 24 hours

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
        $restaurants = $this->restaurantService->fetchAndProcessRestaurants(
            (string)$location['lat'],
            (string)$location['lng']
        );

        Cache::put($cacheKey, $restaurants, now()->addSeconds(self::CACHE_DURATION));

        return response()->json($restaurants);
    }

    /**
     * Get additional photos for a restaurant
     */
    public function getRestaurantPhotos(string $placeId)
    {
        $cacheKey = "restaurant_detailed_photos_{$placeId}";

        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $photos = $this->restaurantService->fetchAdditionalPhotos($placeId);

        // Convert photo references to URLs
        $photoUrls = collect($photos)->map(function ($photo) {
            return [
                'url' => $this->getPhotoUrl($photo['photo_reference']),
                'width' => $photo['width'] ?? 0,
                'height' => $photo['height'] ?? 0,
                'attributions' => $photo['html_attributions'] ?? []
            ];
        })->values()->all();

        $result = [
            'place_id' => $placeId,
            'photos' => $photoUrls
        ];

        Cache::put($cacheKey, $result, now()->addSeconds(self::PHOTOS_CACHE_DURATION));

        return response()->json($result);
    }

    /**
     * Build a photo URL from a photo reference
     */
    private function getPhotoUrl(string $photoReference, int $maxWidth = 400): string
    {
        return 'https://maps.googleapis.com/maps/api/place/photo?' . http_build_query([
            'maxwidth' => $maxWidth,
            'photo_reference' => $photoReference,
            'key' => config('services.google.maps_api_key')
        ]);
    }
}
