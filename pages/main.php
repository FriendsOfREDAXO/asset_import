<?php
namespace FriendsOfRedaxo\AssetImport;

$providers = AssetImporter::getProviders();

if (empty($providers)) {
    echo \rex_view::error(\rex_i18n::msg('asset_import_provider_missing'));
    return;
}

// Medienpool Kategorien laden
$cats_sel = new \rex_media_category_select();
$cats_sel->setStyle('class="form-control"');
$cats_sel->setName('category_id');
$cats_sel->setId('rex-mediapool-category');
$cats_sel->setSize(1);
$cats_sel->setAttribute('class', 'form-control selectpicker');
$cats_sel->setRootId(0); // Setze die Root-Kategorie als Root-Element

$content = '
<div class="asset-import-container">
    <div class="row">
        <!-- Kategorie-Auswahl -->
        <div class="col-sm-4">
            <div class="panel panel-default">
                <header class="panel-heading">
                    <div class="panel-title">' . \rex_i18n::msg('asset_import_target_category') . '</div>
                </header>
                <div class="panel-body">
                    ' . $cats_sel->get() . '
                </div>
            </div>
        </div>
        
        <!-- Suchbereich -->
        <div class="col-sm-8">
            <div class="panel panel-default">
                <header class="panel-heading">
                    <div class="panel-title">
                        <i class="rex-icon fa-search"></i> ' . \rex_i18n::msg('asset_import_search') . '
                    </div>
                </header>
                <div class="panel-body">
                    <div class="asset-import-search">
                        <form id="asset-import-search-form">
                            <div class="row">
                                <div class="col-sm-3">
                                    <select name="provider" class="form-control selectpicker" id="asset-import-provider">';
                                    
foreach ($providers as $id => $class) {
    $provider = new $class();
    $content .= '<option value="' . $id . '">' . $provider->getTitle() . '</option>';
}

$content .= '
                                    </select>
                                </div>
                                <div class="col-sm-7">
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               id="asset-import-query" 
                                               name="query"
                                               placeholder="' . \rex_i18n::msg('asset_import_search_placeholder') . '"
                                               required>
                                        <span class="input-group-btn">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="rex-icon fa-search"></i>
                                                ' . \rex_i18n::msg('asset_import_search') . '
                                            </button>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <select name="type" class="form-control selectpicker" id="asset-import-type">
                                        <option value="all">' . \rex_i18n::msg('asset_import_type_all') . '</option>
                                        <option value="image">' . \rex_i18n::msg('asset_import_type_image') . '</option>
                                        <option value="video">' . \rex_i18n::msg('asset_import_type_video') . '</option>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status und Fehler -->
    <div id="asset-import-status" class="alert" style="display: none;"></div>
    
    <!-- Ergebnisse -->
    <div class="panel panel-default">
        <div class="panel-body">
            <div id="asset-import-results" class="asset-import-results"></div>
            <div id="asset-import-load-more" class="asset-import-load-more" style="display: none;">
                <button class="btn btn-default">
                    <i class="rex-icon fa-chevron-down"></i> 
                    ' . \rex_i18n::msg('asset_import_load_more') . '
                </button>
            </div>
        </div>
    </div>
</div>';

$fragment = new \rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', \rex_i18n::msg('asset_import_media'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
