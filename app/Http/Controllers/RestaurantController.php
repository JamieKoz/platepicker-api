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
    private const MAX_PHOTOS = 5; // Maximum photos to return

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

        // Pre-process restaurants to include a primary photo directly
        foreach ($restaurants as $index => $restaurant) {
            if (isset($restaurant['photos']) && !empty($restaurant['photos'])) {
                $restaurants[$index]['primary_photo'] = array_shift($restaurant['photos']);
            }
        }

        Cache::put($cacheKey, $restaurants, now()->addSeconds(self::CACHE_DURATION));

        return response()->json($restaurants);
    }

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
        $restaurants = $this->restaurantService->fetchAndProcessRestaurants(
            (string)$location['lat'],
            (string)$location['lng']
        );

        // Pre-process restaurants to include a primary photo directly
        foreach ($restaurants as $index => $restaurant) {
            if (isset($restaurant['photos']) && !empty($restaurant['photos'])) {
                $restaurants[$index]['primary_photo'] = array_shift($restaurant['photos']);
            }
        }

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

        // Limit to maximum photos
        $photos = array_slice($photos, 0, self::MAX_PHOTOS);

        // Convert photo references to URLs with optimized sizes
        $photoUrls = collect($photos)->map(function ($photo) {
            return [
                'url' => $this->getPhotoUrl($photo['photo_reference'], 400, 300), // Smaller size
                'width' => $photo['width'] ?? 400,
                'height' => $photo['height'] ?? 300,
                'photo_reference' => $photo['photo_reference'] ?? '',
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
    private function getPhotoUrl(string $photoReference, int $maxWidth = 800, int $maxHeight = 350): string
    {
        if (strpos($photoReference, 'maps.googleapis.com/maps/api/place/photo') !== false) {
            // Extract the actual photo reference from the URL
            $matches = [];
            if (preg_match('/photo_reference=([^&]+)/', $photoReference, $matches)) {
                $photoReference = urldecode($matches[1]);
            }
        }

        // Otherwise build the Places API URL
        return 'https://maps.googleapis.com/maps/api/place/photo?' . http_build_query([
            'maxwidth' => $maxWidth,
            'maxheight' => $maxHeight,
            'photo_reference' => $photoReference,
            'key' => config('services.google.maps_api_key')
        ]);
    }

    public function getPhotoProxy(Request $request)
    {
        $photoReference = $request->query('photo_reference');
        $maxWidth = $request->query('maxwidth', 800);
        $maxHeight = $request->query('maxheight', 350);

        if (!$photoReference) {
            return response()->json(['error' => 'Photo reference is required'], 400);
        }

        // Generate a unique cache key for this photo
        $cacheKey = "photo_proxy_" . md5($photoReference . $maxWidth . $maxHeight);

        // Try to get from cache first
        if (Cache::has($cacheKey)) {
            $cachedPhoto = Cache::get($cacheKey);
            return response($cachedPhoto['data'])
                ->header('Content-Type', $cachedPhoto['content_type'])
                ->header('Cache-Control', 'public, max-age=86400');
        }

        $photoUrl = "https://maps.googleapis.com/maps/api/place/photo?" . http_build_query([
            'maxwidth' => $maxWidth,
            'maxheight' => $maxHeight,
            'photo_reference' => $photoReference,
            'key' => config('services.google.maps_api_key')
        ]);

        try {
            $response = Http::withOptions([
                'allow_redirects' => true,
                'timeout' => 5 // 5 second timeout
            ])
                ->withHeaders(['Accept' => 'image/*'])
                ->get($photoUrl);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type');
                $photoData = $response->body();

                // Store in cache
                Cache::put($cacheKey, [
                    'data' => $photoData,
                    'content_type' => $contentType
                ], now()->addDays(7)); // Cache for 7 days

                return response($photoData)
                    ->header('Content-Type', $contentType)
                    ->header('Cache-Control', 'public, max-age=604800'); // 7 days
            } else {
                return response()->json(['error' => 'Failed to fetch photo'], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
