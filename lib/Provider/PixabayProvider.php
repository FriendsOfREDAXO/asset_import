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
            
            $params = [
                'key' => $this->config['apikey'],
                'q' => $query, // Query Parameter bleibt unverändert
                'page' => $page,
                'per_page' => $this->itemsPerPage,
                'safesearch' => 'true',
                'lang' => 'de'
            ];

            $baseUrl = ($type === 'video') ? $this->apiUrlVideos : $this->apiUrl;
            
            if ($type === 'image') {
                $params['image_type'] = 'all';
            }

            // URL zusammenbauen mit PHP_QUERY_RFC3986
            $url = $baseUrl . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

            // Logging der generierten URL
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
                throw new \rex_exception($errorMessage);
            }

            curl_close($ch);

            $data = json_decode($response, true);
            if (!isset($data['hits'])) {
                $errorMessage = 'Invalid response from Pixabay API - URL: {url} - Response: {response}';
                \rex_logger::factory()->log(LogLevel::ERROR, 'Invalid API Response: {message}', ['message' => $errorMessage, 'url' => $url, 'response' => $response], __FILE__, __LINE__);
                throw new \rex_exception($errorMessage);
            }

            return [
                'items' => array_map(function($item) use ($type) {
                    if ($type === 'video') {
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
                    } else {
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
                    }
                }, $data['hits']),
                'total' => $data['totalHits'],
                'page' => $page,
                'total_pages' => ceil($data['totalHits'] / $this->itemsPerPage)
            ];
           } catch (\Exception $e) {
               \rex_logger::factory()->log(LogLevel::ERROR, 'Exception in searchApi: {message}', ['message' => $e->getMessage()], __FILE__, __LINE__);
           }
        return [];
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