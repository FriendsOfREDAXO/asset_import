<?php
namespace FriendsOfRedaxo\AssetImport;

use rex;
use rex_addon;
use rex_backend_login;
use rex_be_controller;
use rex_csrf_token;
use rex_exception;
use rex_request;
use rex_response;
use rex_view;
use rex_media;
use rex_sql;
use rex_i18n;

// Register base providers
use FriendsOfRedaxo\AssetImport\Provider\PixabayProvider;
use FriendsOfRedaxo\AssetImport\Provider\PexelsProvider;

// Initialize CSRF protection
if (rex_backend_login::hasSession()) {
    rex_csrf_token::factory('asset_import');
}

// Register default providers
AssetImporter::registerProvider(PixabayProvider::class);
AssetImporter::registerProvider(PexelsProvider::class);

// Only execute in backend and for logged-in users
if (rex::isBackend() && rex::getUser()) {
    
    // Register assets for the asset_import pages
    if (rex_be_controller::getCurrentPage() === 'asset_import/main' || 
        rex_be_controller::getCurrentPage() === 'asset_import/config') {
        
        // Add CSS and JS files
        rex_view::addCssFile(rex_addon::get('asset_import')->getAssetsUrl('asset_import.css'));
        rex_view::addJsFile(rex_addon::get('asset_import')->getAssetsUrl('asset_import.js'));
        
        // Add Javascript translations
        $translations = [
            'error_unknown' => rex_i18n::msg('asset_import_error_unknown'),
            'error_loading' => rex_i18n::msg('asset_import_error_loading'),
            'error_import' => rex_i18n::msg('asset_import_error_import'),
            'importing' => rex_i18n::msg('asset_import_importing'),
            'import' => rex_i18n::msg('asset_import_import'),
            'success' => rex_i18n::msg('asset_import_import_success'),
            'loading' => rex_i18n::msg('asset_import_loading'),
            'results_found' => rex_i18n::msg('asset_import_results'),
            'no_results' => rex_i18n::msg('asset_import_no_results')
        ];
        
        // Add translations to Javascript
        rex_view::setJsProperty('asset_import', $translations);
    }
    
    // Handle AJAX requests
    if (rex_request('asset_import_api', 'bool', false)) {
        try {
            $action = rex_request('action', 'string');
            $provider = rex_request('provider', 'string');
            
            // Get provider instance
            $providerInstance = AssetImporter::getProvider($provider);
            if (!$providerInstance) {
                throw new rex_exception('Invalid provider');
            }
            
            // Check if provider is configured
            if (!$providerInstance->isConfigured()) {
                throw new rex_exception(rex_i18n::msg('asset_import_provider_error'));
            }
            
            switch ($action) {
                case 'search':
                    // Handle search request
                    $query = rex_request('query', 'string', '');
                    $page = rex_request('page', 'integer', 1);
                    $options = rex_request('options', 'array', []);
                    
                    $results = $providerInstance->search($query, $page, $options);
                    rex_response::sendJson(['success' => true, 'data' => $results]);
                    break;
                    
                case 'import':
                    // Handle import request
                    $url = rex_request('url', 'string');
                    $filename = rex_request('filename', 'string');
                    $copyright = rex_request('copyright', 'string', '');
                    
                    // Validate input
                    if (empty($url) || empty($filename)) {
                        throw new rex_exception('Invalid import parameters');
                    }
                    
                    // Import file
                    $result = $providerInstance->import($url, $filename, $copyright);
                    
                    // Send response
                    rex_response::sendJson(['success' => $result]);
                    break;
                    
                default:
                    throw new rex_exception('Invalid action');
            }
            
        } catch (\Exception $e) {
            // Log error
            rex_logger::logException($e);
            
            // Send error response
            rex_response::sendJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Install event: Create necessary database tables
rex_addon::get('asset_import')->setProperty('install', function (rex_addon $addon) {
    // Create cache table if it doesn't exist
    $table = rex::getTable('asset_import_cache');
    $sql = rex_sql::factory();
    
    // Check if table exists
    if (!$sql->setQuery("SHOW TABLES LIKE '$table'")->getRows()) {
        $sql->setQuery("
            CREATE TABLE IF NOT EXISTS $table (
                id int(10) unsigned NOT NULL auto_increment,
                provider varchar(191) NOT NULL,
                cache_key varchar(32) NOT NULL,
                response longtext NOT NULL,
                created datetime NOT NULL,
                valid_until datetime NOT NULL,
                PRIMARY KEY (id),
                KEY provider_cache (provider, cache_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
    
    // Create default configuration if it doesn't exist
    if (!$addon->hasConfig()) {
        $addon->setConfig('providers', []);
    }
});

// Uninstall event: Clean up
rex_addon::get('asset_import')->setProperty('uninstall', function (rex_addon $addon) {
    // Remove cache table
    $table = rex::getTable('asset_import_cache');
    $sql = rex_sql::factory();
    $sql->setQuery("DROP TABLE IF EXISTS $table");
    
    // Remove configuration
    $addon->removeConfig();
});
