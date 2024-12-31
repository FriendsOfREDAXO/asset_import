<?php
namespace FriendsOfRedaxo\AssetImport\Provider;

use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;
use Psr\Log\LogLevel;

class PixabayProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://pixabay.com/api/';
    protected string $apiUrlVideos = 'https://pixabay.com/api/videos/';
    protected int $itemsPerPage = 20;
    protected ?string $currentAuthor = null;

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

    protected function getAuthor(): string 
    {
        return $this->currentAuthor ?? '';
    }

    protected function extractImageIdFromUrl(string $url): ?int
    {
        if (preg_match('#pixabay\.com/(?:photos|videos|illustrations|vectors)/[^/]+-(\d+)/?$#i', $url, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    protected function isPixabayUrl(string $query): bool
    {
        return (bool)preg_match('#^https?://(?:www\.)?pixabay\.com/(?:photos|videos|illustrations|vectors)/#i', $query);
    }

    protected function getById(int $id, string $type = 'image'): ?array
    {
        $params = [
            'key' => $this->config['apikey'],
            'id' => $id,
            'safesearch' => 'true',
            'lang' => \rex::getUser()->getLanguage()
        ];

        $baseUrl = ($type === 'video') ? $this->apiUrlVideos : $this->apiUrl;
        
        if ($type === 'image') {
            $params['image_type'] = 'all';
        }

        $result = $this->makeApiRequest($baseUrl, $params);
        
        if ($result && !empty($result['hits'])) {
            return $result['hits'][0];
        }

        return null;
    }

    protected function searchApi(string $query, int $page = 1, array $options = []): array
    {
        try {
            if (!$this->isConfigured()) {
                throw new \rex_exception('Pixabay API key not configured');
            }

            if ($this->isPixabayUrl($query)) {
                $imageId = $this->extractImageIdFromUrl($query);
                if ($imageId) {
                    $item = $this->getById($imageId, 'image');
                    $type = 'image';
                    
                    if (!$item) {
                        $item = $this->getById($imageId, 'video');
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

            if ($type === 'all' || $type === 'image') {
                $imageParams = [
                    'key' => $this->config['apikey'],
                    'q' => $query,
                    'page' => $page,
                    'per_page' => $type === 'all' ? intval($this->itemsPerPage / 2) : $this->itemsPerPage,
                    'safesearch' => 'true',
                    'lang' => \rex::getUser()->getLanguage(),
                    'image_type' => 'all'
                ];
                
                $imageResults = $this->makeApiRequest($this->apiUrl, $imageParams);
                if ($imageResults) {
                    $results = array_map(
                        fn($item) => $this->formatItem($item, 'image'),
                        $imageResults['hits']
                    );
                    $totalHits = $imageResults['totalHits'];
                }
            }

            if ($type === 'all' || $type === 'video') {
                $videoParams = [
                    'key' => $this->config['apikey'],
                    'q' => $query,
                    'page' => $page,
                    'per_page' => $type === 'all' ? intval($this->itemsPerPage / 2) : $this->itemsPerPage,
                    'safesearch' => 'true',
                    'lang' => \rex::getUser()->getLanguage()
                ];
                
                $videoResults = $this->makeApiRequest($this->apiUrlVideos, $videoParams);
                if ($videoResults) {
                    $videoItems = array_map(
                        fn($item) => $this->formatItem($item, 'video'),
                        $videoResults['hits']
                    );
                    
                    if ($type === 'all') {
                        $results = array_merge($results, $videoItems);
                        $totalHits = intval(($totalHits + $videoResults['totalHits']) / 2);
                    } else {
                        $results = $videoItems;
                        $totalHits = $videoResults['totalHits'];
                    }
                }
            }

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

    protected function formatItem(array $item, string $type): array
    {
        $this->currentAuthor = $item['user'] ?? '';

        if ($type === 'video') {
            return [
                'id' => $item['id'],
                'preview_url' => $item['picture_id'] ? "https://i.vimeocdn.com/video/{$item['picture_id']}_640x360.jpg" : '',
                'title' => $item['tags'],
                'author' => $this->getAuthor(),
                'type' => 'video',
                'size' => [
                    'small' => ['url' => $item['videos']['small']['url'] ?? ''],
                    'medium' => ['url' => $item['videos']['medium']['url'] ?? ''],
                    'large' => ['url' => $item['videos']['large']['url'] ?? $item['videos']['medium']['url'] ?? '']
                ]
            ];
        }

        $sizes = [
            'small' => ['url' => $item['webformatURL']],
            'medium' => ['url' => $item['largeImageURL']],
            'large' => ['url' => $item['imageURL'] ?? $item['largeImageURL']]
        ];

        // Add vector if available
        if (isset($item['vectorURL'])) {
            $sizes['vector'] = ['url' => $item['vectorURL']];
        }
        
        return [
            'id' => $item['id'],
            'preview_url' => $item['webformatURL'],
            'title' => $item['tags'],
            'author' => $this->getAuthor(),
            'type' => 'image',
            'size' => $sizes
        ];
    }

    protected function makeApiRequest(string $url, array $params): ?array
    {
        $url = $url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        
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
            $errorMessage = 'Invalid response from Pixabay API - URL: ' . $url . ' - Response: ' . $response;
            \rex_logger::factory()->log(LogLevel::ERROR, 'Invalid API Response: {message}', ['message' => $errorMessage], __FILE__, __LINE__);
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
