<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RestaurantService
{
    private const TARGET_RESULTS = 27;
    private const MAX_PAGES = 3;
    private const FULL_PHOTO_COUNT = 5; // Maximum photos to fetch per restaurant

    private array $blacklistedTerms = [
        'bp',
        'bp truckstop',
        '7-eleven',
        'hotel',
        'motel',
        'lodge',
        'airport',
        'convenience store',
        'gas station',
        'service station',
        'childcare',
        'child care',
        'day care',
        'daycare'
    ];

    private array $validRestaurantTypes = ['restaurant', 'cafe', 'meal_takeaway', 'food', 'hamburger', 'greek'];

    /**
     * Fetch and process restaurants with minimal image data initially
     */
    public function fetchAndProcessRestaurants(string $lat, string $lng, string $diningOption = 'delivery'): array
    {
        // Maximum number of restaurants to return
        $MAX_RESTAURANTS = 25;

        $allResults = [];
        $pageToken = null;
        $maxPages = 2; // Limit to 2 pages to avoid excessive API calls

        for ($i = 0; $i < $maxPages; $i++) {
            $response = $this->fetchPage($lat, $lng, $diningOption, $pageToken);

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

            // Transform the data into our desired format
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

            // Log the raw response for debugging
            Log::debug('Place details response', [
                'place_id' => $placeId,
                'response' => $details
            ]);

            if (!isset($details['result']['photos']) || empty($details['result']['photos'])) {
                // Log missing photos and return empty array
                Log::info('No photos found for place', ['place_id' => $placeId]);
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

            Cache::put($cacheKey, $photoReferences, now()->addDay());
            return $photoReferences;
        } catch (\Exception $e) {
            Log::error('Error fetching additional photos', [
                'place_id' => $placeId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function fetchPage(string $lat, string $lng, string $diningOption = 'delivery', ?string $pageToken = null): array
    {
        $cacheKey = "get_geocode_{$lat}_{$lng}_{$diningOption}" . ($pageToken ? "_page_" . substr($pageToken, 0, 10) : "");
        /* if (Cache::has($cacheKey)) { */
        /*     return Cache::get($cacheKey); */
        /* } */

        // Determine keywords based on dining option
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

        $params = [
            'location' => "{$lat},{$lng}",
            'type' => $searchType,
            'rankby' => 'distance',
            'keyword' => $keyword,
            'key' => config('services.google.maps_api_key')
        ];

        // Add page token if we have one
        if ($pageToken) {
            $params['pagetoken'] = $pageToken;
        }

        // Log the request for debugging
        Log::info('Fetching restaurants with params', ['dining_option' => $diningOption, 'keyword' => $keyword]);

        $response = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', $params);
        $googleResponse = $response->json();

        // Only cache successful responses
        if ($googleResponse['status'] === 'OK') {
            Cache::put($cacheKey, $googleResponse, now()->addHour());
        } else {
            Log::error('Google Places API Error', [
                'status' => $googleResponse['status'] ?? 'unknown',
                'error_message' => $googleResponse['error_message'] ?? 'No error message provided',
                'dining_option' => $diningOption
            ]);
        }

        return $googleResponse;
    }

    private function filterValidRestaurants(array $results): Collection
    {
        return collect($results)->filter(function ($restaurant) {
            return $this->isValidRestaurantType($restaurant) && !$this->containsBlacklistedTerm($restaurant);
        });
    }

    private function isValidRestaurantType(array $restaurant): bool
    {
        return collect($restaurant['types'] ?? [])->contains(function ($type) {
            return in_array($type, $this->validRestaurantTypes);
        });
    }

    private function containsBlacklistedTerm(array $restaurant): bool
    {
        return collect($this->blacklistedTerms)->contains(function ($term) use ($restaurant) {
            return str_contains(strtolower($restaurant['name']), $term);
        });
    }

    private function removeDuplicates(Collection $restaurants): Collection
    {
        $uniqueRestaurants = collect([]);
        $blocklist = [];

        foreach ($restaurants as $restaurant) {
            $name = $restaurant['name'];
            $vicinity = $restaurant['vicinity'];

            // Strip vicinity from name if present
            $strippedName = $this->stripVicinityFromName($name, $vicinity);
            $hash = md5(strtolower($strippedName));

            if (!in_array($hash, $blocklist)) {
                $blocklist[] = $hash;
                $uniqueRestaurants->push($restaurant);
            }
        }
        return $uniqueRestaurants;
    }

    private function stripVicinityFromName(string $name, string $vicinity): string
    {
        // Split vicinity into words and remove each from the name if present
        $vicinityParts = explode(' ', $vicinity);
        $cleanName = $name;

        foreach ($vicinityParts as $part) {
            $cleanName = str_ireplace($part, '', $cleanName);
        }

        return trim($cleanName);
    }

    /**
     * Add just ONE photo per restaurant for initial fast response
     */
    private function enrichWithInitialPhoto(Collection $restaurants): Collection
    {
        return $restaurants->map(function ($restaurant) {
            // Use photo from the initial API response if available
            $initialPhoto = isset($restaurant['photos'][0]) ? $restaurant['photos'][0] : null;

            // Check if we need to indicate more photos are available
            $hasMorePhotos = count($restaurant['photos'] ?? []) > 1;

            return [
                'place_id' => $restaurant['place_id'],
                'name' => $restaurant['name'],
                'vicinity' => $restaurant['vicinity'],
                'rating' => $restaurant['rating'] ?? null,
                'user_ratings_total' => $restaurant['user_ratings_total'] ?? 0,
                'price_level' => $restaurant['price_level'] ?? null,
                'primary_photo' => $initialPhoto, // Just include one photo initially
                'has_additional_photos' => $hasMorePhotos, // Flag to indicate more photos exist
                'opening_hours' => $restaurant['opening_hours'] ?? null,
            ];
        });
    }

    /**
     * Original full enrichment with photos - use this for the additional photos endpoint
     */
    private function enrichWithPhotos(Collection $restaurants): Collection
    {
        return $restaurants->map(function ($restaurant) {
            $details = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $restaurant['place_id'],
                'fields' => 'photos',
                'key' => config('services.google.maps_api_key')
            ])->json();

            $photos = collect($details['result']['photos'] ?? [])
                ->merge($restaurant['photos'] ?? [])
                ->unique('photo_reference')
                ->take(self::FULL_PHOTO_COUNT)
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
        });
    }
}
