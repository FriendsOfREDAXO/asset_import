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

        if (0 == $sql->getRows()) {
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
                'updatedate' => date('Y-m-d H:i:s'),
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
