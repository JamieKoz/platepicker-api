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
    public function fetchAndProcessRestaurants(string $lat, string $lng): Collection
    {
        $processedResults = collect([]);
        $pageCount = 0;
        $nextPageToken = null;

        do {
            // Fetch the next page of results
            $pageResults = $this->fetchPage($lat, $lng, $nextPageToken);

            if (!isset($pageResults['results'])) {
                break;
            }

            // Process this page's results
            $filtered = $this->filterValidRestaurants($pageResults['results']);
            $processedResults = $this->removeDuplicates($processedResults->merge($filtered));

            // Get next page token if available
            $nextPageToken = $pageResults['next_page_token'] ?? null;
            $pageCount++;

            // If we have a next page token and need more results, wait before next request
            if ($nextPageToken && $processedResults->count() < self::TARGET_RESULTS) {
                usleep(250000); // 250ms delay (reduced from 1s to speed up response)
            }
        } while (
            $nextPageToken !== null &&
            $pageCount < self::MAX_PAGES &&
            $processedResults->count() < self::TARGET_RESULTS
        );

        // Return restaurants with just ONE photo each for fast initial response
        return $this->enrichWithInitialPhoto($processedResults)
            ->take(self::TARGET_RESULTS)
            ->shuffle()
            ->values();
    }

    /**
     * Fetch additional photos for a specific restaurant
     */
    public function fetchAdditionalPhotos(string $placeId): array
{
    $cacheKey = "restaurant_photos_{$placeId}";

    /* if (Cache::has($cacheKey)) { */
    /*     return Cache::get($cacheKey); */
    /* } */

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

    private function fetchPage(string $lat, string $lng, ?string $pageToken = null): array
    {
        $cacheKey = "get_geocode_{$lat}_{$lng}" . ($pageToken ? "_page_" . substr($pageToken, 0, 10) : "");

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $params = [
            'location' => "{$lat},{$lng}",
            'type' => 'restaurant',
            'rankby' => 'distance',
            'key' => config('services.google.maps_api_key')
        ];

        // Add page token if we have one
        if ($pageToken) {
            $params['pagetoken'] = $pageToken;
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', $params);
        $googleResponse = $response->json();

        // Only cache successful responses
        if ($googleResponse['status'] === 'OK') {
            Cache::put($cacheKey, $googleResponse, now()->addHour());
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
