<?php
// Erstelle Cache-Tabelle mit rex_sql_table für bessere Updatefähigkeit
$table = \rex_sql_table::get(\rex::getTable('asset_import_cache'));

// Definiere die Struktur der Tabelle
$table
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
    ->ensureIndex(new \rex_sql_index('provider_cache', ['provider', 'cache_key']));

// Erstelle oder aktualisiere die Tabelle
$table->ensure();
