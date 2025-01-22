<?php

namespace FriendsOfRedaxo\AssetImport\Provider;

/**
 * Interface für Asset Import Provider.
 */
interface ProviderInterface
{
    /**
     * Gibt den eindeutigen Provider-Namen zurück.
     */
    public function getName(): string;

    /**
     * Gibt den Anzeigenamen des Providers zurück.
     */
    public function getTitle(): string;

    /**
     * Gibt das FontAwesome-Icon des Providers zurück.
     */
    public function getIcon(): string;

    /**
     * Prüft, ob der Provider konfiguriert ist.
     */
    public function isConfigured(): bool;

    /**
     * Gibt die Konfigurationsfelder des Providers zurück.
     *
     * @return array Array von Konfigurationsfeldern mit folgender Struktur:
     * [
     *   'label' => string,        // Übersetzungsschlüssel für Label
     *   'name' => string,         // Feldname
     *   'type' => string,         // Feldtyp (text, password, select)
     *   'notice' => string|null,  // Optional: Übersetzungsschlüssel für Hinweistext
     *   'options' => array|null   // Optional: Optionen für Select-Felder
     * ]
     */
    public function getConfigFields(): array;

    /**
     * Sucht Assets anhand der übergebenen Parameter.
     *
     * @param string $query   Suchanfrage oder Asset-URL
     * @param int    $page    Aktuelle Seite
     * @param array  $options Zusätzliche Suchoptionen
     *
     * @return array Array mit folgender Struktur:
     * [
     *   'items' => [
     *     [
     *       'id' => string,           // Asset-ID
     *       'preview_url' => string,  // URL des Vorschaubilds
     *       'title' => string,        // Asset-Titel
     *       'author' => string,       // Asset-Autor
     *       'copyright' => string,    // Copyright-Information
     *       'type' => string,         // Asset-Typ (image/video)
     *       'size' => [              // Verfügbare Größen
     *         'tiny' => ['url' => string],
     *         'small' => ['url' => string],
     *         'medium' => ['url' => string],
     *         'large' => ['url' => string]
     *       ]
     *     ]
     *   ],
     *   'total' => int,              // Gesamtanzahl der Ergebnisse
     *   'page' => int,               // Aktuelle Seite
     *   'total_pages' => int         // Gesamtanzahl der Seiten
     * ]
     */
    public function search(string $query, int $page = 1, array $options = []): array;

    /**
     * Importiert ein Asset in den Medienpool.
     *
     * @param string      $url       Download-URL des Assets
     * @param string      $filename  Zieldateiname
     * @param string|null $copyright Optional: Copyright-Information
     *
     * @return bool True bei erfolgreichem Import, sonst false
     */
    public function import(string $url, string $filename, ?string $copyright = null): bool;

    /**
     * Gibt die Standard-Suchoptionen des Providers zurück.
     *
     * @return array Array von Standard-Optionen
     */
    public function getDefaultOptions(): array;
}
