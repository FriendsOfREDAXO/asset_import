<?php

namespace FriendsOfRedaxo\AssetImport;

use Exception;
use rex;
use rex_file;
use rex_media;
use rex_media_service;
use rex_path;
use rex_sql;

use function count;
use function in_array;
use function is_array;

use const FILTER_VALIDATE_URL;
use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;
use const PHP_URL_PATH;

class DirectImporter
{
    /**
     * Vorschau einer URL generieren.
     */
    public static function preview(string $url): array
    {
        if (empty($url)) {
            throw new Exception('URL ist erforderlich');
        }

        // URL validieren
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception('Ungültige URL');
        }

        // Header abrufen um Dateityp und Größe zu prüfen
        $headers = @get_headers($url, 1);
        if (false === $headers) {
            throw new Exception('URL nicht erreichbar');
        }

        // Status Code prüfen
        $statusCode = self::getStatusCode($headers);
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new Exception('URL ist nicht verfügbar (HTTP ' . $statusCode . ')');
        }

        // Content-Type prüfen
        $contentType = self::getContentType($headers);
        $supportedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/webm', 'video/ogg',
        ];

        if (!in_array($contentType, $supportedTypes)) {
            throw new Exception('Nicht unterstützter Dateityp: ' . $contentType);
        }

        // Dateiname aus URL extrahieren
        $urlPath = parse_url($url, PHP_URL_PATH);
        $suggestedFilename = basename($urlPath);

        // Falls kein Dateiname in URL, einen generieren
        if (empty($suggestedFilename) || !pathinfo($suggestedFilename, PATHINFO_EXTENSION)) {
            $extension = self::getExtensionFromContentType($contentType);
            $suggestedFilename = 'import_' . date('Y-m-d_H-i-s') . '.' . $extension;
        }

        // Dateigröße
        $fileSize = self::getContentLength($headers);

        return [
            'url' => $url,
            'content_type' => $contentType,
            'file_size' => $fileSize,
            'file_size_formatted' => $fileSize ? self::formatBytes($fileSize) : 'Unbekannt',
            'suggested_filename' => $suggestedFilename,
            'is_image' => str_starts_with($contentType, 'image/'),
            'is_video' => str_starts_with($contentType, 'video/'),
            'preview_url' => $url, // Für Bilder kann die Original-URL als Vorschau verwendet werden
        ];
    }

    /**
     * Datei von URL importieren.
     */
    public static function import(string $url, string $filename, string $copyright = '', int $categoryId = 0): bool
    {
        if (empty($url) || empty($filename)) {
            throw new Exception('URL und Dateiname sind erforderlich');
        }

        // Dateiname bereinigen
        $filename = self::sanitizeFilename($filename);

        // Prüfen ob Datei bereits existiert
        if (rex_media::get($filename)) {
            throw new Exception('Eine Datei mit dem Namen "' . $filename . '" existiert bereits');
        }

        // Temporäre Datei herunterladen
        $tempFile = self::downloadFile($url);

        try {
            // Verwende REDAXO's Media-Upload Mechanismus
            $success = self::addMediaToPool($tempFile, $filename, $copyright, $categoryId);

            if (!$success) {
                throw new Exception('Fehler beim Hinzufügen zur Mediendatenbank');
            }

            // Prüfen ob die Datei wirklich im Medienpool ist
            $media = rex_media::get($filename);
            if (!$media) {
                throw new Exception('Datei wurde nicht korrekt im Medienpool erstellt');
            }

            return true;
        } catch (Exception $e) {
            // Temporäre Datei aufräumen
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
            throw $e;
        }
    }

    /**
     * Datei zum Medienpool hinzufügen (verwendet REDAXO's rex_media_service).
     */
    private static function addMediaToPool(string $tempFile, string $filename, string $copyright, int $categoryId): bool
    {
        try {
            // Media-Array für rex_media_service erstellen
            $media = [
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'file' => [
                    'name' => $filename,
                    'path' => $tempFile,
                    'tmp_name' => $tempFile,
                ],
                'category_id' => $categoryId,
            ];

            // REDAXO's Standard-Media-Service verwenden
            $result = rex_media_service::addMedia($media, true);

            if (false === $result) {
                throw new Exception('rex_media_service::addMedia() hat false zurückgegeben');
            }

            // Copyright nachträglich setzen (wie bei den Providern)
            if (!empty($copyright)) {
                $mediaObject = rex_media::get($filename);
                if ($mediaObject) {
                    $sql = rex_sql::factory();
                    $sql->setTable(rex::getTable('media'));
                    $sql->setWhere(['filename' => $filename]);
                    $sql->setValue('med_copyright', $copyright);
                    $sql->update();
                }
            }

            return true;
        } catch (Exception $e) {
            throw new Exception('Media-Pool-Fehler: ' . $e->getMessage());
        }
    }

    /**
     * Datei von URL herunterladen.
     */
    private static function downloadFile(string $url): string
    {
        $tempFile = rex_path::cache('asset_import_' . uniqid() . '.tmp');

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: REDAXO Asset Import/1.0',
                    'Accept: */*',
                ],
                'timeout' => 30,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if (false === $content) {
            throw new Exception('Fehler beim Herunterladen der Datei');
        }

        if (false === rex_file::put($tempFile, $content)) {
            throw new Exception('Fehler beim Speichern der temporären Datei');
        }

        return $tempFile;
    }

    /**
     * Status Code aus Headers extrahieren.
     */
    private static function getStatusCode(array $headers): int
    {
        $statusLine = $headers[0];
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    /**
     * Content-Type aus Headers extrahieren.
     */
    private static function getContentType(array $headers): string
    {
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';

        if (is_array($contentType)) {
            $contentType = $contentType[0];
        }

        // Nur den Haupttyp nehmen (ohne charset etc.)
        return strtok($contentType, ';');
    }

    /**
     * Content-Length aus Headers extrahieren.
     */
    private static function getContentLength(array $headers): ?int
    {
        $contentLength = $headers['Content-Length'] ?? $headers['content-length'] ?? null;

        if (is_array($contentLength)) {
            $contentLength = $contentLength[0];
        }

        return $contentLength ? (int) $contentLength : null;
    }

    /**
     * Dateiendung basierend auf Content-Type ermitteln.
     */
    private static function getExtensionFromContentType(string $contentType): string
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogg',
        ];

        return $extensions[$contentType] ?? 'bin';
    }

    /**
     * Dateiname bereinigen.
     */
    private static function sanitizeFilename(string $filename): string
    {
        // Gefährliche Zeichen entfernen
        $filename = preg_replace('/[^\w\-_\.]/', '_', $filename);

        // Mehrfache Unterstriche reduzieren
        $filename = preg_replace('/_+/', '_', $filename);

        // Führende/Nachfolgende Unterstriche entfernen
        $filename = trim($filename, '_');

        return $filename;
    }

    /**
     * Prüfen ob es sich um ein Bild handelt.
     */
    private static function isImage(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
    }

    /**
     * Bytes formatieren.
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= 1024 ** $pow;

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
