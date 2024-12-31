<?php
namespace FriendsOfRedaxo\AssetImport\Provider;

use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;
use Psr\Log\LogLevel;

class PexelsProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://api.pexels.com/v1/';
    protected string $apiUrlVideos = 'https://api.pexels.com/videos/';
    protected int $itemsPerPage = 20;

    public function getName(): string
    {
        return 'pexels';
    }

    public function getTitle(): string
    {
        return 'Pexels';
    }

    public function getIcon(): string
    {
        return 'fa-camera';
    }

    public function getDefaultOptions(): array
    {
        return [
            'type' => 'image'
        ];
    }

    public function isConfigured(): bool
    {
        return isset($this->config['apikey']) && !empty($this->config['apikey']);
    }

    public function getConfigFields(): array
    {
        return [
            [
                'label' => 'asset_import_provider_pexels_apikey',
                'name' => 'apikey',
                'type' => 'text',
                'notice' => 'asset_import_provider_pexels_apikey_notice'
            ]
        ];
    }

    /**
     * Extract image/video ID from Pexels URL
     */
    protected function extractIdFromUrl(string $url): ?int
    {
        // Match URLs like:
        // https://www.pexels.com/photo/brown-rocks-during-golden-hour-2014422/
        // https://www.pexels.com/video/drone-view-of-a-city-3129957/
        if (preg_match('#pexels\.com/(?:photo|video)/[^/]+-(\d+)/?$#i', $url, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Check if input is a Pexels URL
     */
    protected function isPexelsUrl(string $query): bool
    {
        return (bool)preg_match('#^https?://(?:www\.)?pexels\.com/(?:photo|video)/#i', $query);
    }

    /**
     * Get single photo by ID
     */
    protected function getPhotoById(int $id): ?array
    {
        $url = $this->apiUrl . 'photos/' . $id;
        return $this->makeApiRequest($url);
    }

    /**
     * Get single video by ID
     */
    protected function getVideoById(int $id): ?array
    {
        $url = $this->apiUrlVideos . '/videos/' . $id;
        return $this->makeApiRequest($url);
    }

    protected function searchApi(string $query, int $page = 1, array $options = []): array
    {
        try {
            if (!$this->isConfigured()) {
                throw new \rex_exception('Pexels API key not configured');
            }

            // Check if query is a Pexels URL
            if ($this->isPexelsUrl($query)) {
                $id = $this->extractIdFromUrl($query);
                if ($id) {
                    // Try photo first
                    $item = $this->getPhotoById($id);
                    $type = 'image';
                    
                    // If not found, try video
                    if (!$item) {
                        $item = $this->getVideoById($id);
                        $type = 'video';
                    }
                    
                    if ($item) {
                        $formattedItem = $this->formatItem($item, $type);
                        return [
                            'items' => [$formattedItem],
                            'total' => 1,
                            'page' => 1,
                            'total_pages' => 1
                        ];
                    }
                }
                // If URL parsing failed, fall back to normal search
            }

            $type = $options['type'] ?? 'image';
            $results = [];
            $totalHits = 0;

            // Search for images
            if ($type === 'all' || $type === 'image') {
                $imageResults = $this->makeApiRequest(
                    $this->apiUrl . 'search',
                    [
                        'query' => $query,
                        'page' => $page,
                        'per_page' => $type === 'all' ? intval($this->itemsPerPage / 2) : $this->itemsPerPage
                    ]
                );

                if ($imageResults && isset($imageResults['photos'])) {
                    $results = array_map(
                        fn($item) => $this->formatItem($item, 'image'),
                        $imageResults['photos']
                    );
                    $totalHits = $imageResults['total_results'];
                }
            }

            // Search for videos
            if ($type === 'all' || $type === 'video') {
                $videoResults = $this->makeApiRequest(
                    $this->apiUrlVideos . 'search',
                    [
                        'query' => $query,
                        'page' => $page,
                        'per_page' => $type === 'all' ? intval($this->itemsPerPage / 2) : $this->itemsPerPage
                    ]
                );

                if ($videoResults && isset($videoResults['videos'])) {
                    $videoItems = array_map(
                        fn($item) => $this->formatItem($item, 'video'),
                        $videoResults['videos']
                    );
                    
                    if ($type === 'all') {
                        $results = array_merge($results, $videoItems);
                        $totalHits = intval(($totalHits + $videoResults['total_results']) / 2);
                    } else {
                        $results = $videoItems;
                        $totalHits = $videoResults['total_results'];
                    }
                }
            }

            // Ensure we never return more than itemsPerPage results
            $results = array_slice($results, 0, $this->itemsPerPage);

            return [
                'items' => $results,
                'total' => $totalHits,
                'page' => $page,
                'total_pages' => ceil($totalHits / $this->itemsPerPage)
            ];
            
        } catch (\Exception $e) {
            \rex_logger::factory()->log(LogLevel::ERROR, 'Exception in searchApi: {message}', ['message' => $e->getMessage()], __FILE__, __LINE__);
            return [];
        }
    }

    /**
     * Format API item to standardized response
     */
    protected function formatItem(array $item, string $type): array
    {
        if ($type === 'video') {
            $sizes = [];
            foreach ($item['video_files'] as $file) {
                $quality = $this->getVideoQualityLabel($file);
                if ($quality) {
                    $sizes[$quality] = ['url' => $file['link']];
                }
            }

            return [
                'id' => $item['id'],
                'preview_url' => $item['image'], // Thumbnail image
                'title' => $item['url'], // Pexels doesn't provide tags, use URL as fallback
                'author' => $item['user']['name'],
                'type' => 'video',
                'size' => $sizes
            ];
        }
        
        return [
            'id' => $item['id'],
            'preview_url' => $item['src']['medium'],
            'title' => $item['url'], // Pexels doesn't provide tags, use URL as fallback
            'author' => $item['photographer'],
            'type' => 'image',
            'size' => [
                'preview' => ['url' => $item['src']['tiny']],
                'web' => ['url' => $item['src']['medium']],
                'large' => ['url' => $item['src']['large2x']],
                'original' => ['url' => $item['src']['original']]
            ]
        ];
    }

    /**
     * Get standardized quality label for video
     */
    protected function getVideoQualityLabel(array $file): ?string
    {
        $height = $file['height'];
        $quality = $file['quality'];

        if ($quality === 'hd' && $height >= 720) {
            return 'large';
        } elseif ($height >= 480) {
            return 'medium';
        } elseif ($height >= 360) {
            return 'small';
        } elseif ($height < 360) {
            return 'tiny';
        }

        return null;
    }

    /**
     * Make API request to Pexels
     */
    protected function makeApiRequest(string $url, array $params = []): ?array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        
        \rex_logger::factory()->log(LogLevel::INFO, 'Pexels API URL: {url}', ['url' => $url], __FILE__, __LINE__);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->config['apikey']
            ]
        ]);

        $response = curl_exec($ch);
        
        if ($response === false) {
            $errorMessage = 'Curl error: ' . curl_error($ch) . ' - URL: ' . $url;
            \rex_logger::factory()->log(LogLevel::ERROR, 'Curl Error: {message}', ['message' => $errorMessage], __FILE__, __LINE__);
            curl_close($ch);
            return null;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $errorMessage = 'Invalid HTTP response code: ' . $httpCode . ' - URL: ' . $url;
            \rex_logger::factory()->log(LogLevel::ERROR, 'HTTP Error: {message}', ['message' => $errorMessage], __FILE__, __LINE__);
            return null;
        }

        $data = json_decode($response, true);
        if ($data === null) {
            $errorMessage = 'Invalid JSON response from Pexels API - URL: ' . $url;
            \rex_logger::factory()->log(LogLevel::ERROR, 'Invalid API Response: {message}', ['message' => $errorMessage], __FILE__, __LINE__);
            return null;
        }

        return $data;
    }

    public function import(string $url, string $filename): bool
    {
        if (!$this->isConfigured()) {
            throw new \rex_exception('Pexels API key not configured');
        }

        $filename = $this->sanitizeFilename($filename);
        
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$extension) {
            // Determine extension based on URL or Content-Type
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true
            ]);
            $header = curl_exec($ch);
            curl_close($ch);
            
            if (preg_match('/Content-Type: image\/(\w+)/i', $header, $matches)) {
                $extension = $matches[1];
            } else {
                $extension = 'jpg'; // Fallback
            }
        }
        
        $filename = $filename . '.' . $extension;

        return $this->downloadFile($url, $filename);
    }
}
