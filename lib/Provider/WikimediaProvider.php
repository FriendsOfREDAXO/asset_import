<?php

namespace FriendsOfRedaxo\AssetImport\Provider;

use Exception;
use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;
use Psr\Log\LogLevel;
use rex;
use rex_addon;
use rex_exception;
use rex_i18n;
use rex_logger;
use rex_media;
use rex_media_service;
use rex_path;
use rex_sql;

use function array_merge;
use function array_slice;
use function count;
use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function implode;
use function is_array;
use function json_decode;
use function md5;
use function parse_url;
use function pathinfo;
use function preg_match;
use function sprintf;
use function str_replace;
use function strpos;
use function strtolower;
use function urlencode;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADER;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_SSL_VERIFYPEER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const CURLOPT_USERAGENT;
use const PATHINFO_EXTENSION;

class WikimediaProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://commons.wikimedia.org/w/api.php';
    protected int $itemsPerPage = 20;

    public function getName(): string
    {
        return 'wikimedia';
    }

    public function getTitle(): string
    {
        return 'Wikimedia Commons';
    }

    public function getIcon(): string
    {
        return 'fa-wikipedia-w';
    }

    public function getConfigFields(): array
    {
        return [
            [
                'label' => 'asset_import_provider_wikimedia_useragent',
                'name' => 'useragent',
                'type' => 'text',
                'notice' => 'asset_import_provider_wikimedia_useragent_notice',
            ],
            [
                'label' => 'asset_import_provider_copyright_fields',
                'name' => 'copyright_fields',
                'type' => 'select',
                'options' => [
                    ['label' => 'Author + Wikimedia Commons', 'value' => 'author_wikimedia'],
                    ['label' => 'Only Author', 'value' => 'author'],
                    ['label' => 'Only Wikimedia Commons', 'value' => 'wikimedia'],
                    ['label' => 'License Info', 'value' => 'license'],
                ],
                'notice' => 'asset_import_provider_copyright_notice',
            ],
            [
                'label' => 'asset_import_provider_set_copyright',
                'name' => 'set_copyright',
                'type' => 'select',
                'options' => [
                    ['label' => 'Nein', 'value' => '0'],
                    ['label' => 'Ja', 'value' => '1'],
                ],
                'notice' => 'asset_import_provider_set_copyright_notice',
            ],
            [
                'label' => 'asset_import_provider_wikimedia_file_types',
                'name' => 'file_types',
                'type' => 'select',
                'options' => [
                    ['label' => 'All file types', 'value' => 'all'],
                    ['label' => 'Images only', 'value' => 'images'],
                ],
                'notice' => 'asset_import_provider_wikimedia_file_types_notice',
            ],
        ];
    }

    public function isConfigured(): bool
    {
        // Wikimedia Commons API ist öffentlich, keine Konfiguration erforderlich
        // Nur User-Agent wird empfohlen
        return true;
    }

    protected function searchApi(string $query, int $page = 1, array $options = []): array
    {
        try {
            // Prüfe, ob es sich um eine direkte Wikimedia-URL handelt
            if ($this->isWikimediaUrl($query)) {
                return $this->handleWikimediaUrl($query);
            }

            $fileType = $options['file_type'] ?? 'all';
            $limit = $this->itemsPerPage;
            $offset = ($page - 1) * $limit;

            // Search-Parameter für MediaWiki API
            $searchParams = [
                'action' => 'query',
                'format' => 'json',
                'list' => 'search',
                'srsearch' => $this->buildSearchQuery($query, $fileType),
                'srnamespace' => '6', // File namespace
                'srlimit' => $limit,
                'sroffset' => $offset,
                'srprop' => 'size|wordcount|timestamp|snippet',
                'srinfo' => 'totalhits',
            ];

            $searchUrl = $this->apiUrl . '?' . http_build_query($searchParams);
            // Fix: Verhindere HTML-Encoding von & Zeichen
            $searchUrl = str_replace('&amp;', '&', $searchUrl);
            $searchResponse = $this->makeApiRequest($searchUrl);

            if (!$searchResponse || !isset($searchResponse['query']['search'])) {
                return $this->getEmptyResult($page);
            }

            $searchResults = $searchResponse['query']['search'];
            $totalHits = $searchResponse['query']['searchinfo']['totalhits'] ?? 0;

            if (empty($searchResults)) {
                return $this->getEmptyResult($page);
            }

            // Hole detaillierte Informationen für die gefundenen Dateien
            $titles = array_map(function ($result) {
                return $result['title'];
            }, $searchResults);

            $detailsParams = [
                'action' => 'query',
                'format' => 'json',
                'titles' => implode('|', $titles),
                'prop' => 'imageinfo',
                'iiprop' => 'url|size|mime|extmetadata|user|timestamp',
                'iiurlwidth' => '300',
                'iiurlheight' => '300',
                'iilimit' => '50',
            ];

            $detailsUrl = $this->apiUrl . '?' . http_build_query($detailsParams);
            // Fix: Verhindere HTML-Encoding von & Zeichen
            $detailsUrl = str_replace('&amp;', '&', $detailsUrl);
            $detailsResponse = $this->makeApiRequest($detailsUrl);

            if (!$detailsResponse || !isset($detailsResponse['query']['pages'])) {
                return $this->getEmptyResult($page);
            }

            $items = $this->processSearchResults($detailsResponse['query']['pages']);

            return [
                'items' => $items,
                'total' => $totalHits,
                'page' => $page,
                'total_pages' => ceil($totalHits / $limit),
            ];
        } catch (Exception $e) {
            rex_logger::logException($e);
            return $this->getEmptyResult($page);
        }
    }

    protected function isWikimediaUrl(string $url): bool
    {
        return strpos($url, 'commons.wikimedia.org') !== false ||
               strpos($url, 'upload.wikimedia.org') !== false;
    }

    protected function handleWikimediaUrl(string $url): array
    {
        try {
            // Extrahiere Dateinamen aus URL
            $filename = $this->extractFilenameFromUrl($url);
            if (!$filename) {
                return $this->getEmptyResult(1);
            }

            // Hole Informationen zur spezifischen Datei
            $params = [
                'action' => 'query',
                'format' => 'json',
                'titles' => 'File:' . $filename,
                'prop' => 'imageinfo',
                'iiprop' => 'url|size|mime|extmetadata|user|timestamp',
                'iiurlwidth' => '300',
                'iiurlheight' => '300',
            ];

            $apiUrl = $this->apiUrl . '?' . http_build_query($params);
            // Fix: Verhindere HTML-Encoding von & Zeichen
            $apiUrl = str_replace('&amp;', '&', $apiUrl);
            $response = $this->makeApiRequest($apiUrl);

            if (!$response || !isset($response['query']['pages'])) {
                return $this->getEmptyResult(1);
            }

            $items = $this->processSearchResults($response['query']['pages']);

            return [
                'items' => $items,
                'total' => count($items),
                'page' => 1,
                'total_pages' => 1,
            ];
        } catch (Exception $e) {
            rex_logger::logException($e);
            return $this->getEmptyResult(1);
        }
    }

    protected function extractFilenameFromUrl(string $url): ?string
    {
        // Verschiedene URL-Formate unterstützen
        if (preg_match('/File:([^&\?]+)/', $url, $matches)) {
            return urldecode($matches[1]);
        }

        if (preg_match('/\/([^\/]+\.(jpg|jpeg|png|gif|svg|webp|pdf|mp4|ogv|webm|ogg|mp3|wav))$/i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function buildSearchQuery(string $query, string $fileType): string
    {
        $searchQuery = $query;

        // Filetype-Filter für spezifische Bildformate
        if ($fileType === 'images') {
            // Nur die gewünschten Formate: JPG, PNG, SVG, WebP
            $searchQuery .= ' (filetype:jpg OR filetype:jpeg OR filetype:png OR filetype:svg OR filetype:webp)';
        }

        return $searchQuery;
    }

    protected function processSearchResults(array $pages): array
    {
        $items = [];

        foreach ($pages as $page) {
            if (!isset($page['imageinfo']) || empty($page['imageinfo'])) {
                continue;
            }

            $imageInfo = $page['imageinfo'][0];
            $metadata = $imageInfo['extmetadata'] ?? [];

            // MIME-Type prüfen - nur erlaubte Typen verarbeiten
            $mimeType = $imageInfo['mime'] ?? '';
            if (!$this->isAllowedMimeType($mimeType)) {
                continue; // Überspringe nicht erlaubte Dateitypen
            }

            // Basis-Informationen
            $title = str_replace('File:', '', $page['title']);
            $author = $this->extractAuthor($metadata, $imageInfo);
            $copyright = $this->buildCopyright($metadata, $author);
            $description = $this->extractDescription($metadata);

            // URLs und Größen
            $previewUrl = $imageInfo['thumburl'] ?? $imageInfo['url'];
            $sizes = $this->buildSizeVariants($imageInfo);

            // Asset-Typ bestimmen
            $type = $this->determineAssetType($mimeType);

            $items[] = [
                'id' => md5($imageInfo['url']),
                'preview_url' => $previewUrl,
                'title' => $title,
                'description' => $description,
                'author' => $author,
                'copyright' => $copyright,
                'type' => $type,
                'size' => $sizes,
                'license' => $this->extractLicense($metadata),
                'original_url' => $imageInfo['url'],
                'file_size' => $imageInfo['size'] ?? 0,
                'mime_type' => $mimeType,
                'timestamp' => $imageInfo['timestamp'] ?? '',
            ];
        }

        return $items;
    }

    protected function extractAuthor(array $metadata, array $imageInfo): string
    {
        // Versuche verschiedene Metadaten-Felder für den Autor
        if (isset($metadata['Artist']['value'])) {
            return strip_tags($metadata['Artist']['value']);
        }

        if (isset($metadata['Credit']['value'])) {
            return strip_tags($metadata['Credit']['value']);
        }

        if (isset($imageInfo['user'])) {
            return $imageInfo['user'];
        }

        return 'Unknown';
    }

    protected function extractDescription(array $metadata): string
    {
        if (isset($metadata['ImageDescription']['value'])) {
            return strip_tags($metadata['ImageDescription']['value']);
        }

        if (isset($metadata['ObjectName']['value'])) {
            return strip_tags($metadata['ObjectName']['value']);
        }

        return '';
    }

    protected function extractLicense(array $metadata): string
    {
        if (isset($metadata['LicenseShortName']['value'])) {
            return $metadata['LicenseShortName']['value'];
        }

        if (isset($metadata['License']['value'])) {
            return strip_tags($metadata['License']['value']);
        }

        return 'Unknown License';
    }

    protected function buildCopyright(array $metadata, string $author): string
    {
        $copyrightType = $this->config['copyright_fields'] ?? 'author_wikimedia';

        switch ($copyrightType) {
            case 'author':
                return $author;

            case 'wikimedia':
                return 'Wikimedia Commons';

            case 'license':
                return $this->extractLicense($metadata);

            case 'author_wikimedia':
            default:
                if ($author && $author !== 'Unknown') {
                    return $author . ' / Wikimedia Commons';
                }
                return 'Wikimedia Commons';
        }
    }

    protected function buildSizeVariants(array $imageInfo): array
    {
        $originalUrl = $imageInfo['url'];
        $width = $imageInfo['width'] ?? 0;
        $height = $imageInfo['height'] ?? 0;

        // Basis-URL für Thumbnails (MediaWiki thumb.php)
        $thumbBaseUrl = str_replace('/commons/', '/commons/thumb/', $originalUrl);

        $sizes = [
            'original' => ['url' => $originalUrl, 'width' => $width, 'height' => $height],
        ];

        // Generiere verschiedene Thumbnail-Größen
        $thumbnailSizes = [
            'tiny' => 150,
            'small' => 300,
            'medium' => 600,
            'large' => 1200,
        ];

        foreach ($thumbnailSizes as $sizeName => $maxWidth) {
            if ($width > $maxWidth) {
                $thumbHeight = intval(($height * $maxWidth) / $width);
                $thumbUrl = $thumbBaseUrl . '/' . $maxWidth . 'px-' . basename($originalUrl);
                $sizes[$sizeName] = [
                    'url' => $thumbUrl,
                    'width' => $maxWidth,
                    'height' => $thumbHeight,
                ];
            } else {
                // Wenn das Original kleiner ist, verwende das Original
                $sizes[$sizeName] = $sizes['original'];
            }
        }

        return $sizes;
    }

    protected function determineAssetType(string $mimeType): string
    {
        if (strpos($mimeType, 'image/') === 0) {
            return 'image';
        }

        return 'file';
    }

    protected function makeApiRequest(string $url): ?array
    {
        $curl = curl_init();

        $userAgent = $this->config['useragent'] ?? 'REDAXO Asset Import Bot/1.0';

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($curl, CURLOPT_HEADER, false);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new rex_exception('cURL Error: ' . $error);
        }

        curl_close($curl);

        if ($httpCode !== 200) {
            throw new rex_exception('HTTP Error: ' . $httpCode);
        }

        // Dekodiere JSON-Antwort
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            rex_logger::factory()->log(LogLevel::ERROR, 'Wikimedia API JSON Decode Error: ' . json_last_error_msg() . ' - URL: ' . $url);
            throw new rex_exception('JSON Decode Error: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }

    protected function getEmptyResult(int $page): array
    {
        return [
            'items' => [],
            'total' => 0,
            'page' => $page,
            'total_pages' => 0,
        ];
    }

    public function import(string $url, string $filename, ?string $copyright = null): bool
    {
        try {
            // Bereinige den Dateinamen für REDAXO Media
            $cleanFilename = $this->sanitizeMediaFilename($filename);
            
            // Nutze die download-Methode der Parent-Klasse mit bereinigtem Dateinamen
            $success = $this->downloadFile($url, $cleanFilename);
            
            // Copyright setzen, wenn gewünscht und vorhanden
            if ($success && $copyright && $this->shouldSetCopyright()) {
                // Warte kurz, damit REDAXO die Datei verarbeiten kann
                usleep(100000); // 0.1 Sekunden
                
                $media = rex_media::get($cleanFilename);
                if ($media) {
                    $sql = rex_sql::factory();
                    $sql->setTable(rex::getTable('media'));
                    $sql->setWhere(['filename' => $cleanFilename]);
                    $sql->setValue('med_copyright', $copyright);
                    $sql->update();
                }
            }
            
            return $success;
        } catch (Exception $e) {
            rex_logger::logException($e);
            return false;
        }
    }

    /**
     * Bereinigt Dateinamen für REDAXO Media
     */
    protected function sanitizeMediaFilename(string $filename): string
    {
        // Entferne problematische Zeichen
        $cleaned = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        // Mehrfache Unterstriche reduzieren
        $cleaned = preg_replace('/_+/', '_', $cleaned);
        // Führende/abschließende Unterstriche entfernen
        $cleaned = trim($cleaned, '_');
        
        return $cleaned;
    }

    /**
     * Prüft, ob Copyright-Informationen gesetzt werden sollen
     */
    protected function shouldSetCopyright(): bool
    {
        return ($this->config['set_copyright'] ?? '0') === '1';
    }

    public function getDefaultOptions(): array
    {
        return array_merge(parent::getDefaultOptions(), [
            'file_type' => 'all',
        ]);
    }

    /**
     * Prüft, ob der MIME-Type erlaubt ist.
     */
    protected function isAllowedMimeType(string $mimeType): bool
    {
        $allowedTypes = [
            // Nur die gewünschten Bildformate
            'image/jpeg',
            'image/png', 
            'image/svg+xml',
            'image/webp',
            // Optional: PDF-Dokumente
            'application/pdf',
        ];

        return in_array($mimeType, $allowedTypes);
    }
}
