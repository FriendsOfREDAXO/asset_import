<?php

namespace FriendsOfRedaxo\AssetImport;

use Exception;
use rex;
use rex_fragment;
use rex_i18n;
use rex_media_category_select;
use rex_response;

// Handle AJAX requests for direct URL import
if (rex_request('direct_import_api', 'int', 0)) {
    // Berechtigung für Direct Import prüfen
    $user = rex::getUser();
    if (!$user || !$user->hasPerm('asset_import[direct]')) {
        rex_response::sendJson([
            'success' => false, 
            'error' => 'Keine Berechtigung für Direct URL Import'
        ]);
        exit;
    }
    
    try {
        $action = rex_request('action', 'string', '');

        switch ($action) {
            case 'preview':
                $url = rex_request('url', 'string', '');
                $result = DirectImporter::preview($url);
                rex_response::sendJson(['success' => true, 'data' => $result]);
                break;

            case 'import':
                $url = rex_request('url', 'string', '');
                $filename = rex_request('filename', 'string', '');
                $copyright = rex_request('copyright', 'string', '');
                $categoryId = rex_request('category_id', 'int', 0);

                $result = DirectImporter::import($url, $filename, $copyright, $categoryId);
                rex_response::sendJson(['success' => $result]);
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        rex_response::sendJson([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    }
    exit;
}

// Medienpool Kategorien laden
$cats_sel = new rex_media_category_select();
$cats_sel->setStyle('class="form-control selectpicker"');
$cats_sel->setName('category_id');
$cats_sel->setId('rex-mediapool-category-direct');
$cats_sel->setSize(1);
$cats_sel->setAttribute('class', 'form-control selectpicker');
$cats_sel->setAttribute('data-live-search', 'true');

$user = rex::requireUser();

if ($user->getComplexPerm('media')->hasAll()) {
    $cats_sel->addOption(rex_i18n::msg('pool_kats_no'), '0');
}

$content = '
<div class="direct-import-container">
    <div class="row">
        <!-- Info Panel -->
        <div class="col-sm-12">
            <div class="panel panel-info">
                <header class="panel-heading">
                    <div class="panel-title">
                        <i class="rex-icon fa-info-circle"></i> ' . rex_i18n::msg('asset_import_direct_info_title') . '
                    </div>
                </header>
                <div class="panel-body">
                    <p>' . rex_i18n::msg('asset_import_direct_info_text') . '</p>
                    <ul>
                        <li>' . rex_i18n::msg('asset_import_direct_info_formats') . '</li>
                        <li>' . rex_i18n::msg('asset_import_direct_info_copyright') . '</li>
                        <li>' . rex_i18n::msg('asset_import_direct_info_filename') . '</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Kategorie-Auswahl -->
        <div class="col-sm-4">
            <div class="panel panel-default">
                <header class="panel-heading">
                    <div class="panel-title">' . rex_i18n::msg('asset_import_target_category') . '</div>
                </header>
                <div class="panel-body">
                    ' . $cats_sel->get() . '
                </div>
            </div>
        </div>

        <!-- URL Import -->
        <div class="col-sm-8">
            <div class="panel panel-default">
                <header class="panel-heading">
                    <div class="panel-title">
                        <i class="rex-icon fa-link"></i> ' . rex_i18n::msg('asset_import_direct_url_import') . '
                    </div>
                </header>
                <div class="panel-body">
                    <form id="direct-import-form">
                        <div class="form-group">
                            <label for="direct-import-url">' . rex_i18n::msg('asset_import_direct_url') . '</label>
                            <div class="input-group">
                                <input type="url"
                                       class="form-control"
                                       id="direct-import-url"
                                       name="url"
                                       placeholder="https://example.com/image.jpg"
                                       required>
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" id="direct-import-preview">
                                        <i class="rex-icon fa-eye"></i>
                                        ' . rex_i18n::msg('asset_import_direct_preview') . '
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div id="direct-import-preview-container" style="display: none;">
                            <div class="form-group">
                                <label for="direct-import-filename">' . rex_i18n::msg('asset_import_direct_filename') . '</label>
                                <input type="text"
                                       class="form-control"
                                       id="direct-import-filename"
                                       name="filename"
                                       placeholder="' . rex_i18n::msg('asset_import_direct_filename_placeholder') . '">
                            </div>

                            <div class="form-group">
                                <label for="direct-import-copyright">' . rex_i18n::msg('asset_import_direct_copyright') . '</label>
                                <input type="text"
                                       class="form-control"
                                       id="direct-import-copyright"
                                       name="copyright"
                                       placeholder="' . rex_i18n::msg('asset_import_direct_copyright_placeholder') . '">
                            </div>

                            <div id="direct-import-preview-area"></div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" id="direct-import-submit">
                                    <i class="rex-icon fa-download"></i>
                                    ' . rex_i18n::msg('asset_import_direct_import_btn') . '
                                </button>
                                <button type="button" class="btn btn-default" id="direct-import-reset">
                                    <i class="rex-icon fa-refresh"></i>
                                    ' . rex_i18n::msg('asset_import_direct_reset') . '
                                </button>
                            </div>

                            <div class="progress" id="direct-import-progress" style="display: none;">
                                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%">
                                    <i class="rex-icon fa-download"></i> ' . rex_i18n::msg('asset_import_importing') . '
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Status und Fehler -->
    <div id="direct-import-status" class="alert" style="display: none;"></div>
</div>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('asset_import_direct_title'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
