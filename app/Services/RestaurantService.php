<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RestaurantService
{
    private const FULL_PHOTO_COUNT = 5;

    /**
     * Fetch and process restaurants with minimal image data initially
     */
    public function fetchAndProcessRestaurants(string $lat, string $lng, string $diningOption = '', ?string $customKeyword = null): array
    {
        $MAX_RESTAURANTS = 25;

        $allResults = [];
        $pageToken = null;
        $maxPages = 2; // Limit to 2 pages to avoid excessive API calls

        for ($i = 0; $i < $maxPages; $i++) {
            $response = $this->fetchPage($lat, $lng, $diningOption, $customKeyword, $pageToken);

            if ($response['status'] !== 'OK') {
                break;
            }

            $allResults = array_merge($allResults, $response['results']);

            // If we already have enough restaurants, don't fetch more pages
            if (count($allResults) >= $MAX_RESTAURANTS) {
                Log::info("Reached max restaurant count ({$MAX_RESTAURANTS}), not fetching more pages");
                break;
            }

            // Check if there are more pages
            if (!isset($response['next_page_token'])) {
                break;
            }

            $pageToken = $response['next_page_token'];

            // Google requires a short delay before using the next page token
            sleep(2);
        }

        // Process and transform the results
        $restaurants = [];
        $count = 0;

        foreach ($allResults as $place) {
            // Skip places without a name or place_id
            if (!isset($place['name']) || !isset($place['place_id'])) {
                continue;
            }

            $restaurant = [
                'name' => $place['name'],
                'place_id' => $place['place_id'],
                'vicinity' => $place['vicinity'] ?? '',
                'rating' => $place['rating'] ?? 0,
                'user_ratings_total' => $place['user_ratings_total'] ?? 0,
                'price_level' => $place['price_level'] ?? 0,
                'photos' => [],
                'types' => $place['types'] ?? []
            ];

            // Process photos if available - store all photos for later use
            if (isset($place['photos']) && !empty($place['photos'])) {
                foreach ($place['photos'] as $photo) {
                    if (isset($photo['photo_reference'])) {
                        // Ensure the photo reference is valid
                        $photoRef = $photo['photo_reference'];
                        if (is_string($photoRef) && strlen($photoRef) > 10 && strlen($photoRef) < 400) {
                            $restaurant['photos'][] = [
                                'reference' => $photoRef,
                                'width' => $photo['width'] ?? 1000,
                                'height' => $photo['height'] ?? 600
                            ];

                            if (count($restaurant['photos']) >= 5) {
                                break;
                            }
                        } else {
                            Log::warning("Skipping invalid photo reference for restaurant {$place['place_id']}: " .
                                (is_string($photoRef) ? substr($photoRef, 0, 30) . "..." : "non-string"));
                        }
                    }
                }
            }

            $restaurants[] = $restaurant;
            $count++;

            // Limit to max restaurants
            if ($count >= $MAX_RESTAURANTS) {
                Log::info("Limiting to {$MAX_RESTAURANTS} restaurants (had " . count($allResults) . " total)");
                break;
            }
        }

        Log::info("Returning {$count} processed restaurants for {$diningOption} option");
        return $restaurants;
    }

    /**
     * Fetch additional photos for a specific restaurant
     */
    public function fetchAdditionalPhotos(string $placeId): array
    {
        $cacheKey = "restaurant_photos_{$placeId}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $details = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'fields' => 'photos',
                'key' => config('services.google.maps_api_key')
            ])->json();


            if (!isset($details['result']['photos']) || empty($details['result']['photos'])) {
                Cache::put($cacheKey, [], now()->addHour()); // Cache empty result for shorter time
                return [];
            }

            $photoReferences = collect($details['result']['photos'])
                ->take(self::FULL_PHOTO_COUNT)
                ->map(function ($photo) {
                    return [
                        'photo_reference' => $photo['photo_reference'],
                        'height' => $photo['height'] ?? 0,
                        'width' => $photo['width'] ?? 0,
                        'html_attributions' => $photo['html_attributions'] ?? []
                    ];
                })
                ->values()
                ->all();

            Cache::put($cacheKey, $photoReferences, now()->addMonth());
            return $photoReferences;
        } catch (\Exception $e) {
            Log::error('Error fetching additional photos', [
                'place_id' => $placeId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function fetchPage(string $lat, string $lng, string $diningOption = 'delivery', ?string $customKeyword = null, ?string $pageToken = null): array
    {
        $cacheKey = "get_geocode_{$lat}_{$lng}_{$diningOption}";
        if ($customKeyword) {
            $cacheKey .= "_" . md5($customKeyword);
        }
        if ($pageToken) {
            $cacheKey .= "_page_" . substr($pageToken, 0, 10);
        }

        if ($diningOption === 'custom' && $customKeyword) {
            $keyword = $customKeyword . ', restaurant, food';
            $searchType = 'restaurant';
        } else {
            $keyword = 'opennow';
            $searchType = 'restaurant';
            switch ($diningOption) {
                case 'bars':
                    $keyword = 'bar, pub, alcohol, opennow';
                    $searchType = 'bar';
                    break;
                case 'dine_in':
                    $keyword = 'food, fastfood, reservable, cafe, opennow';
                    break;
                case 'takeaway':
                    $keyword = 'delivery, fastfood, takeaway, opennow';
                    break;
                case 'drive_thru':
                    $keyword = 'delivery, fastfood, drivethru, opennow';
                    break;
                case 'delivery':
                default:
                    $keyword = 'delivery, opennow';
                    break;
            }
        }

        $params = [
            'location' => "{$lat},{$lng}",
            'type' => $searchType,
            'rankby' => 'distance',
            'keyword' => $keyword,
            'key' => config('services.google.maps_api_key')
        ];

        if ($pageToken) {
            $params['pagetoken'] = $pageToken;
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', $params);
        $googleResponse = $response->json();

        if ($googleResponse['status'] === 'OK') {
            Cache::put($cacheKey, $googleResponse, now()->addMonth());
        } else {
            Log::error('Google Places API Error', [
                'status' => $googleResponse['status'] ?? 'unknown',
                'error_message' => $googleResponse['error_message'] ?? 'No error message provided',
                'dining_option' => $diningOption
            ]);
        }

        return $googleResponse;
    }

    public function getRestaurantDetails(string $placeId): array
    {
        $cacheKey = "restaurant_details_{$placeId}";
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $placeId,
                'fields' => 'name,rating,formatted_phone_number,geometry,opening_hours,photos,reviews,website,formatted_address,price_level,user_ratings_total',
                'key' => config('services.google.maps_api_key')
            ]);
            $details = $response->json();
            if ($details['status'] === 'OK') {
                Cache::put($cacheKey, $details['result'], now()->addHours(6));
                return $details['result'];
            }
            throw new \Exception('Failed to fetch restaurant details');
        } catch (\Exception $e) {
            Log::error('Error fetching restaurant details', [
                'place_id' => $placeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
