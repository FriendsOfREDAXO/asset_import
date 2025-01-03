<?php
namespace FriendsOfRedaxo\AssetImport\Asset;

use FriendsOfRedaxo\AssetImport\Provider\ProviderInterface;

abstract class AbstractProvider implements ProviderInterface
{
    protected array $config = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $this->config = \rex_addon::get('asset_import')->getConfig($this->getName()) ?? [];
    }

    protected function saveConfig(array $config): void
    {
        \rex_addon::get('asset_import')->setConfig($this->getName(), $config);
    }

    public function getDefaultOptions(): array 
    {
        return [];
    }

    public function search(string $query, int $page = 1, array $options = []): array
    {
        $cacheKey = $this->buildCacheKey($query, $page, $options);
        $cachedResult = $this->getCachedResponse($cacheKey);

        if ($cachedResult !== null) {
            return $cachedResult;
        }

        $result = $this->searchApi($query, $page, $options);
        $this->cacheResponse($cacheKey, $result);

        return $result;
    }

    abstract protected function searchApi(string $query, int $page = 1, array $options = []): array;

    /**
     * Download a file from URL and import it into the media pool
     */
    protected function downloadFile(string $url, string $filename): bool
    {
        try {
            $tmpFile = \rex_path::cache('asset_import_' . uniqid() . '_' . $filename);
            
            $ch = curl_init($url);
            $fp = fopen($tmpFile, 'wb');
            
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_HEADER => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $success = curl_exec($ch);
            
            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }
            
            curl_close($ch);
            fclose($fp);
            
            if ($success) {
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

                // After successful import, update copyright information if available
                if ($result && isset($result['filename'])) {
                    $this->updateCopyrightInfo($result['filename']);
                }

                unlink($tmpFile);
                
                return $result !== false;
            }
            
            return false;
            
        } catch (\Exception $e) {
            \rex_logger::logException($e);
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            return false;
        }
    }

   protected function updateCopyrightInfo(string $filename): void
{
    try {
        dump('Starting copyright update for: ' . $filename); // DUMP 1
        
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT id FROM ' . \rex::getTable('media') . ' WHERE filename = ? LIMIT 1', [$filename]);
        
        if ($sql->getRows() > 0) {
            $copyright = $this->getCopyrightInfo([
                'filename' => $filename,
                'provider' => $this->getName(),
                'provider_title' => $this->getTitle()
            ]);

            dump('Generated copyright:', $copyright); // DUMP 2

            if ($copyright !== null) {
                $sql->setTable(\rex::getTable('media'));
                $sql->setWhere(['filename' => $filename]);
                $sql->setValue('med_copyright', $copyright);
                $sql->update();
                
                dump('SQL Update executed with:', [  // DUMP 3
                    'table' => \rex::getTable('media'),
                    'where' => ['filename' => $filename],
                    'copyright' => $copyright
                ]);
            }
        }
    } catch (\Exception $e) {
        dump('Error in updateCopyrightInfo:', $e->getMessage()); // DUMP 4
        \rex_logger::logException($e);
    }
}
    /**
     * Default implementation for copyright information
     * Can be overridden by specific providers
     */
    public function getCopyrightInfo(array $item): ?string 
    {
        $provider = $item['provider_title'] ?? $this->getTitle();
        return sprintf('© %s', $provider);
    }

    /**
     * Default implementation for field mapping
     * Can be overridden by specific providers
     */
    public function getFieldMapping(): array
    {
        return [
            'author' => 'Photographer/Author',
            'license' => 'License',
            'provider' => 'Provider',
            'source_url' => 'Source URL'
        ];
    }

    protected function buildCacheKey(string $query, int $page, array $options): string
    {
        return md5($this->getName() . $query . $page . serialize($options));
    }

    protected function getCachedResponse(string $cacheKey): ?array
    {
        $sql = \rex_sql::factory();
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

    protected function cacheResponse(string $cacheKey, array $response): void
    {
        // Delete old cache entries
        $sql = \rex_sql::factory();
        $sql->setQuery('
            DELETE FROM ' . \rex::getTable('asset_import_cache') . '
            WHERE provider = :provider 
            AND (valid_until < NOW() OR cache_key = :cache_key)',
            [
                'provider' => $this->getName(),
                'cache_key' => $cacheKey
            ]
        );

        // Create new cache entry
        $sql = \rex_sql::factory();
        $sql->setTable(\rex::getTable('asset_import_cache'));
        $sql->setValue('provider', $this->getName());
        $sql->setValue('cache_key', $cacheKey);
        $sql->setValue('response', json_encode($response));
        $sql->setValue('created', date('Y-m-d H:i:s'));
        $sql->setValue('valid_until', date('Y-m-d H:i:s', strtotime('+24 hours')));
        $sql->insert();
    }

    protected function sanitizeFilename(string $filename): string
    {
        $filename = mb_convert_encoding($filename, 'UTF-8', 'auto');
        $filename = \rex_string::normalize($filename);
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        return trim($filename, '_');
    }
}
