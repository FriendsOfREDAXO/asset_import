<?php
namespace FriendsOfRedaxo\AssetImport;

// Start session für CSRF-Protection
if (rex_backend_login::hasSession()) {
    rex_csrf_token::factory('asset_import');
}


// Nur im Backend ausführen
if (\rex::isBackend() && \rex::getUser()) {

    // Provider registrieren
$provider = new \FriendsOfRedaxo\AssetImport\Provider\PixabayProvider();
\FriendsOfRedaxo\AssetImport\AssetImporter::registerProvider($provider->getName(), get_class($provider));

    
    // Assets einbinden
    if (\rex_be_controller::getCurrentPage() == 'asset_import/main') {
        \rex_view::addCssFile(\rex_addon::get('asset_import')->getAssetsUrl('asset_import.css'));
        \rex_view::addJsFile(\rex_addon::get('asset_import')->getAssetsUrl('asset_import.js'));
    }

    // AJAX Handler
    if (\rex_request('asset_import_api', 'bool', false)) {
        try {
            $action = \rex_request('action', 'string');
            $provider = \rex_request('provider', 'string');
            
            $providerInstance = AssetImporter::getProvider($provider);
            if (!$providerInstance) {
                throw new \rex_exception('Invalid provider');
            }

            switch ($action) {
                case 'search':
                    $query = \rex_request('query', 'string', '');
                    $page = \rex_request('page', 'integer', 1);
                    $options = \rex_request('options', 'array', []);
                    $results = $providerInstance->search($query, $page, $options);
                    \rex_response::sendJson(['success' => true, 'data' => $results]);
                    break;

                case 'import':
                    $url = \rex_request('url', 'string');
                    $filename = \rex_request('filename', 'string');
                    $result = $providerInstance->import($url, $filename);
                    \rex_response::sendJson(['success' => $result]);
                    break;

                default:
                    throw new \rex_exception('Invalid action');
            }

        } catch (\Exception $e) {
            \rex_logger::logException($e);
            \rex_response::sendJson([
                'success' => false, 
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
}
