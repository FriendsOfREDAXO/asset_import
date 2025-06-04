<?php

// Entferne Cache-Tabelle
$table = rex_sql_table::get(rex::getTable('asset_import_cache'));
$table->drop();

