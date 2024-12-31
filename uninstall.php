<?php
// Remove cache table
$table = \rex::getTable('asset_import_cache');
\rex_sql_table::get($table)->drop();
