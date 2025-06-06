<?php

// Erstelle Cache-Tabelle mit rex_sql_table für bessere Updatefähigkeit
$table = rex_sql_table::get(rex::getTable('asset_import_cache'));

// Definiere die Struktur der Tabelle
$table
    // Basis-Spalten
    ->ensureColumn(new rex_sql_column('id', 'int(10) unsigned', false, null, 'auto_increment'))
    ->ensureColumn(new rex_sql_column('provider', 'varchar(191)'))
    ->ensureColumn(new rex_sql_column('cache_key', 'varchar(32)'))
    ->ensureColumn(new rex_sql_column('response', 'longtext'))
    ->ensureColumn(new rex_sql_column('created', 'datetime'))
    ->ensureColumn(new rex_sql_column('valid_until', 'datetime'))

    // Primary Key
    ->setPrimaryKey('id')

    // Index für schnellere Suche
    ->ensureIndex(new rex_sql_index('provider_cache', ['provider', 'cache_key']));

// Erstelle oder aktualisiere die Tabelle
$table->ensure();

<?php

try {
    // Hole Medientabelle
    $mediaTable = rex_sql_table::get(rex::getTable('media'));
    
    // Prüfe und füge med_copyright Spalte hinzu, wenn sie nicht existiert
    if (!$mediaTable->hasColumn('med_copyright')) {
        $mediaTable->addColumn(new rex_sql_column('med_copyright', 'text', true));
        // Führe die Änderungen aus
        $mediaTable->ensure();
    }
    
    // Prüfe ob die Metainfo-Tabelle existiert
    $sql = rex_sql::factory();
    $sql->setQuery('SHOW TABLES LIKE "' . rex::getTable('metainfo_field') . '"');
    
    if ($sql->getRows() > 0) {
        // Prüfe ob das Metainfo-Feld bereits existiert
        $sql->setQuery('SELECT * FROM ' . rex::getTable('metainfo_field') . ' WHERE name = :name', [':name' => 'med_copyright']);
        
        if ($sql->getRows() == 0) {
            // Erstelle Metainfo Feld für med_copyright
            $metaField = [
                'title' => 'Copyright',
                'name' => 'med_copyright',
                'priority' => 3,
                'attributes' => '',
                'type_id' => 1, // Text Input
                'params' => '',
                'validate' => '',
                'restrictions' => '',
                'createuser' => rex::getUser()->getLogin(),
                'createdate' => date('Y-m-d H:i:s'),
                'updateuser' => rex::getUser()->getLogin(),
                'updatedate' => date('Y-m-d H:i:s')
            ];

            $insert = rex_sql::factory();
            $insert->setTable(rex::getTable('metainfo_field'));
            $insert->setValues($metaField);
            $insert->insert();
        }
    }

} catch (rex_sql_exception $e) {
    rex_logger::factory()->log('error', 'med_copyright field creation error - ' . $e->getMessage());
    throw new rex_functional_exception($e->getMessage());
}
