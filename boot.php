<?php

namespace FriendsOfRedaxo\AssetImport;

use Exception;
use FriendsOfRedaxo\AssetImport\Provider\PexelsProvider;
use FriendsOfRedaxo\AssetImport\Provider\PixabayProvider;
use FriendsOfRedaxo\AssetImport\Provider\WikimediaProvider;
use Psr\Log\LogLevel;
use rex;
use rex_addon;
use rex_backend_login;
use rex_be_controller;
use rex_csrf_token;
use rex_exception;
// Register base providers
use rex_i18n;
use rex_logger;
use rex_media;
use rex_response;
use rex_view;

use function count;

// Initialize CSRF protection
if (rex_backend_login::hasSession()) {
    rex_csrf_token::factory('asset_import');
}

// Only execute in backend and for logged-in users
if (rex::isBackend() && rex::getUser()) {
    // Register default providers
    AssetImporter::registerProvider(PixabayProvider::class);
    AssetImporter::registerProvider(PexelsProvider::class);
    AssetImporter::registerProvider(WikimediaProvider::class);

    // Register assets for the asset_import pages
    if ('asset_import/main' === rex_be_controller::getCurrentPage()
        || 'asset_import/config' === rex_be_controller::getCurrentPage()) {
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
            'no_results' => rex_i18n::msg('asset_import_no_results'),
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

                    // Log import request
                    rex_logger::factory()->log(LogLevel::INFO,
                        'Starting import request',
                        [
                            'provider' => $provider,
                            'url' => $url,
                            'filename' => $filename,
                            'copyright' => $copyright,
                        ],
                    );

                    // Validate input
                    if (empty($url) || empty($filename)) {
                        throw new rex_exception('Invalid import parameters');
                    }

                    // Import file
                    $result = $providerInstance->import($url, $filename, $copyright);

                    // Log import result
                    rex_logger::factory()->log(LogLevel::INFO,
                        'Import request completed',
                        [
                            'provider' => $provider,
                            'success' => $result,
                            'filename' => $filename,
                        ],
                    );

                    // Verify media and copyright after import
                    if ($result) {
                        $media = rex_media::get($filename);
                    }

                    // Send response
                    rex_response::sendJson(['success' => $result]);
                    break;

                default:
                    throw new rex_exception('Invalid action');
            }
        } catch (Exception $e) {
            // Log error
            rex_logger::factory()->log(LogLevel::ERROR,
                'API request failed: ' . $e->getMessage(),
                [
                    'provider' => $provider ?? 'unknown',
                    'action' => $action ?? 'unknown',
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            );

            // Send error response
            rex_response::sendJson([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }
}
