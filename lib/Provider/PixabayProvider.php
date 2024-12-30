<?php
namespace FriendsOfRedaxo\AssetImport\Provider;

use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;
use Psr\Log\LogLevel;

class PixabayProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://pixabay.com/api/';
    protected string $apiUrlVideos = 'https://pixabay.com/api/videos/';
    protected int $itemsPerPage = 20;

    public function getName(): string
    {
        return 'pixabay';
    }

    public function getTitle(): string
    {
        return 'Pixabay';
    }

    public function getIcon(): string
    {
        return 'fa-images';
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
                'label' => 'asset_import_provider_pixabay_apikey',
                'name' => 'apikey',
                'type' => 'text',
                'notice' => 'asset_import_provider_pixabay_apikey_notice'
            ]
        ];
    }

    protected function searchApi(string $query, int $page = 1, array $options = []): array
    {
        try {
            if (!$this->isConfigured()) {
                throw new \rex_exception('Pixabay API key not configured');
            }

            $type = $options['type'] ?? 'image';
            $results = [];
            $totalHits = 0;

            // Bei 'all' oder 'image' nach Bildern suchen
            if ($type === 'all' || $type === 'image') {
                $imageResults = $this->fetchFromApi($this->apiUrl, $query, $page, ['image_type' => 'all']);
                if ($imageResults) {
                    $results = array_map(function($item) {
                        return [
                            'id' => $item['id'],
                            'preview_url' => $item['webformatURL'],
                            'title' => $item['tags'],
                            'author' => $item['user'],
                            'type' => 'image',
                            'size' => [
                                'preview' => ['url' => $item['previewURL']],
                                'web' => ['url' => $item['webformatURL']],
                                'large' => ['url' => $item['largeImageURL']],
                                'original' => ['url' => $item['imageURL'] ?? $item['largeImageURL']]
                            ]
                        ];
                    }, $imageResults['hits']);
                    $totalHits += $imageResults['totalHits'];
                }
            }

            // Bei 'all' oder 'video' nach Videos suchen
            if ($type === 'all' || $type === 'video') {
                $videoResults = $this->fetchFromApi($this->apiUrlVideos, $query, $page);
                if ($videoResults) {
                    $videoItems = array_map(function($item) {
                        return [
                            'id' => $item['id'],
                            'preview_url' => $item['picture_id'] ? "https://i.vimeocdn.com/video/{$item['picture_id']}_640x360.jpg" : '',
                            'title' => $item['tags'],
                            'author' => $item['user'],
                            'type' => 'video',
                            'size' => [
                                'tiny' => ['url' => $item['videos']['tiny']['url']],
                                'small' => ['url' => $item['videos']['small']['url']],
                                'medium' => ['url' => $item['videos']['medium']['url']],
                                'large' => ['url' => $item['videos']['large']['url'] ?? $item['videos']['medium']['url']]
                            ]
                        ];
                    }, $videoResults['hits']);
                    $results = array_merge($results, $videoItems);
                    $totalHits += $videoResults['totalHits'];
                }
            }

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

    protected function fetchFromApi(string $apiUrl, string $query, int $page, array $additionalParams = []): ?array
    {
        $params = array_merge([
            'key' => $this->config['apikey'],
            'q' => $query,
            'page' => $page,
            'per_page' => $this->itemsPerPage,
            'safesearch' => 'true',
            'lang' => 'de'
        ], $additionalParams);

        $url = $apiUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        \rex_logger::factory()->log(LogLevel::INFO, 'Pixabay API URL: {url}', ['url' => $url], __FILE__, __LINE__);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
            
        if ($response === false) {
            $errorMessage = 'Curl error: ' . curl_error($ch) . ' - URL: ' . $url;
            \rex_logger::factory()->log(LogLevel::ERROR, 'Curl Error: {message}', ['message' => $errorMessage], __FILE__, __LINE__);
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['hits'])) {
            $errorMessage = 'Invalid response from Pixabay API - URL: {url} - Response: {response}';
            \rex_logger::factory()->log(LogLevel::ERROR, 'Invalid API Response: {message}', ['message' => $errorMessage, 'url' => $url, 'response' => $response], __FILE__, __LINE__);
            return null;
        }

        return $data;
    }

    public function import(string $url, string $filename): bool
    {
        if (!$this->isConfigured()) {
            throw new \rex_exception('Pixabay API key not configured');
        }

        $filename = $this->sanitizeFilename($filename);
        
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$extension) {
            $extension = strpos($url, 'vimeocdn.com') !== false ? 'mp4' : 'jpg';
        }
        
        $filename = $filename . '.' . $extension;

        return $this->downloadFile($url, $filename);
    }
}
