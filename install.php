<?php
// Prüfen ob bereits eine Konfiguration existiert
if (!$this->hasConfig()) {
    // Standardkonfiguration setzen
    $this->setConfig('providers', [
        'pixabay' => [
            'apikey' => '',
            'copyright_fields' => 'user_pixabay'
        ],
        'pexels' => [
            'apikey' => '',
            'copyright_fields' => 'photographer_pexels'
        ]
    ]);
}

// Erstelle Cache-Tabelle mit rex_sql_table für bessere Updatefähigkeit
\rex_sql_table::get(\rex::getTable('asset_import_cache'))
    // Basis-Spalten
    ->ensureColumn(new \rex_sql_column('id', 'int(10) unsigned', false, null, 'auto_increment'))
    ->ensureColumn(new \rex_sql_column('provider', 'varchar(191)'))
    ->ensureColumn(new \rex_sql_column('cache_key', 'varchar(32)'))
    ->ensureColumn(new \rex_sql_column('response', 'longtext'))
    ->ensureColumn(new \rex_sql_column('created', 'datetime'))
    ->ensureColumn(new \rex_sql_column('valid_until', 'datetime'))
    
    // Primary Key
    ->setPrimaryKey('id')
    
    // Index für schnellere Suche
    ->ensureIndex(new \rex_sql_index('provider_cache', ['provider', 'cache_key']))
    
    // UTF8MB4 für volle Unicode-Unterstützung
    ->setCharset('utf8mb4')
    ->setCollation('utf8mb4_unicode_ci')
    
    // Tabelle erstellen/aktualisieren
    ->ensure();

// Setze Berechtigungen für das AddOn
if (\rex::getUser() && \rex::getUser()->isAdmin()) {
    $this->setProperty('perm', 'asset_import[]');
}

// Prüfe ob das med_copyright Feld existiert, aber versuche nicht es anzulegen
$sql = \rex_sql::factory();
$sql->setQuery('SHOW COLUMNS FROM ' . \rex::getTable('media') . ' LIKE "med_copyright"');

// Wenn es nicht existiert, gib einen Hinweis aus
if ($sql->getRows() === 0) {
    \rex_logger::logError(
        E_WARNING,
        'The field "med_copyright" does not exist in the media table. Copyright information will not be saved.',
        __FILE__,
        __LINE__
    );
}
