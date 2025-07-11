# Asset Import für REDAXO

Ein AddOn zum Importieren von Medien aus verschiedenen Quellen (Pixabay, Pexels etc.) direkt in den REDAXO Medienpool.

![Screenshot](https://github.com/FriendsOfREDAXO/asset_import/blob/assets/screen.png?raw=true)

## Features

- Bildsuche über verschiedene Provider
- Direkte Suche in **Wikimedia Commons** (Wikipedia Medien)
- Vorschau der Assets mit Metadaten
- Direkter Import in den Medienpool
- **Copyright-Informationen** automatisch übernehmen
- Kategoriezuweisung
- 24h API-Cache für bessere Performance
- Erweiterbar durch weitere Provider

## Verfügbare Provider

### Pixabay
- Kostenlose Stock-Fotos und -Videos
- API-Key erforderlich
- Kommerzielle Nutzung möglich

### Pexels
- Hochqualitative Stock-Fotos
- API-Key erforderlich
- Alle Bilder kostenlos nutzbar

### **Wikimedia Commons** ⭐ **NEU**
- Freie Medien der Wikipedia-Projekte
- **Keine API-Key erforderlich**
- Millionen von freien Bildern, SVGs und Dokumenten
- Automatische Copyright- und Lizenz-Übernahme
- Unterstützt JPG, PNG, SVG, WebP und PDF

## Installation

1. Im REDAXO Installer das AddOn `asset_import` herunterladen
2. Installation durchführen
3. Provider konfigurieren unter "Asset Import > Einstellungen"

## Konfiguration

### Wikimedia Commons (empfohlen)

**Wikimedia Commons** ist sofort einsatzbereit - kein API-Key erforderlich!

1. **Gehe zu:** Asset Import > Einstellungen > Wikimedia Commons
2. **Konfiguriere:**
   - **User-Agent:** `DeineWebsite.de Import/1.0 (deine@email.de)`
   - **Copyright-Felder:** Wähle das gewünschte Format:
     - `Author + Wikimedia Commons` → "Max Mustermann / Wikimedia Commons"
     - `Only Author` → "Max Mustermann"
     - `License Info` → "CC BY-SA 4.0"
   - **Copyright-Info setzen:** `Ja` (für automatische Copyright-Übernahme)
   - **Dateitypen:** `Images only` oder `All file types`
3. **Speichere die Einstellungen**
4. **Fertig!** Du kannst sofort loslegen

## Quick-Start: Erstes Bild importieren

### Mit Wikimedia Commons (empfohlen für Einsteiger)

1. **Gehe zu:** AddOns > Asset Import
2. **Wähle:** Wikimedia Commons
3. **Suche nach:** `cat` oder `Berlin`
4. **Klicke auf:** "Importieren" bei einem Bild deiner Wahl
5. **Prüfe:** Medienpool - dein Bild ist da mit Copyright-Info! 🎉

Das wars! Kein API-Key, keine komplizierte Einrichtung.

### Mit Pixabay/Pexels

1. **Erstelle API-Key** (siehe Links oben)
2. **Gehe zu:** Asset Import > Einstellungen > Pixabay/Pexels  
3. **Trage API-Key ein** und speichere
4. **Gehe zu:** Asset Import und wähle den Provider
5. **Suche und importiere** wie bei Wikimedia

## FAQ

### Warum Wikimedia Commons wählen?

- ✅ **Kostenlos:** Keine API-Limits oder Kosten
- ✅ **Rechtssicher:** Alle Medien sind frei nutzbar
- ✅ **Vielfältig:** Millionen professioneller Bilder und Grafiken
- ✅ **Qualität:** Oft bessere Qualität als Stock-Foto-Seiten
- ✅ **Einzigartig:** Historische und wissenschaftliche Inhalte

### Was bedeuten die Copyright-Optionen?

- **Author + Wikimedia Commons:** `"Max Mustermann / Wikimedia Commons"`
- **Only Author:** `"Max Mustermann"`
- **Only Wikimedia Commons:** `"Wikimedia Commons"`  
- **License Info:** `"CC BY-SA 4.0"`

### Welche Dateiformate werden unterstützt?

**Wikimedia Commons:**
- **Bilder:** JPG, PNG, SVG, WebP
- **Dokumente:** PDF

**Pixabay/Pexels:**
- **Bilder:** JPG, PNG, WebP
- **Videos:** MP4, WebM (je nach Provider)

### Wo finde ich die Copyright-Informationen?

Nach dem Import findest du die Copyright-Informationen im **Medienpool**:
1. **Gehe zu:** Medienpool
2. **Klicke** auf dein importiertes Bild
3. **Schaue** ins Feld "**Copyright**" (nicht Beschreibung!)

### Kann ich auch Videos importieren?

- **Wikimedia Commons:** Nein, nur Bilder und PDFs
- **Pixabay/Pexels:** Ja, Videos werden unterstützt

### Pixabay & Pexels

Für Pixabay und Pexels benötigst du einen kostenlosen API-Key:
- [Pixabay API-Key erstellen](https://pixabay.com/api/docs/)
- [Pexels API-Key erstellen](https://www.pexels.com/api/key/)

## Berechtigungen

Das AddOn bringt eine eigene Berechtigung mit:

- **`asset_import[]`** - Berechtigt zum Zugriff auf das gesamte Asset Import AddOn

Diese Berechtigung kann in der Benutzerverwaltung (System > Benutzer) einzelnen Benutzern oder Rollen zugewiesen werden. Ohne diese Berechtigung ist das AddOn für den Benutzer nicht sichtbar.

Die Einstellungsseite erfordert zusätzlich Administratorrechte (`admin[]`).

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

## Wikimedia Commons Provider

### Überblick

Der **Wikimedia Commons Provider** ermöglicht den direkten Import von freien Medien aus der größten Sammlung freier Inhalte der Welt. Wikimedia Commons ist die zentrale Mediendatenbank aller Wikipedia-Projekte und enthält Millionen von Bildern, SVGs, Audio- und Videodateien unter freien Lizenzen.

### Besondere Features

- ✅ **Kein API-Key erforderlich** - sofort einsatzbereit
- ✅ **Millionen freie Medien** - Fotos, Grafiken, historische Bilder
- ✅ **Automatische Copyright-Übernahme** - Autor und Lizenzinfo werden automatisch gesetzt
- ✅ **Verschiedene Formate** - JPG, PNG, SVG, WebP, PDF
- ✅ **Direkte URL-Eingabe** - Wikimedia-Links direkt importieren
- ✅ **Erweiterte Suche** - mit Dateityp-Filtern

### Verwendung

1. **Textsuche:** Gib Suchbegriffe ein (z.B. "Berlin", "cat", "nature")
2. **URL-Import:** Kopiere Wikimedia-URLs direkt in das Suchfeld
3. **Dateityp-Filter:** Wähle zwischen "Alle Dateien" oder "Nur Bilder"
4. **Copyright-Übernahme:** Aktiviere die automatische Übernahme von Autoren- und Lizenzinformationen

### Rechtliche Sicherheit

Alle Dateien auf Wikimedia Commons stehen unter **freien Lizenzen**:
- **Creative Commons** (CC BY, CC BY-SA, CC0)
- **Public Domain** (gemeinfrei)
- **GNU Free Documentation License**

Das AddOn übernimmt automatisch die korrekte **Quellenangabe** und **Lizenzinformation**, um rechtliche Anforderungen zu erfüllen.

### Beispiele für verfügbare Inhalte

- **Fotos:** Natur, Städte, Architektur, Personen, Tiere
- **Historische Bilder:** Gemälde, historische Fotos, Karten
- **SVG-Grafiken:** Logos, Icons, Diagramme, Flaggen
- **Dokumente:** Bücher, Karten, wissenschaftliche Arbeiten

### User-Agent Konfiguration

Wikimedia empfiehlt die Angabe eines **User-Agent** für bessere API-Performance:

**Format:** `[Website/Projekt] [Tool]/[Version] ([Kontakt-Email])`

**Beispiele:**
```
MeineWebsite.de AssetImport/1.0 (kontakt@meinewebsite.de)
Firma-XY REDAXO-Import/1.0 (admin@firma-xy.de)
MyProject.com MediaBot/1.0 (support@myproject.com)
```


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

## API Referenz

### AbstractProvider

Die Basisklasse, von der alle Provider erben müssen. Stellt grundlegende Funktionalitäten und Schnittstellen bereit.

#### Hauptmethoden

```php
public function getName(): string
```
Gibt einen eindeutigen Bezeichner für den Provider zurück.

```php
public function getTitle(): string
```
Gibt den Anzeigenamen zurück, der in der Benutzeroberfläche angezeigt wird.

```php
public function getIcon(): string
```
Gibt einen FontAwesome-Icon-Bezeichner zurück (z.B. 'fa-cloud').

```php
public function isConfigured(): bool
```
Prüft, ob der Provider alle erforderlichen Konfigurationseinstellungen hat.

```php
public function getConfigFields(): array
```
Gibt Konfigurationsfelder für die Provider-Einstellungsseite zurück. Jedes Feld sollte ein Array mit folgenden Elementen sein:
- `name`: Feldbezeichner
- `type`: Eingabetyp ('text', 'password', 'select')
- `label`: Übersetzungsschlüssel für das Label
- `notice`: Optionaler Übersetzungsschlüssel für Hilfetext
- `options`: Array von Optionen für Select-Felder

```php
public function search(string $query, int $page = 1, array $options = []): array
```
Führt die Suche durch und gibt Ergebnisse in folgendem Format zurück:
```php
[
    'items' => [
        [
            'id' => string,            // Eindeutige ID
            'preview_url' => string,   // Vorschaubild-URL
            'title' => string,         // Asset-Titel
            'author' => string,        // Ersteller/Autor
            'type' => string,          // 'image' oder 'video'
            'size' => [               // Verfügbare Größen
                'tiny' => ['url' => string],
                'small' => ['url' => string],
                'medium' => ['url' => string],
                'large' => ['url' => string],
                'original' => ['url' => string]
            ]
        ]
    ],
    'total' => int,          // Gesamtanzahl der Ergebnisse
    'page' => int,           // Aktuelle Seitennummer
    'total_pages' => int     // Gesamtanzahl der Seiten
]
```

```php
public function import(string $url, string $filename): bool
```
Importiert ein Asset in den REDAXO-Medienpool. Gibt bei Erfolg true zurück.

#### Geschützte Methoden

```php
protected function searchApi(string $query, int $page = 1, array $options = []): array
```
Implementierung der eigentlichen API-Suche. Muss von Provider-Klassen implementiert werden.

```php
protected function getCacheLifetime(): int
```
Gibt die Cache-Lebensdauer in Sekunden zurück. Standard: 86400 (24 Stunden)

```php
protected function getDefaultOptions(): array
```
Gibt Standard-Suchoptionen zurück. Standard: `['type' => 'all']`

### AssetImporter

Statische Klasse zur Verwaltung von Providern.

```php
public static function registerProvider(string $providerClass): void
```
Registriert eine neue Provider-Klasse.

```php
public static function getProviders(): array
```
Gibt alle registrierten Provider-Klassen zurück.

```php
public static function getProvider(string $name): ?AbstractProvider
```
Gibt Provider-Instanz anhand des Namens zurück oder null, wenn nicht gefunden.

### Cache

Das AddOn verwendet das eingebaute Caching-System von REDAXO, um API-Antworten zu speichern. Cache-Einträge werden in der Tabelle `rex_asset_import_cache` gespeichert mit:

- `provider`: Provider-Bezeichner
- `cache_key`: MD5-Hash der Abfrageparameter
- `response`: JSON-kodierte API-Antwort
- `created`: Erstellungszeitpunkt
- `valid_until`: Ablaufzeitpunkt

Die Standard-Cache-Lebensdauer beträgt 24 Stunden und kann pro Provider durch Überschreiben von `getCacheLifetime()` angepasst werden.

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
