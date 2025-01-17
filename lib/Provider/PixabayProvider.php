<?php
namespace FriendsOfRedaxo\AssetImport\Provider;

use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;
use rex_media;
use Psr\Log\LogLevel;

class PixabayProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://pixabay.com/api/';
    protected string $apiUrlVideos = 'https://pixabay.com/api/videos/';
    protected int $itemsPerPage = 20;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
         $this->config = $config; // Konfiguration laden
    }

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

    public function getConfigFields(): array
    {
        return [
            [
                'label' => 'asset_import_provider_pixabay_apikey',
                'name' => 'apikey',
                'type' => 'text',
                'notice' => 'asset_import_provider_pixabay_apikey_notice'
            ],
            [
                'label' => 'asset_import_provider_copyright_fields',
                'name' => 'copyright_fields',
                'type' => 'select',
                'options' => [
                    ['label' => 'Username + Pixabay', 'value' => 'user_pixabay'],
                    ['label' => 'Only Pixabay', 'value' => 'pixabay']
                ],
                'notice' => 'asset_import_provider_copyright_notice'
            ]
        ];
    }

    public function isConfigured(): bool
    {
        $isConfigured = isset($this->config['apikey']) && !empty($this->config['apikey']);
        
        if (!$isConfigured) {
            \rex_logger::factory()->log(LogLevel::WARNING, 'Pixabay provider not configured correctly.', [], __FILE__, __LINE__);
        }
        
        return $isConfigured;
    }

    protected function searchApi(string $query, int $page = 1, array $options = []): array
    {
        try {
            if (!$this->isConfigured()) {
                throw new \rex_exception('Pixabay API key not configured');
            }

            if ($this->isPixabayUrl($query)) {
                return $this->handlePixabayUrl($query);
            }

            $type = $options['type'] ?? 'image';
            $results = [];
            $totalHits = 0;

            if ($type === 'all' || $type === 'image') {
                $imageResults = $this->searchImages($query, $page, $type);
                $results = $imageResults['items'] ?? [];
                $totalHits = $imageResults['total'] ?? 0;
            }

            if ($type === 'all' || $type === 'video') {
                $videoResults = $this->searchVideos($query, $page, $type);
                
                if ($type === 'all' && !empty($videoResults['items'])) {
                    $results = array_merge($results, $videoResults['items']);
                    $totalHits = intval(($totalHits + ($videoResults['total'] ?? 0)) / 2);
                } elseif ($type === 'video') {
                    $results = $videoResults['items'] ?? [];
                    $totalHits = $videoResults['total'] ?? 0;
                }
            }

            // Entferne Duplikate und beschränke Ergebnisse
            $results = array_values(array_unique($results, SORT_REGULAR));
            $results = array_slice($results, 0, $this->itemsPerPage);

            return [
                'items' => $results,
                'total' => $totalHits,
                'page' => $page,
                'total_pages' => ceil($totalHits / $this->itemsPerPage)
            ];

        } catch (\Exception $e) {
            \rex_logger::factory()->log(LogLevel::ERROR, $e->getMessage(), [], __FILE__, __LINE__);
            return ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
        }
    }

    protected function searchImages(string $query, int $page, string $type): array
    {
        $params = [
            'key' => $this->config['apikey'],
            'q' => $query,
            'page' => $page,
            'per_page' => $type === 'all' ? intval($this->itemsPerPage / 2) : $this->itemsPerPage,
            'safesearch' => 'true',
            'lang' => \rex::getUser()->getLanguage(),
            'image_type' => 'all',
            'orientation' => 'horizontal'
        ];

        $response = $this->makeApiRequest($this->apiUrl, $params);
        
        if (!$response || !isset($response['hits'])) {
            return ['items' => [], 'total' => 0];
        }

        return [
            'items' => array_map(
                fn($item) => $this->formatItem($item, 'image'),
                $response['hits']
            ),
            'total' => $response['totalHits']
        ];
    }

    protected function searchVideos(string $query, int $page, string $type): array
    {
        $params = [
            'key' => $this->config['apikey'],
            'q' => $query,
            'page' => $page,
            'per_page' => $type === 'all' ? intval($this->itemsPerPage / 2) : $this->itemsPerPage,
            'safesearch' => 'true',
            'lang' => \rex::getUser()->getLanguage()
        ];

        $response = $this->makeApiRequest($this->apiUrlVideos, $params);
        
        if (!$response || !isset($response['hits'])) {
            return ['items' => [], 'total' => 0];
        }

        return [
            'items' => array_map(
                fn($item) => $this->formatItem($item, 'video'),
                $response['hits']
            ),
            'total' => $response['totalHits']
        ];
    }

    protected function formatItem(array $item, string $type): array
    {
        $copyright = $this->formatCopyright($item);

        if ($type === 'video') {
            return [
                'id' => $item['id'],
                'preview_url' => !empty($item['picture_id']) ? "https://i.vimeocdn.com/video/{$item['picture_id']}_640x360.jpg" : ($item['userImageURL'] ?? $item['previewURL'] ?? ''),
                'title' => $this->formatTitle($item),
                'author' => $item['user'] ?? '',
                'copyright' => $copyright,
                'type' => 'video',
                'size' => $this->formatVideoSizes($item)
            ];
        }

        return [
            'id' => $item['id'],
            'preview_url' => $item['previewURL'],
            'title' => $this->formatTitle($item),
            'author' => $item['user'] ?? '',
            'copyright' => $copyright,
            'type' => 'image',
            'size' => [
                'medium' => ['url' => $item['largeImageURL']],
                'tiny' => ['url' => $item['previewURL']],
                'small' => ['url' => $item['webformatURL']],
                'large' => ['url' => $item['imageURL'] ?? $item['largeImageURL']]
            ]
        ];
    }

    protected function formatTitle(array $item): string
    {
        $title = '';

        // Versuche zuerst, einen sprechenden Titel aus den Tags zu generieren
        if (!empty($item['tags'])) {
            $tags = explode(',', $item['tags']);
            $title = trim($tags[0]);
        }

        // Fallback auf ID und Typ
        if (empty($title)) {
            $type = isset($item['videos']) ? 'Video' : 'Image';
            $title = $type . ' ' . $item['id'];
        }

        return $title;
    }

    protected function formatCopyright(array $item): string
    {
        $copyrightFields = $this->config['copyright_fields'] ?? 'user_pixabay';
        $parts = [];

        switch ($copyrightFields) {
             case 'user_pixabay':
                if (!empty($item['user'])) {
                    $parts[] = $item['user'];
                }
                $parts[] = 'Pixabay.com';
                break;
            case 'pixabay':
            default:
                $parts[] = 'Pixabay.com';
                break;
        }

        return implode(' / ', array_filter($parts));
    }

    protected function formatVideoSizes(array $item): array
    {
        $sizes = [
            'medium' => ['url' => ''],
            'tiny' => ['url' => ''],
            'small' => ['url' => ''],
            'large' => ['url' => '']
        ];
        
        if (!empty($item['videos'])) {
            // Finde die beste verfügbare Qualität als Fallback
            $fallbackUrl = '';
            
            // Definiere Qualitätsstufen und ihre Zuordnung
            $qualityMap = [
                'large' => ['large', 'medium'],
                'medium' => ['medium', 'large', 'small'],
                'small' => ['small', 'medium', 'tiny'],
                'tiny' => ['tiny', 'small']
            ];
            
            // Finde zuerst einen Fallback
            foreach (['large', 'medium', 'small', 'tiny'] as $quality) {
                if (!empty($item['videos'][$quality]['url'])) {
                    $fallbackUrl = $item['videos'][$quality]['url'];
                    break;
                }
            }
            
            // Setze die URLs für jede Größe
            foreach ($qualityMap as $targetSize => $possibleSources) {
                foreach ($possibleSources as $source) {
                    if (!empty($item['videos'][$source]['url'])) {
                        $sizes[$targetSize]['url'] = $item['videos'][$source]['url'];
                        break;
                    }
                }
                // Wenn keine passende Größe gefunden wurde, nutze den Fallback
                if (empty($sizes[$targetSize]['url']) && $fallbackUrl) {
                    $sizes[$targetSize]['url'] = $fallbackUrl;
                }
            }
        }

        return $sizes;
    }

    public function import(string $url, string $filename, ?string $copyright = null): bool
    {
        if (!$this->isConfigured()) {
            throw new \rex_exception('Pixabay API key not configured');
        }

        try {
            $filename = $this->sanitizeFilename($filename);
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

            if (!$extension) {
                $extension = strpos($url, 'vimeocdn.com') !== false ? 'mp4' : 'jpg';
            }
            
            $filename = $filename . '.' . $extension;

            if ($this->downloadFile($url, $filename)) {
                if ($copyright) {
                    $media = rex_media::get($filename);
                    if ($media) {
                        $sql = \rex_sql::factory();
                        $sql->setTable(\rex::getTable('media'));
                        $sql->setWhere(['filename' => $filename]);
                        $sql->setValue('med_copyright', $copyright);
                        $sql->update();
                    }
                }
                return true;
            }

            return false;

        } catch (\Exception $e) {
            \rex_logger::factory()->log(LogLevel::ERROR, 'Import error: ' . $e->getMessage(), [], __FILE__, __LINE__);
            return false;
        }
    }

    protected function isPixabayUrl(string $query): bool
    {
        return (bool)preg_match('#^https?://(?:www\.)?pixabay\.com/(?:photos|videos)/#i', $query);
    }

    protected function handlePixabayUrl(string $query): array
    {
        $id = $this->extractImageIdFromUrl($query);
        if (!$id) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
        }

        // Versuche zuerst Bild
        $item = $this->getById($id, 'image');
        $type = 'image';
        
        // Wenn nicht gefunden, versuche Video
        if (!$item) {
            $item = $this->getById($id, 'video');
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

        return ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
    }

    protected function extractImageIdFromUrl(string $url): ?int
    {
        if (preg_match('#pixabay\.com/(?:photos|videos)/[^/]+-(\d+)/?$#i', $url, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    protected function getById(int $id, string $type = 'image'): ?array
    {
        $params = [
            'key' => $this->config['apikey'],
            'id' => $id,
            'lang' => \rex::getUser()->getLanguage()
        ];

        $url = $type === 'video' ? $this->apiUrlVideos : $this->apiUrl;
        $response = $this->makeApiRequest($url, $params);

        return $response['hits'][0] ?? null;
    }

    protected function makeApiRequest(string $url, array $params): ?array
    {
        $url = $url . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 20
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            \rex_logger::factory()->log(LogLevel::ERROR, 'API request failed: {url}', ['url' => $url, 'http_code' => $httpCode], __FILE__, __LINE__);
            return null;
        }

        $data = json_decode($response, true);
        if (!isset($data['hits'])) {
            \rex_logger::factory()->log(LogLevel::ERROR, 'Invalid API response: {url}', ['url' => $url], __FILE__, __LINE__);
            return null;
        }

        return $data;
    }

    public function getDefaultOptions(): array
    {
        return [
            'type' => 'image',
            'orientation' => 'horizontal',
            'safesearch' => true,
            'lang' => \rex::getUser()->getLanguage()
        ];
    }

    protected function getCacheLifetime(): int
    {
        // 24 Stunden Cache
        return 86400;
    }
}
