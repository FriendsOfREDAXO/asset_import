<?php
namespace FriendsOfRedaxo\AssetImport\Provider;

use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;
use Psr\Log\LogLevel;
use rex;
use rex_media;
use rex_media_manager;
use rex_sql;

class PixabayProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://pixabay.com/api/';
    protected string $apiUrlVideos = 'https://pixabay.com/api/videos/';
    protected int $itemsPerPage = 20;
    protected array $currentAssetInfo = [];

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

            // Search for images if type is 'all' or 'image'
            if ($type === 'all' || $type === 'image') {
                $imageParams = [
                    'key' => $this->config['apikey'],
                    'q' => $query,
                    'page' => $page,
                    'per_page' => $type === 'all' ? intval($this->itemsPerPage / 2) : $this->itemsPerPage,
                    'safesearch' => 'true',
                    'lang' => 'de',
                    'image_type' => 'all'
                ];
                
                $imageResults = $this->makeApiRequest($this->apiUrl, $imageParams);
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
                    $totalHits = $imageResults['totalHits'];
                }
            }

            // Search for videos if type is 'all' or 'video'
            if ($type === 'all' || $type === 'video') {
                $videoParams = [
                    'key' => $this->config['apikey'],
                    'q' => $query,
                    'page' => $page,
                    'per_page' => $type === 'all' ? intval($this->itemsPerPage / 2) : $this->itemsPerPage,
                    'safesearch' => 'true',
                    'lang' => 'de'
                ];
                
                $videoResults = $this->makeApiRequest($this->apiUrlVideos, $videoParams);
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
                    
                    if ($type === 'all') {
                        $results = array_merge($results, $videoItems);
                        $totalHits = intval(($totalHits + $videoResults['totalHits']) / 2);
                    } else {
                        $results = $videoItems;
                        $totalHits = $videoResults['totalHits'];
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
        
        // Extract author from the title/filename
        $parts = explode(',', $filename);
        $author = trim(end($parts));
        
        // Store asset info for use in setMediaMetadata
        $this->currentAssetInfo = [
            'copyright' => sprintf('Pixabay, %s', $author)
        ];
        
        $filename = $filename . '.' . $extension;
        return $this->downloadFile($url, $filename);
    }

    protected function ensureCopyrightField(): void
    {
        $sql = rex_sql::factory();
        $sql->setQuery('SHOW COLUMNS FROM ' . rex::getTable('media') . ' LIKE "med_copyright"');
        
        if ($sql->getRows() === 0) {
            // Feld existiert noch nicht, also anlegen
            $sql->setQuery('ALTER TABLE ' . rex::getTable('media') . ' ADD med_copyright VARCHAR(255) DEFAULT NULL');
        }
    }

    protected function setMediaMetadata(string $filename): void
    {
        // Prüfen ob das Copyright-Feld existiert
        $sql = rex_sql::factory();
        $sql->setQuery('SHOW COLUMNS FROM ' . rex::getTable('media') . ' LIKE "med_copyright"');
        
        // Nur speichern wenn das Feld existiert
        if ($sql->getRows() > 0 && !empty($this->currentAssetInfo)) {
            $media = rex_media::get($filename);
            if ($media) {
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('media'));
                $sql->setWhere(['filename' => $filename]);
                $sql->setValue('med_copyright', $this->currentAssetInfo['copyright']);
                $sql->update();
            }
        }
        
        // Reset current asset info
        $this->currentAssetInfo = [];
    }

    protected function downloadFile(string $url, string $filename): bool
    {
        $success = parent::downloadFile($url, $filename);
        
        if ($success) {
            // Set metadata after successful download
            $this->setMediaMetadata($filename);
        }
        
        return $success;
    }
}
