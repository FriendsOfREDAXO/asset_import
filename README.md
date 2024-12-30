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

## Lizenz

MIT Lizenz, siehe [LICENSE](LICENSE)

## Autoren

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO


**Project Lead**

[Thomas Skerbis](https://github.com/skerbis)  
