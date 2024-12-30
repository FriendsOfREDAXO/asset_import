<?php
if (!$this->hasConfig()) {
    $this->setConfig('providers', []);
}

\rex_sql_table::get(\rex::getTable('asset_import_cache'))
    ->ensureColumn(new \rex_sql_column('id', 'int(10) unsigned', false, null, 'auto_increment'))
    ->ensureColumn(new \rex_sql_column('provider', 'varchar(191)'))
    ->ensureColumn(new \rex_sql_column('cache_key', 'varchar(32)'))
    ->ensureColumn(new \rex_sql_column('response', 'longtext'))
    ->ensureColumn(new \rex_sql_column('created', 'datetime'))
    ->ensureColumn(new \rex_sql_column('valid_until', 'datetime'))
    ->setPrimaryKey('id')
    ->ensureIndex(new \rex_sql_index('provider_cache', ['provider', 'cache_key']))
    ->ensure();
