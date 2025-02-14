<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class RestaurantService
{
    private const TARGET_RESULTS = 27;
    private const MAX_PAGES = 3;

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
                sleep(1); // Google needs time to prepare the next page
            }
        } while (
            $nextPageToken !== null &&
            $pageCount < self::MAX_PAGES &&
            $processedResults->count() < self::TARGET_RESULTS
        );

        return $this->enrichWithPhotos($processedResults)
            ->take(self::TARGET_RESULTS)
            ->shuffle()
            ->values();
    }

    private function fetchPage(string $lat, string $lng, ?string $pageToken = null): array
    {
        $cacheKey = "get_geocode_{$lat}_{$lng}" . ($pageToken ? "_page_" . substr($pageToken, 0, 10) : "");

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Base parameters
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
        });
    }

}
