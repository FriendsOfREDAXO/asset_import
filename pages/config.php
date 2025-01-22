<?php

namespace FriendsOfRedaxo\AssetImport;

use rex_addon;
use rex_fragment;
use rex_i18n;
use rex_select;
use rex_url;
use rex_view;

$addon = rex_addon::get('asset_import');
$providers = AssetImporter::getProviders();

if (empty($providers)) {
    echo rex_view::info(rex_i18n::msg('asset_import_provider_missing'));
    return;
}

// Formular verarbeiten
if (rex_post('config-submit', 'boolean')) {
    $error = false;

    // Provider-Einstellungen speichern
    foreach ($providers as $id => $class) {
        $provider = new $class();
        if (isset($_POST['config'][$id])) {
            $config = $_POST['config'][$id];
            $addon->setConfig($id, $config);
        }
    }

    if (!$error) {
        echo rex_view::success(rex_i18n::msg('asset_import_config_saved'));
    }
}

$content = '';

// Für jeden Provider Einstellungen anzeigen
foreach ($providers as $id => $class) {
    $provider = new $class();
    $fields = $provider->getConfigFields();

    if (empty($fields)) {
        continue;
    }

    $content .= '<fieldset><legend>' . $provider->getTitle() . '</legend>';
    $fragment = new rex_fragment();

    $formElements = [];

    foreach ($fields as $field) {
        $n = [];
        $value = $addon->getConfig($id)[$field['name']] ?? '';

        $n['label'] = '<label for="' . $id . '-' . $field['name'] . '">'
            . rex_i18n::msg($field['label'])
            . '</label>';

        switch ($field['type']) {
            case 'text':
            case 'password':
                $n['field'] = '<input type="' . $field['type'] . '"
                    id="' . $id . '-' . $field['name'] . '"
                    name="config[' . $id . '][' . $field['name'] . ']"
                    value="' . rex_escape($value) . '"
                    class="form-control"/>';
                break;

            case 'select':
                $select = new rex_select();
                $select->setId($id . '-' . $field['name']);
                $select->setName('config[' . $id . '][' . $field['name'] . ']');
                $select->setSelected($value);
                $select->setAttribute('class', 'form-control');
                foreach ($field['options'] as $option) {
                    $select->addOption($option['label'], $option['value']);
                }
                $n['field'] = $select->get();
                break;
        }

        if (isset($field['notice'])) {
            $n['note'] = '<p class="help-block">' . rex_i18n::msg($field['notice']) . '</p>';
        }

        $formElements[] = $n;
    }

    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/form.php');
    $content .= '</fieldset>';
}

if (!empty($content)) {
    $content = '
    <form action="' . rex_url::currentBackendPage() . '" method="post">
        ' . $content;

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="config-submit" value="1">'
        . rex_i18n::msg('asset_import_save')
        . '</button>';
    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $content .= $fragment->parse('core/form/submit.php') . '</form>';

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', rex_i18n::msg('asset_import_settings'));
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}
