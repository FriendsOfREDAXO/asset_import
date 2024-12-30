# Asset Import für REDAXO

Ein AddOn zum Importieren von Medien aus verschiedenen Quellen (Pixabay, Unsplash etc.) direkt in den REDAXO Medienpool.

## Features

- Bildsuche über verschiedene Provider
- Video-Suche (abhängig vom Provider)
- Vorschau der Assets
- Direkter Import in den Medienpool
- Kategoriezuweisung
- 24h API-Cache für bessere Performance
- Erweiterbar durch weitere Provider

## Installation

1. Im REDAXO Installer das AddOn `asset_import` herunterladen
2. Installation durchführen
3. Provider konfigurieren unter "Asset Import > Einstellungen"

## Provider registrieren

Provider können in der boot.php eines anderen AddOns registriert werden:

```php
// Provider-Klasse implementieren
namespace MyAddon\Provider;

class MyProvider extends \FriendsOfRedaxo\AssetImport\Asset\AbstractProvider 
{
    // Provider Implementation
}

// Provider im Asset Import registrieren
if (\rex_addon::get('asset_import')->isAvailable()) {
    \FriendsOfRedaxo\AssetImport\AssetImporter::registerProvider(MyProvider::class);
}
```

## Provider implementieren

Ein Provider muss das ProviderInterface implementieren:

```php
public function getName(): string;        // Eindeutiger Name
public function getTitle(): string;       // Anzeigename
public function getIcon(): string;        // FontAwesome Icon
public function isConfigured(): bool;     // Prüft Konfiguration
public function getConfigFields(): array; // Konfigurationsfelder
public function search(): array;          // Suchmethode
public function import(): bool;           // Import Methode
public function getDefaultOptions(): array; // Standard-Optionen
```

Die abstrakte Klasse `AbstractProvider` bietet bereits:
- API Caching (24h)
- Medienpool Import
- Konfigurationsverwaltung


## Beispiel Provider für File import aus lokalem Ordner

### Was macht der Provider?

Der FTP Upload Provider ermöglicht es, Dateien aus einem definierten Upload-Verzeichnis in den REDAXO Medienpool zu importieren. Er ist ein gutes Beispiel dafür, wie ein eigener Provider für das Asset Import AddOn implementiert werden kann.

### Features

- Durchsucht das `ftpupload`-Verzeichnis im REDAXO-Root rekursiv
- Unterstützt Bilder (jpg, jpeg, png, gif, webp) und Videos (mp4, webm)
- Sortiert Dateien nach Änderungsdatum (neueste zuerst)
- Bietet Suche nach Dateinamen
- Paginierte Ergebnisse (20 pro Seite)

### Installation

1. Erstelle Provider-Klasse in deinem AddOn:

```php
// in /redaxo/src/addons/project/lib/Provider/FtpUploadProvider.php

<?php
namespace Project\Provider;

use FriendsOfRedaxo\AssetImport\Asset\AbstractProvider;
use rex_path;
use rex_url;

class FtpUploadProvider extends AbstractProvider
{
    public function getName(): string 
    {
        return 'ftpupload';
    }

    public function getTitle(): string 
    {
        return 'FTP Upload';
    }

    public function getIcon(): string 
    {
        return 'fa-upload';
    }

    public function isConfigured(): bool 
    {
        return true;
    }

    public function getConfigFields(): array 
    {
        return [];
    }

    public function getDefaultOptions(): array 
    {
        return [
            'type' => 'image'
        ];
    }

    protected function searchApi(string $query, int $page = 1, array $options = []): array 
    {
        $items = [];
        $type = $options['type'] ?? 'image';
        $uploadPath = rex_path::base('ftpupload');
        
        if (is_dir($uploadPath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploadPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $fileType = $this->getFileType($file->getFilename());
                    
                    // Nur Bilder und Videos berücksichtigen
                    if ($fileType && ($type === 'all' || $type === $fileType)) {
                        if (empty($query) || stripos($file->getFilename(), $query) !== false) {
                            $relativePath = str_replace($uploadPath, '', $file->getPathname());
                            $relativePath = ltrim($relativePath, '/\\');
                            $filename = $file->getFilename();
                            
                            $items[] = [
                                'id' => md5($relativePath),
                                'preview_url' => rex_url::base('ftpupload/' . $relativePath),
                                'title' => $filename,
                                'author' => 'FTP Upload',
                                'type' => $fileType,
                                'size' => [
                                    'original' => ['url' => rex_url::base('ftpupload/' . $relativePath)]
                                ]
                            ];
                        }
                    }
                }
            }

            // Sortiere nach Datum absteigend
            usort($items, function($a, $b) use ($uploadPath) {
                $timeA = filemtime($uploadPath . '/' . $a['title']);
                $timeB = filemtime($uploadPath . '/' . $b['title']);
                return $timeB - $timeA;
            });

            // Paginierung
            $itemsPerPage = 20;
            $offset = ($page - 1) * $itemsPerPage;
            $items = array_slice($items, $offset, $itemsPerPage);
        }

        $total = count($items);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / 20)
        ];
    }

    private function getFileType(string $filename): ?string 
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $types = [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'video' => ['mp4', 'webm']
        ];
        
        foreach ($types as $type => $extensions) {
            if (in_array($ext, $extensions)) {
                return $type;
            }
        }
        
        return null;
    }
}
```

2. Provider in deinem AddOn registrieren:

```php
// in /redaxo/src/addons/project/boot.php

if (\rex_addon::get('asset_import')->isAvailable()) {
    \FriendsOfRedaxo\AssetImport\AssetImporter::registerProvider(\Project\Provider\FtpUploadProvider::class);
}
```

3. `ftpupload`-Verzeichnis im REDAXO-Root erstellen und Schreibrechte setzen

### Verzeichnisstruktur

```
redaxo/
├── src/
│   └── addons/
│       └── project/
│           ├── lib/
│           │   └── Provider/
│           │       └── FtpUploadProvider.php
│           └── boot.php
└── ftpupload/
    ├── bilder/
    └── videos/
```

### Funktionsweise

1. **Verzeichnis-Scan:**
   - Durchsucht das `ftpupload`-Verzeichnis rekursiv
   - Filtert nach unterstützten Dateitypen
   - Berücksichtigt nur Bilder und Videos

2. **Suche:**
   - Filtert Dateien nach Suchbegriff im Dateinamen
   - Typ-Filter für Bilder oder Videos

3. **Sortierung & Paginierung:**
   - Sortiert nach Änderungsdatum (neueste zuerst)
   - 20 Einträge pro Seite
   - Unterstützt Blättern durch die Ergebnisse

4. **Import:**
   - Nutzt den Standard-Import des AbstractProvider
   - Importiert direkt in den Medienpool

### Erweiterungsmöglichkeiten

- Konfigurierbare Verzeichnisse
- Zusätzliche Dateitypen
- Thumbnails für Videos
- Metadaten aus EXIF/IPTC
- Automatische Kategoriezuweisung basierend auf Unterordnern

### Lizenz

MIT


## Lizenz

MIT Lizenz, siehe [LICENSE](LICENSE)

## Autoren

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO


**Project Lead**

[Thomas Skerbis](https://github.com/skerbis)  
