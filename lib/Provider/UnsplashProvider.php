<?php

namespace FriendsOfRedaxo\AssetImport\Provider;

use Exception;
use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;
use Psr\Log\LogLevel;
use rex_media;
use rex;
use rex_sql;
use rex_exception;
use rex_logger;
use rex_path;
use rex_i18n;
use rex_media_service;

class UnsplashProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://api.unsplash.com/search/photos';
    protected int $itemsPerPage = 20;

    public function getName(): string
    {
        return 'unsplash';
    }

    public function getTitle(): string
    {
        return 'Unsplash';
    }

    public function getIcon(): string
    {
        return 'fa-camera';
    }

    public function getConfigFields(): array
    {
        return [
            [
                'label' => 'asset_import_provider_unsplash_apikey',
                'name' => 'apikey',
                'type' => 'text',
                'notice' => 'asset_import_provider_unsplash_apikey_notice',
            ],
            [
                'label' => 'asset_import_provider_copyright_fields',
                'name' => 'copyright_fields',
                'type' => 'select',
                'options' => [
                    ['label' => 'Photographer + Unsplash', 'value' => 'photographer_unsplash'],
                    ['label' => 'Only Photographer', 'value' => 'photographer'],
                    ['label' => 'Only Unsplash', 'value' => 'unsplash'],
                ],
                'notice' => 'asset_import_provider_copyright_notice',
            ],
        ];
    }

    public function isConfigured(): bool
    {
        $isConfigured = isset($this->config['apikey']) && !empty($this->config['apikey']);

        if (!$isConfigured) {
            rex_logger::factory()->log(LogLevel::WARNING, 'Unsplash provider not configured correctly.', [], __FILE__, __LINE__);
        }

        return $isConfigured;
    }

    protected function searchApi(string $query, int $page = 1, array $options = []): array
    {
        try {
            if (!$this->isConfigured()) {
                throw new rex_exception('Unsplash API key not configured');
            }

            $results = $this->searchImages($query, $page, $this->itemsPerPage);

            return [
                'items' => $results['items'],
                'total' => $results['total'],
                'page' => $page,
                'total_pages' => ceil($results['total'] / $this->itemsPerPage),
            ];
        } catch (Exception $e) {
            rex_logger::factory()->log(LogLevel::ERROR, $e->getMessage(), [], __FILE__, __LINE__);
            return ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
        }
    }

    protected function searchImages(string $query, int $page, int $perPage): array
    {
        $params = [
            'query' => $query,
            'page' => $page,
            'per_page' => $perPage,
        ];

        $response = $this->makeApiRequest($this->apiUrl, $params);

        $results = ['items' => [], 'total' => 0];
        if ($response && isset($response['results'])) {
            $results['items'] = array_map(fn($item) => $this->formatItem($item), $response['results']);
            $results['total'] = $response['total'] ?? count($response['results']);
        }

        return $results;
    }

    protected function formatItem(array $item): array
    {
        return [
            'id' => $item['id'],
            'preview_url' => $item['urls']['small'],
            'title' => $item['alt_description'] ?? 'Image',
            'author' => $item['user']['name'] ?? '',
            'copyright' => $this->formatCopyright($item),
            'type' => 'image',
            'size' => [
                'small' => ['url' => $item['urls']['small']],
                'medium' => ['url' => $item['urls']['regular']],
                'large' => ['url' => $item['urls']['full']],
            ],
        ];
    }

    protected function formatCopyright(array $item): string
    {
        rex_logger::factory()->log(LogLevel::DEBUG, 'Formatting copyright', [
            'item_id' => $item['id'] ?? 'unknown',
            'copyright_fields' => $this->config['copyright_fields'] ?? 'default',
            'photographer' => $item['user']['name'] ?? 'unknown',
        ]);

        $field = $this->config['copyright_fields'] ?? 'photographer_unsplash';
        $parts = [];

        switch ($field) {
            case 'photographer':
                $parts[] = $item['user']['name'] ?? '';
                break;
            case 'unsplash':
                $parts[] = 'Unsplash.com';
                break;
            case 'photographer_unsplash':
            default:
                if (!empty($item['user']['name'])) {
                    $parts[] = $item['user']['name'];
                }
                $parts[] = 'Unsplash.com';
                break;
        }

        $copyright = implode(' / ', array_filter($parts));

        rex_logger::factory()->log(LogLevel::DEBUG, 'Generated copyright string', [
            'copyright' => $copyright,
        ]);

        return $copyright;
    }

    protected function makeApiRequest(string $url, array $params = []): ?array
    {
        $url .= '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: Client-ID ' . $this->config['apikey'],
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (false === $response || 200 !== $httpCode) {
            rex_logger::factory()->log(LogLevel::ERROR, 'API request failed: ' . $url, ['http_code' => $httpCode], __FILE__, __LINE__);
            return null;
        }

        $data = json_decode($response, true);
        if (null === $data) {
            rex_logger::factory()->log(LogLevel::ERROR, 'Invalid JSON response from API: ' . $url, [], __FILE__, __LINE__);
            return null;
        }

        return $data;
    }

    public function import(string $url, string $filename, ?string $copyright = null, int $categoryId = 0): bool
    {
        if (!$this->isConfigured()) {
            throw new rex_exception('Unsplash API key not configured');
        }

        try {
            $filename = $this->sanitizeFilename($filename);
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

            if (!$extension) {
                $header = get_headers($url, 1);
                if (isset($header['Content-Type']) && preg_match('#image/(\w+)#', $header['Content-Type'], $matches)) {
                    $extension = $matches[1];
                } else {
                    $extension = 'jpg';
                }
            }

            $filename .= '.' . $extension;

            if (rex_media::get($filename)) {
                throw new rex_exception(rex_i18n::msg('asset_import_file_exists', $filename));
            }

            $tempPath = rex_path::coreData('unsplash_tmp_' . $filename);
            if (!file_put_contents($tempPath, file_get_contents($url))) {
                throw new rex_exception('Failed to download image from Unsplash');
            }

            $fileInfo = [
                'name' => $filename,
                'path' => $tempPath,
                'type' => mime_content_type($tempPath),
                'tmp_name' => $tempPath,
                'error' => 0,
                'size' => filesize($tempPath),
            ];

            rex_media_service::addMedia([
                'category_id' => $categoryId,
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'file' => $fileInfo,
            ]);

            if ($copyright) {
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('media'));
                $sql->setWhere(['filename' => $filename]);
                $sql->setValue('med_copyright', $copyright);
                $sql->update();
            }

            return true;
        } catch (Exception $e) {
            rex_logger::factory()->log(LogLevel::ERROR, 'Import error: ' . $e->getMessage());
            throw $e;
        }
    }
}
