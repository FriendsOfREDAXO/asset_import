<?php
namespace FriendsOfRedaxo\AssetImport\Asset;

use FriendsOfRedaxo\AssetImport\Provider\ProviderInterface;
use Psr\Log\LogLevel;

abstract class AbstractProvider implements ProviderInterface
{
    protected array $config = [];
    protected $mediaCategories = [];

    /**
     * Cache Lifetime in Seconds (24 hours)
     */
    protected const CACHE_LIFETIME = 86400;

    public function __construct()
    {
        $this->config = \rex_addon::get('asset_import')->getConfig($this->getName()) ?? [];
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    protected function getConfig(string $key)
    {
        return $this->config[$key] ?? null;
    }

    public function search(string $query, int $page = 1, array $options = []): array
    {
        // Bereinige abgelaufene Cache-Einträge
        $this->cleanupCache();

        // Cache-Key generieren
        $cacheKey = md5($query . serialize($options) . $page);

        // Prüfe Cache
        if ($cachedData = $this->getFromCache($cacheKey)) {
            return $cachedData;
        }

        // API abfragen
        $results = $this->searchApi($query, $page, $options);

        // Speichere im Cache
        $this->saveToCache($cacheKey, $results);

        return $results;
    }

    /**
     * Bereinigt den Cache von alten Einträgen für alle Provider
     */
    protected function cleanupCache(): void
    {
        try {
            $sql = \rex_sql::factory();
            
            // Lösche abgelaufene Einträge
            $sql->setQuery('DELETE FROM ' . \rex::getTable('asset_import_cache') . ' WHERE valid_until < NOW()');
            
            $deletedRows = $sql->getRows();
            
            if ($deletedRows > 0) {
                \rex_logger::factory()->log(
                    LogLevel::INFO,
                    'Cleaned up {count} expired cache entries',
                    ['count' => $deletedRows],
                    __FILE__,
                    __LINE__
                );
            }
        } catch (\Exception $e) {
            \rex_logger::factory()->log(
                LogLevel::ERROR,
                'Error cleaning up cache: {message}',
                ['message' => $e->getMessage()],
                __FILE__,
                __LINE__
            );
        }
    }

    protected function getFromCache(string $key): ?array
    {
        try {
            $sql = \rex_sql::factory();
            $sql->setQuery(
                'SELECT response FROM ' . \rex::getTable('asset_import_cache') . ' 
                WHERE provider = :provider 
                AND cache_key = :key 
                AND valid_until > NOW()',
                [
                    'provider' => $this->getName(),
                    'key' => $key
                ]
            );

            if ($sql->getRows() === 1) {
                return json_decode($sql->getValue('response'), true);
            }
        } catch (\Exception $e) {
            \rex_logger::factory()->log(LogLevel::ERROR, 'Cache read error: {message}', ['message' => $e->getMessage()], __FILE__, __LINE__);
        }

        return null;
    }

    protected function saveToCache(string $key, array $data): void
    {
        try {
            $sql = \rex_sql::factory();
            $sql->setTable(\rex::getTable('asset_import_cache'));
            $sql->setValue('provider', $this->getName());
            $sql->setValue('cache_key', $key);
            $sql->setValue('response', json_encode($data));
            $sql->setValue('created', date('Y-m-d H:i:s'));
            $sql->setValue('valid_until', date('Y-m-d H:i:s', time() + self::CACHE_LIFETIME));
            $sql->insert();
        } catch (\Exception $e) {
            \rex_logger::factory()->log(LogLevel::ERROR, 'Cache write error: {message}', ['message' => $e->getMessage()], __FILE__, __LINE__);
        }
    }

    protected function downloadFile(string $url, string $filename): bool
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $data = curl_exec($ch);
            
            if ($data === false) {
                throw new \Exception('Download failed: ' . curl_error($ch));
            }
            
            curl_close($ch);
            
            // Prepare media category
            $categoryId = \rex_request('category_id', 'int', 0);

            // Create media
            $media = \rex_media::get($filename);
            if ($media) {
                $filename = $this->generateUniqueFilename($filename);
            }

            $path = \rex_path::media($filename);
            if (file_put_contents($path, $data)) {
                \rex_media_service::mediaUpdated($filename, $filename, $categoryId);
                return true;
            }
        } catch (\Exception $e) {
            \rex_logger::factory()->log(LogLevel::ERROR, 'Download error: {message}', ['message' => $e->getMessage()], __FILE__, __LINE__);
        }

        return false;
    }

    protected function sanitizeFilename(string $filename): string
    {
        $filename = strtolower($filename);
        $filename = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $filename);
        $filename = preg_replace('/[^a-z0-9\-_]/', '_', $filename);
        $filename = preg_replace('/_{2,}/', '_', $filename);
        $filename = trim($filename, '_');
        return $filename;
    }

    protected function generateUniqueFilename(string $filename): string
    {
        $pathInfo = pathinfo($filename);
        $basename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        
        $counter = 1;
        $newFilename = $filename;
        
        while (file_exists(\rex_path::media($newFilename))) {
            $newFilename = $basename . '_' . $counter . '.' . $extension;
            $counter++;
        }
        
        return $newFilename;
    }

    abstract protected function searchApi(string $query, int $page = 1, array $options = []): array;
}
