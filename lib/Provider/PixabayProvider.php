<?php
namespace FriendsOfRedaxo\AssetImport\Provider;

use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;

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
        if (!$this->isConfigured()) {
            throw new \rex_exception('Pixabay API key not configured');
        }

        $type = $options['type'] ?? 'image';
        
        $params = [
            'key' => $this->config['apikey'],
            'q' => $query,
            'page' => $page,
            'per_page' => $this->itemsPerPage,
            'safesearch' => 'true',
            'lang' => 'de'
        ];

        $baseUrl = ($type === 'video') ? $this->apiUrlVideos : $this->apiUrl;
        
        if ($type === 'image') {
            $params['image_type'] = 'all';
        }

        $url = $baseUrl . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        
        if ($response === false) {
            throw new \rex_exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['hits'])) {
            throw new \rex_exception('Invalid response from Pixabay API');
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
