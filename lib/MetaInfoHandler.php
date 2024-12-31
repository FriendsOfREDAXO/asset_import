<?php
namespace FriendsOfRedaxo\AssetImport;

class MetaInfoHandler
{
    public static function ensureCopyrightField(): void
    {
        if (!self::hasCopyrightField()) {
            self::createCopyrightField();
        }
    }

    public static function hasCopyrightField(): bool
    {
        $sql = \rex_sql::factory();
        $sql->setQuery('SELECT * FROM ' . \rex::getTable('metainfo_field') . ' WHERE name = :name', ['name' => 'med_copyright']);
        return $sql->getRows() > 0;
    }

    private static function createCopyrightField(): void
    {
        $sql = \rex_sql::factory();
        try {
            $sql->setTable(\rex::getTable('metainfo_field'));
            $sql->setValue('name', 'med_copyright');
            $sql->setValue('label', 'translate:media_copyright');
            $sql->setValue('type', '1'); // Text input
            $sql->setValue('params', '');
            $sql->setValue('priority', '1');
            $sql->setValue('attributes', '');
            $sql->setValue('callback', '');
            $sql->setValue('restrictions', '');
            $sql->setValue('templates', '');
            $sql->insert();
            \rex_delete_cache();
        } catch (\rex_sql_exception $e) {
            \rex_logger::logException($e);
        }
    }
}
