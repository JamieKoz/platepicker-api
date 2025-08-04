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

    public function getNearbyRestaurants(Request $request)
    {
        $placeId = $request->query('place_id');
        $diningOption = $request->query('dining_option', '');
        $keyword = $request->query('keyword');
        $dietary = $request->query('dietary');

        $cacheKey = "restaurants_{$placeId}_{$diningOption}";
        if ($keyword) {
            $cacheKey .= "_" . md5($keyword);
        }

        if ($dietary) {
            $cacheKey .= "_dietary_" . md5($dietary);
        }

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
        $dietaryArray = $dietary ? explode(',', $dietary) : [];
        $restaurants = $this->restaurantService->fetchAndProcessRestaurants(
            (string)$location['lat'],
            (string)$location['lng'],
            $diningOption,
            $keyword,
            $dietaryArray
        );

        // Convert collection to array before processing
        $processedRestaurants = [];

        foreach ($restaurants as $restaurant) {
            $processedRestaurant = $restaurant;

            // Add a flag to indicate if the restaurant has photos
            $processedRestaurant['has_additional_photos'] =
            isset($restaurant['photos']) && !empty($restaurant['photos']);

            // Include only the primary photo for immediate display
            if (isset($restaurant['photos']) && !empty($restaurant['photos'])) {
                // Create a copy of the photos array
                $photos = $restaurant['photos'];
                $primaryPhoto = array_shift($photos);

                $processedRestaurant['primary_photo'] = $primaryPhoto;

                // Keep the original photos array in place for additional photos endpoint
                $processedRestaurant['photos'] = $restaurant['photos'];
            }

            $processedRestaurants[] = $processedRestaurant;
        }

        Cache::put($cacheKey, $processedRestaurants, now()->addSeconds(self::CACHE_DURATION));
        return response()->json($processedRestaurants);
    }

    public function reverseGeocode(Request $request)
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $diningOption = $request->query('dining_option', '');
        $dietary = $request->query('dietary');

        $keyword = $request->query('keyword');
        if (!$lat || !$lng) {
            return response()->json([
                'error' => 'Latitude and longitude are required'
            ], 400);
        }

        $cacheKey = "restaurants_geocode_{$lat}_{$lng}_{$diningOption}";
        if ($keyword) {
            $cacheKey .= "_" . md5($keyword);
        }
        if ($dietary) {
            $cacheKey .= "_dietary_" . md5($dietary);
        }
        if (Cache::has($cacheKey)) {
            return response()->json(Cache::get($cacheKey));
        }

        $dietaryArray = $dietary ? explode(',', $dietary) : [];
        $restaurants = $this->restaurantService->fetchAndProcessRestaurants(
            (string)$lat,
            (string)$lng,
            $diningOption,
            $keyword,
            $dietaryArray
        );

        // Convert collection to array before processing
        $processedRestaurants = [];

        foreach ($restaurants as $restaurant) {
            $processedRestaurant = $restaurant;

            // Add a flag to indicate if the restaurant has photos
            $processedRestaurant['has_additional_photos'] =
            isset($restaurant['photos']) && !empty($restaurant['photos']);

            // Include only the primary photo for immediate display
            if (isset($restaurant['photos']) && !empty($restaurant['photos'])) {
                // Create a copy of the photos array
                $photos = $restaurant['photos'];
                $primaryPhoto = array_shift($photos);

                $processedRestaurant['primary_photo'] = $primaryPhoto;

                // Keep the original photos array in place for additional photos endpoint
                $processedRestaurant['photos'] = $restaurant['photos'];
            }

            $processedRestaurants[] = $processedRestaurant;
        }

        Cache::put($cacheKey, $processedRestaurants, now()->addSeconds(self::CACHE_DURATION));
        return response()->json($processedRestaurants);
    }

    public function getRestaurantPhotos($placeId)
    {
        $cacheKey = "restaurant_photos_{$placeId}";

        if (Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            return response()->json($cachedData);
        }

        // Get detailed place information with photos
        $placeResponse = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'photos',
            'key' => config('services.google.maps_api_key')
        ]);

        $placeData = $placeResponse->json();

        if (!isset($placeData['result']['photos'])) {
            return response()->json([
                'photos' => []
            ]);
        }

        $photos = [];
        $limit = 4; // Limit to 4 photos
        $count = 0;

        foreach ($placeData['result']['photos'] as $photo) {
            if (isset($photo['photo_reference'])) {
                $photos[] = [
                    'reference' => $photo['photo_reference'], // Note: using 'reference' not 'photo_reference'
                    'width' => $photo['width'] ?? 400,
                    'height' => $photo['height'] ?? 400
                ];

                $count++;
                if ($count >= $limit) {
                    break;
                }
            }
        }

        $response = [
            'photos' => $photos
        ];


        Cache::put($cacheKey, $response, now()->addDay());
        return response()->json($response);
    }

    public function getPhotoProxy(Request $request)
    {
        $photoReference = $request->query('photo_reference');
        $maxWidth = $request->query('maxwidth', 800);
        $maxHeight = $request->query('maxheight', 600);

        if (!$photoReference) {
            return response()->json(['error' => 'Photo reference is required'], 400);
        }

        $cacheKey = "photo_proxy_{$photoReference}_{$maxWidth}_{$maxHeight}";

        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            return response($cachedResponse['data'], 200, [
                'Content-Type' => $cachedResponse['content_type'],
                'Cache-Control' => 'public, max-age=86400'
            ]);
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/place/photo', [
                'maxwidth' => $maxWidth,
                'maxheight' => $maxHeight,
                'photo_reference' => $photoReference,
                'key' => config('services.google.maps_api_key')
            ]);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type');
                $data = $response->body();

                // Cache the photo for faster retrieval next time
                Cache::put($cacheKey, [
                    'data' => $data,
                    'content_type' => $contentType
                ], now()->addDays(7));


                return response($data, 200, [
                    'Content-Type' => $contentType,
                    'Cache-Control' => 'public, max-age=86400'
                ]);
            } else {

                // Return a fallback image or error
                return response()->json(['error' => 'Unable to fetch photo'], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching photo'], 500);
        }
    }

    public function getRestaurantDetails($placeId)
    {
        try {
            $details = $this->restaurantService->getRestaurantDetails($placeId);
            return response()->json($details);
        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Failed to fetch restaurant details'
            ], 500);
        }
    }
}
