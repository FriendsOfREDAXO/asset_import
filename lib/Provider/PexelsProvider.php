<?php

namespace FriendsOfRedaxo\AssetImport\Provider;

use Exception;
use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;
use Psr\Log\LogLevel;
use rex;
use rex_exception;
use rex_i18n;
use rex_logger;
use rex_media;
use rex_sql;

use function array_slice;
use function count;
use function is_array;
use function sprintf;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_NOBODY;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const PATHINFO_EXTENSION;
use const PHP_URL_PATH;

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

    public function getConfigFields(): array
    {
        return [
            [
                'label' => 'asset_import_provider_pexels_apikey',
                'name' => 'apikey',
                'type' => 'text',
                'notice' => 'asset_import_provider_pexels_apikey_notice',
            ],
            [
                'label' => 'asset_import_provider_copyright_fields',
                'name' => 'copyright_fields',
                'type' => 'select',
                'options' => [
                    ['label' => 'Photographer + Pexels', 'value' => 'photographer_pexels'],
                    ['label' => 'Only Photographer', 'value' => 'photographer'],
                    ['label' => 'Only Pexels', 'value' => 'pexels'],
                ],
                'notice' => 'asset_import_provider_copyright_notice',
            ],
        ];
    }

    public function isConfigured(): bool
    {
        $isConfigured = isset($this->config)
                       && is_array($this->config)
                       && isset($this->config['apikey'])
                       && !empty($this->config['apikey']);

        if (!$isConfigured) {
            rex_logger::factory()->log(LogLevel::WARNING, 'Pexels provider not configured correctly.', [], __FILE__, __LINE__);
        }

        return $isConfigured;
    }

    protected function searchApi(string $query, int $page = 1, array $options = []): array
    {
        try {
            if (!$this->isConfigured()) {
                throw new rex_exception('Pexels API key not configured');
            }

            if ($this->isPexelsUrl($query)) {
                return $this->handlePexelsUrl($query);
            }

            $type = $options['type'] ?? 'all';
            $results = ['items' => [], 'total' => 0];

            // Setze itemsPerPage basierend auf dem Typ
            $currentItemsPerPage = ('all' === $type) ?
                (int) ($this->itemsPerPage / 2) :
                $this->itemsPerPage;

            if ('all' === $type || 'image' === $type) {
                $imageResults = $this->searchImages($query, $page, $currentItemsPerPage);
                $results['items'] = array_merge($results['items'], $imageResults['items'] ?? []);
                $results['total'] += $imageResults['total'] ?? 0;
            }

            if ('all' === $type || 'video' === $type) {
                $videoResults = $this->searchVideos($query, $page, $currentItemsPerPage);
                $results['items'] = array_merge($results['items'], $videoResults['items'] ?? []);
                if ('video' === $type) {
                    $results['total'] = $videoResults['total'] ?? 0;
                } else {
                    $results['total'] = (int) (($results['total'] + ($videoResults['total'] ?? 0)) / 2);
                }
            }

            // Sortiere und begrenze Ergebnisse
            if ('all' === $type) {
                usort($results['items'], static function ($a, $b) {
                    return $b['id'] - $a['id'];
                });
                $results['items'] = array_slice($results['items'], 0, $this->itemsPerPage);
            }

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
        $results = [];

        if (!empty($query)) {
            $searchResults = $this->makeApiRequest(
                $this->apiUrl . 'search',
                [
                    'query' => $query,
                    'page' => $page,
                    'per_page' => $perPage,
                    'orientation' => 'landscape',
                ],
            );

            if ($searchResults && isset($searchResults['photos'])) {
                $results = [
                    'items' => array_map(
                        fn ($item) => $this->formatItem($item, 'image'),
                        $searchResults['photos'],
                    ),
                    'total' => $searchResults['total_results'],
                ];
            }
        } else {
            $curatedResults = $this->makeApiRequest(
                $this->apiUrl . 'curated',
                [
                    'page' => $page,
                    'per_page' => $perPage,
                ],
            );

            if ($curatedResults && isset($curatedResults['photos'])) {
                $results = [
                    'items' => array_map(
                        fn ($item) => $this->formatItem($item, 'image'),
                        $curatedResults['photos'],
                    ),
                    'total' => count($curatedResults['photos']) * 10,
                ];
            }
        }

        return $results;
    }

    protected function searchVideos(string $query, int $page, int $perPage): array
    {
        $videoParams = [
            'page' => $page,
            'per_page' => $perPage,
        ];

        if (!empty($query)) {
            $videoParams['query'] = $query;
            $endpoint = 'search';
        } else {
            $endpoint = 'popular';
        }

        $videoResults = $this->makeApiRequest(
            $this->apiUrlVideos . $endpoint,
            $videoParams,
        );

        if ($videoResults && isset($videoResults['videos'])) {
            return [
                'items' => array_map(
                    fn ($item) => $this->formatItem($item, 'video'),
                    $videoResults['videos'],
                ),
                'total' => $videoResults['total_results'] ?? count($videoResults['videos']) * 10,
            ];
        }

        return ['items' => [], 'total' => 0];
    }

    protected function formatItem(array $item, string $type): array
    {
        $copyright = $this->formatCopyright($item);

        if ('video' === $type) {
            $sizes = $this->formatVideoSizes($item);

            return [
                'id' => $item['id'],
                'preview_url' => $item['image'] ?? '',
                'title' => $item['duration'] ? sprintf('Video (%ds)', $item['duration']) : 'Video',
                'author' => $item['user']['name'] ?? '',
                'copyright' => $copyright,
                'type' => 'video',
                'size' => $sizes,
            ];
        }

        return [
            'id' => $item['id'],
            'preview_url' => $item['src']['medium'] ?? $item['src']['small'] ?? '',
            'title' => $item['alt'] ?? $item['photographer'] ?? 'Image',
            'author' => $item['photographer'] ?? '',
            'copyright' => $copyright,
            'type' => 'image',
            'size' => [
                'medium' => ['url' => $item['src']['medium'] ?? $item['src']['large'] ?? ''],
                'tiny' => ['url' => $item['src']['tiny'] ?? $item['src']['small'] ?? ''],
                'small' => ['url' => $item['src']['small'] ?? $item['src']['medium'] ?? ''],
                'large' => ['url' => $item['src']['original'] ?? $item['src']['large2x'] ?? $item['src']['large'] ?? ''],
            ],
        ];
    }

    protected function formatCopyright(array $item): string
    {
        $copyrightFields = $this->config['copyright_fields'] ?? 'photographer_pexels';
        $parts = [];

        switch ($copyrightFields) {
            case 'photographer':
                if (isset($item['photographer']) || isset($item['user']['name'])) {
                    $parts[] = $item['photographer'] ?? $item['user']['name'];
                }
                break;
            case 'pexels':
                $parts[] = 'Pexels.com';
                break;
            case 'photographer_pexels':
            default:
                if (isset($item['photographer']) || isset($item['user']['name'])) {
                    $parts[] = $item['photographer'] ?? $item['user']['name'];
                }
                $parts[] = 'Pexels.com';
                break;
        }

        $copyright = implode(' / ', array_filter($parts));

        return $copyright;
    }

    protected function formatVideoSizes(array $item): array
    {
        $videoFiles = $item['video_files'] ?? [];
        $sizes = [];

        foreach ($videoFiles as $file) {
            $height = $file['height'] ?? 0;
            $link = $file['link'] ?? '';

            if (empty($link)) {
                continue;
            }

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

        // Fallback für fehlende Größen
        if (!empty($videoFiles)) {
            $fallbackUrl = '';
            foreach ($videoFiles as $file) {
                if (!empty($file['link'])) {
                    $fallbackUrl = $file['link'];
                    break;
                }
            }

            $requiredSizes = ['tiny', 'small', 'medium', 'large'];
            foreach ($requiredSizes as $size) {
                if (!isset($sizes[$size])) {
                    $sizes[$size] = ['url' => $fallbackUrl];
                }
            }
        }

        return $sizes;
    }

    public function import(string $url, string $filename, ?string $copyright = null): bool
    {
        if (!$this->isConfigured()) {
            throw new rex_exception('Pexels API key not configured');
        }

        try {
            $filename = $this->sanitizeFilename($filename);
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

            if (!$extension) {
                // Versuche Content-Type zu erhalten
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_NOBODY => true,
                    CURLOPT_HEADER => true,
                ]);
                $header = curl_exec($ch);
                curl_close($ch);

                if (preg_match('/Content-Type: image\/(\w+)/i', $header, $matches)) {
                    $extension = $matches[1];
                } else {
                    $extension = 'jpg';
                }
            }

            $filename = $filename . '.' . $extension;

            // Prüfe ob die Datei bereits existiert
            if (rex_media::get($filename)) {
                throw new rex_exception(
                    rex_i18n::msg('asset_import_file_exists', $filename),
                );
            }

            if ($this->downloadFile($url, $filename)) {
                if ($copyright) {
                    $media = rex_media::get($filename);
                    if ($media) {
                        $sql = rex_sql::factory();
                        $sql->setTable(rex::getTable('media'));
                        $sql->setWhere(['filename' => $filename]);
                        $sql->setValue('med_copyright', $copyright);
                        $sql->update();
                    }
                }
                return true;
            }

            return false;
        } catch (Exception $e) {
            rex_logger::factory()->log(LogLevel::ERROR, 'Import error: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function isPexelsUrl(string $query): bool
    {
        return (bool) preg_match('#^https?://(?:www\.)?pexels\.com/(?:photo|video)/#i', $query);
    }

    protected function handlePexelsUrl(string $query): array
    {
        $id = $this->extractIdFromUrl($query);
        if (!$id) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
        }

        // Try photo first
        $item = $this->getById($id, 'image');
        $type = 'image';

        // If not found, try video
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
                'total_pages' => 1,
            ];
        }

        return ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
    }

    protected function makeApiRequest(string $url, array $params = []): ?array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->config['apikey'],
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

    protected function getById(int $id, string $type = 'image'): ?array
    {
        $endpoint = 'video' === $type ? 'videos/' . $id : 'photos/' . $id;
        $baseUrl = 'video' === $type ? $this->apiUrlVideos : $this->apiUrl;

        return $this->makeApiRequest($baseUrl . $endpoint);
    }

    protected function extractIdFromUrl(string $url): ?int
    {
        // Match URLs like:
        // https://www.pexels.com/photo/brown-rocks-during-golden-hour-2014422/
        // https://www.pexels.com/video/drone-view-of-a-city-3129957/
        if (preg_match('#pexels\.com/(?:photo|video)/[^/]+-(\d+)/?$#i', $url, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    public function getDefaultOptions(): array
    {
        return [
            'type' => 'image',
            'orientation' => 'landscape',
            'size' => 'medium',
        ];
    }

    protected function getCacheLifetime(): int
    {
        // 24 Stunden Cache
        return 86400;
    }
}
