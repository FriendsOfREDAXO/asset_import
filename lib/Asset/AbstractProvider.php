<?php
namespace FriendsOfRedaxo\AssetImport\Asset;

use FriendsOfRedaxo\AssetImport\Provider\ProviderInterface;
use rex_path;
use rex_media;
use rex_sql;
use rex_logger;
use Psr\Log\LogLevel;

abstract class AbstractProvider implements ProviderInterface
{
    protected array $config = [];
    protected array $defaultConfig = [
        'copyright_fields' => 'all'  // Default Copyright-Einstellung
    ];

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $this->config = array_merge(
            $this->defaultConfig,
            \rex_addon::get('asset_import')->getConfig($this->getName()) ?? []
        );
    }

    protected function saveConfig(array $config): void
    {
        $this->config = array_merge($this->defaultConfig, $config);
        \rex_addon::get('asset_import')->setConfig($this->getName(), $this->config);
    }

    public function getDefaultOptions(): array 
    {
        return [
            'type' => 'all',
            'safesearch' => true,
            'lang' => \rex::getUser()->getLanguage()
        ];
    }

    /**
     * Führt die Suche durch und handhabt das Caching
     */
    public function search(string $query, int $page = 1, array $options = []): array
    {
        try {
            $cacheKey = $this->buildCacheKey($query, $page, $options);
            $cachedResult = $this->getCachedResponse($cacheKey);

            if ($cachedResult !== null) {
                return $cachedResult;
            }

            $result = $this->searchApi($query, $page, $options);
            $this->cacheResponse($cacheKey, $result);

            return $result;
        } catch (\Exception $e) {
            rex_logger::logException($e);
            return [
                'items' => [], 
                'total' => 0, 
                'page' => $page, 
                'total_pages' => 0
            ];
        }
    }

    /**
     * API-Suche - muss von konkreten Provider-Klassen implementiert werden
     */
    abstract protected function searchApi(string $query, int $page = 1, array $options = []): array;

    /**
     * Generiert einen eindeutigen Cache-Key
     */
    protected function buildCacheKey(string $query, int $page, array $options): string
    {
        $data = [
            'provider' => $this->getName(),
            'query' => $query,
            'page' => $page,
            'options' => $options,
            'lang' => \rex::getUser()->getLanguage()
        ];
        
        return md5(serialize($data));
    }

    /**
     * Liest gecachte Antwort
     */
    protected function getCachedResponse(string $cacheKey): ?array
    {
        $sql = rex_sql::factory();
        $sql->setQuery('
            SELECT response 
            FROM ' . \rex::getTable('asset_import_cache') . '
            WHERE provider = :provider 
            AND cache_key = :cache_key
            AND valid_until > NOW()',
            [
                'provider' => $this->getName(),
                'cache_key' => $cacheKey
            ]
        );
        
        if ($sql->getRows() > 0) {
            return json_decode($sql->getValue('response'), true);
        }
        
        return null;
    }

    /**
     * Speichert API-Antwort im Cache
     */
    protected function cacheResponse(string $cacheKey, array $response): void
    {
        // Alte Cache-Einträge löschen
        $sql = rex_sql::factory();
        $sql->setQuery('
            DELETE FROM ' . \rex::getTable('asset_import_cache') . '
            WHERE provider = :provider 
            AND (valid_until < NOW() OR cache_key = :cache_key)',
            [
                'provider' => $this->getName(),
                'cache_key' => $cacheKey
            ]
        );

        // Neuen Cache-Eintrag erstellen
        $sql = rex_sql::factory();
        $sql->setTable(\rex::getTable('asset_import_cache'));
        $sql->setValue('provider', $this->getName());
        $sql->setValue('cache_key', $cacheKey);
        $sql->setValue('response', json_encode($response));
        $sql->setValue('created', date('Y-m-d H:i:s'));
        $sql->setValue('valid_until', date('Y-m-d H:i:s', time() + $this->getCacheLifetime()));
        $sql->insert();
    }

    /**
     * Bereinigt Dateinamen
     */
    protected function sanitizeFilename(string $filename): string
    {
        $filename = mb_convert_encoding($filename, 'UTF-8', 'auto');
        $filename = \rex_string::normalize($filename);
        
        // Entferne alle nicht erlaubten Zeichen
        $filename = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $filename);
        
        // Entferne mehrfache Unterstriche
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Kürze zu lange Dateinamen
        $maxLength = 100;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        if (strlen($name) > $maxLength) {
            $name = substr($name, 0, $maxLength);
            $filename = $name . '.' . $extension;
        }
        
        return trim($filename, '_');
    }

    /**
     * Lädt eine Datei herunter
     */
    protected function downloadFile(string $url, string $filename): bool
    {
        try {
            $tmpFile = rex_path::cache('asset_import_' . uniqid() . '_' . $filename);
            
            $ch = curl_init($url);
            $fp = fopen($tmpFile, 'wb');
            
            if ($fp === false) {
                throw new \Exception('Could not open temporary file for writing');
            }
            
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_HEADER => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'REDAXO Asset Import'
            ]);
            
            $success = curl_exec($ch);
            
            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                throw new \Exception('HTTP error: ' . $httpCode);
            }
            
            curl_close($ch);
            fclose($fp);
            
            if ($success) {
                // Prüfe Dateigröße
                $fileSize = filesize($tmpFile);
                if ($fileSize === 0) {
                    throw new \Exception('Downloaded file is empty');
                }
                
                // Prüfe Dateiformat
                $mimeType = mime_content_type($tmpFile);
                if (!$this->isAllowedMimeType($mimeType)) {
                    throw new \Exception('Invalid file type: ' . $mimeType);
                }
                
                $media = [
                    'title' => pathinfo($filename, PATHINFO_FILENAME),
                    'file' => [
                        'name' => $filename,
                        'path' => $tmpFile,
                        'tmp_name' => $tmpFile
                    ],
                    'category_id' => \rex_post('category_id', 'int', 0)
                ];
                
                $result = \rex_media_service::addMedia($media, true);
                
                // Lösche temporäre Datei
                if (file_exists($tmpFile)) {
                    unlink($tmpFile);
                }
                
                return $result !== false;
            }
            
            return false;
            
        } catch (\Exception $e) {
            rex_logger::logException($e);
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            return false;
        }
    }

    /**
     * Prüft, ob der MIME-Type erlaubt ist
     */
    protected function isAllowedMimeType(string $mimeType): bool
    {
        $allowedTypes = [
            // Bilder
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            // Videos
            'video/mp4',
            'video/webm',
            'video/ogg'
        ];
        
        return in_array($mimeType, $allowedTypes);
    }

    /**
     * Import-Methode mit Copyright-Unterstützung
     */
    abstract public function import(string $url, string $filename, ?string $copyright = null): bool;

    /**
     * Gibt die Cache-Lebensdauer zurück
     */
    protected function getCacheLifetime(): int
    {
        return 86400; // 24 Stunden
    }
}
