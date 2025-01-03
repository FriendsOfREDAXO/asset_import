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
        $isConfigured = isset($this->config) && 
                       is_array($this->config) && 
                       isset($this->config['apikey']) && 
                       !empty($this->config['apikey']);
        
        if (!$isConfigured) {
            \rex_logger::factory()->log(LogLevel::WARNING, 'Pexels provider not configured correctly. Config status: {status}', [
                'status' => [
                    'config_set' => isset($this->config),
                    'is_array' => isset($this->config) && is_array($this->config),
                    'has_apikey' => isset($this->config['apikey']),
                    'apikey_not_empty' => !empty($this->config['apikey'])
                ]
            ], __FILE__, __LINE__);
        }
        
        return $isConfigured;
    }

    public function getConfigFields(): array
    {
        return [
            [
                'label' => 'asset_import_provider_pexels_apikey',
                'name' => 'apikey',
                'type' => 'text',
                'notice' => 'asset_import_provider_pexels_apikey_notice'
            ],
            [
                'label' => 'asset_import_provider_pexels_copyright_format',
                'name' => 'copyright_format',
                'type' => 'select',
                'options' => [
                    ['value' => 'extended', 'label' => 'Extended (Photographer, License & Provider)'],
                    ['value' => 'simple', 'label' => 'Simple (Provider only)']
                ],
                'notice' => 'Format for copyright information'
            ]
        ];
    }

    public function getFieldMapping(): array
    {
        return [
            'photographer' => 'Photographer',
            'photographer_url' => 'Photographer Profile',
            'url' => 'Source URL',
            'type' => 'Media Type'
        ];
    }

    public function getCopyrightInfo(array $item): ?string
    {
            dump('getCopyrightInfo input:', $item); // DUMP 5

        $format = $this->config['copyright_format'] ?? 'extended';
        
        if ($format === 'simple') {
            return '© Pexels.com';
        }

        $originalItem = $this->getOriginalItemData($item);
        dump('Original item data:', $originalItem); // DUMP 6

        if (!$originalItem) {
            return '© Pexels.com';
        }

        $photographer = $originalItem['photographer'] ?? null;
        $photographerUrl = $originalItem['photographer_url'] ?? null;
        $sourceUrl = $originalItem['url'] ?? 'https://www.pexels.com';

        // Build copyright string
        $copyright = [];
        
        // Add photographer if available
        if ($photographer) {
            if ($photographerUrl) {
                $copyright[] = "© <a href=\"{$photographerUrl}\" target=\"_blank\">{$photographer}</a>";
            } else {
                $copyright[] = "© {$photographer}";
            }
        }
        
        // Add license and source
        $copyright[] = 'Pexels License';
        $copyright[] = "Source: <a href=\"{$sourceUrl}\" target=\"_blank\">Pexels.com</a>";

        return implode(' | ', $copyright);
    }

    protected function getOriginalItemData(array $item): ?array
    {
        try {
            if (isset($item['filename'])) {
                // Try to get ID from filename if it's a Pexels format
                if (preg_match('/-(\d+)\./', $item['filename'], $matches)) {
                    $id = $matches[1];
                    $type = strpos($item['filename'], 'video-') !== false ? 'video' : 'photo';
                    $apiItem = $this->getById((int)$id, $type);
                    if ($apiItem) {
                        return $apiItem;
                    }
                }
            }

            // If we have original_data, use that
            if (isset($item['original_data'])) {
                return $item['original_data'];
            }
        } catch (\Exception $e) {
            \rex_logger::logException($e);
        }
        
        return null;
    }

    protected function extractIdFromUrl(string $url): ?int
    {
        if (preg_match('#pexels\.com/(?:photo|video)/[^/]+-(\d+)/?$#i', $url, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    protected function isPexelsUrl(string $query): bool
    {
        return (bool)preg_match('#^https?://(?:www\.)?pexels\.com/(?:photo|video)/#i', $query);
    }

    protected function getPhotoById(int $id): ?array
    {
        $url = $this->apiUrl . 'photos/' . $id;
        return $this->makeApiRequest($url);
    }

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
            }

            $type = $options['type'] ?? 'image';
            $results = [];
            $totalHits = 0;

            // Erhöhe die Anzahl der Ergebnisse pro Seite
            $perPage = $this->itemsPerPage * 2;

            // Search for images
            if ($type === 'all' || $type === 'image') {
                // Versuche zuerst eine kuratierte Suche
                $imageResults = $this->makeApiRequest(
                    $this->apiUrl . 'curated',
                    [
                        'page' => $page,
                        'per_page' => $type === 'all' ? intval($perPage / 2) : $perPage
                    ]
                );

                // Wenn ein Suchbegriff vorhanden ist, führe auch eine normale Suche durch
                if (!empty($query)) {
                    $searchResults = $this->makeApiRequest(
                        $this->apiUrl . 'search',
                        [
                            'query' => $query,
                            'page' => $page,
                            'per_page' => $type === 'all' ? intval($perPage / 2) : $perPage,
                            'orientation' => 'landscape'
                        ]
                    );

                    if ($searchResults && isset($searchResults['photos'])) {
                        $results = array_map(
                            fn($item) => $this->formatItem($item, 'image'),
                            $searchResults['photos']
                        );
                        $totalHits = $searchResults['total_results'];
                    }
                } 
                // Wenn keine Suchergebnisse oder kein Suchbegriff, verwende kuratierte Ergebnisse
                elseif ($imageResults && isset($imageResults['photos'])) {
                    $results = array_map(
                        fn($item) => $this->formatItem($item, 'image'),
                        $imageResults['photos']
                    );
                    $totalHits = count($imageResults['photos']) * 10;
                }
            }

            // Search for videos
            if ($type === 'all' || $type === 'video') {
                $videoParams = [
                    'page' => $page,
                    'per_page' => $type === 'all' ? intval($perPage / 2) : $perPage
                ];

                if (!empty($query)) {
                    $videoParams['query'] = $query;
                    $endpoint = 'search';
                } else {
                    $endpoint = 'popular';
                }

                $videoResults = $this->makeApiRequest(
                    $this->apiUrlVideos . $endpoint,
                    $videoParams
                );

                if ($videoResults && isset($videoResults['videos'])) {
                    $videoItems = array_map(
                        fn($item) => $this->formatItem($item, 'video'),
                        $videoResults['videos']
                    );
                    
                    if ($type === 'all') {
                        $results = array_merge($results, $videoItems);
                        $totalHits = isset($videoResults['total_results']) 
                            ? intval(($totalHits + $videoResults['total_results']) / 2)
                            : $totalHits + count($videoResults['videos']) * 10;
                    } else {
                        $results = $videoItems;
                        $totalHits = $videoResults['total_results'] ?? count($videoResults['videos']) * 10;
                    }
                }
            }

            // Entferne Duplikate basierend auf der ID
            $results = array_values(array_reduce($results, function($carry, $item) {
                $carry[$item['id']] = $item;
                return $carry;
            }, []));

            // Stelle sicher, dass wir nicht mehr als die maximale Anzahl zurückgeben
            $results = array_slice($results, 0, $perPage);

            return [
                'items' => $results,
                'total' => $totalHits,
                'page' => $page,
                'total_pages' => max(1, ceil($totalHits / $perPage))
            ];
            
        } catch (\Exception $e) {
            \rex_logger::factory()->log(LogLevel::ERROR, 'Exception in searchApi: {message}', ['message' => $e->getMessage()], __FILE__, __LINE__);
            return [];
        }
    }

    protected function formatItem(array $item, string $type): array
    {
        if ($type === 'video') {
            // Sortiere Video-Files nach Qualität (HD zuerst)
            $videoFiles = $item['video_files'] ?? [];
            
            // Gruppiere nach Qualität
            $sizes = [];
            foreach ($videoFiles as $file) {
                $height = $file['height'] ?? 0;
                $link = $file['link'] ?? '';
                
                if (empty($link)) continue;

                if ($height >= 1080) {
                    $sizes['large'] = ['url' => $link];
                } elseif ($height >= 720) {
                    $sizes['medium'] = ['url' => $link];
                } elseif ($height >= 480) {
                    $sizes['small'] = ['url' => $link];
                } else {
                    $sizes['tiny'] = ['url' => $link];
                }
            }

            // Stelle sicher, dass alle Größen vorhanden sind
            if (!empty($videoFiles)) {
                $fallbackUrl = '';
                // Suche die beste verfügbare Qualität als Fallback
                foreach ($videoFiles as $file) {
                    if (!empty($file['link'])) {
                        $fallbackUrl = $file['link'];
                        break;
                    }
                }

                // Setze Fallback für fehlende Größen
                $requiredSizes = ['tiny', 'small', 'medium', 'large'];
                foreach ($requiredSizes as $size) {
                    if (!isset($sizes[$size])) {
                        $sizes[$size] = ['url' => $fallbackUrl];
                    }
                }
            }

            return [
                'id' => $item['id'],
                'preview_url' => $item['image'] ?? '',
                'title' => $item['duration'] ? sprintf('Video (%ds)', $item['duration']) : 'Video',
                'author' => $item['user']['name'] ?? 'Pexels',
                'type' => 'video',
                'size' => $sizes,
                'original_data' => $item
            ];
        }
        
        // Für Bilder
        $sizes = [
            'tiny' => ['url' => $item['src']['tiny'] ?? $item['src']['small'] ?? ''],
            'small' => ['url' => $item['src']['small'] ?? $item['src']['medium'] ?? ''],
            'medium' => ['url' => $item['src']['medium'] ?? $item['src']['large'] ?? ''],
            'large' => ['url' => $item['src']['original'] ?? $item['src']['large2x'] ?? $item['src']['large'] ?? '']
        ];

        return [
            'id' => $item['id'],
            'preview_url' => $item['src']['medium'] ?? $item['src']['small'] ?? '',
            'title' => $item['alt'] ?? $item['photographer'] ?? 'Image',
            'author' => $item['photographer'] ?? 'Pexels',
            'type' => 'image',
            'size' => $sizes,
            'original_data' => $item,
            'source_url' => $item['url'] ?? '',
            'photographer_url' => $item['photographer_url'] ?? '',
            'license' => 'Pexels License'
        ];
    }

    protected function makeApiRequest(string $url, array $params = []): ?array
    {
        if (!$this->isConfigured()) {
            throw new \rex_exception('Pexels API key not configured');
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        
        \rex_logger::factory()->log(LogLevel::INFO, 'Pexels API URL: {url}', ['url' => $url], __FILE__, __LINE__);

        // Debug log für API Key (nur die ersten 4 Zeichen)
        $apiKey = $this->config['apikey'];
        $maskedKey = substr($apiKey, 0, 4) . '...';
        \rex_logger::factory()->log(LogLevel::DEBUG, 'Using Pexels API key: {key}', ['key' => $maskedKey], __FILE__, __LINE__);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $apiKey
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

    public function import(string $url, string $filename): bool
    {
         dump('Starting import with:', [  // DUMP 7
        'url' => $url,
        'filename' => $filename
    ]);
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
