<?php
namespace FriendsOfRedaxo\AssetImport;

use rex;
use rex_backend_login;
use rex_be_controller;
use rex_csrf_token; 
use rex_exception;
use rex_request;
use rex_response;
use rex_view;
use rex_addon;

use FriendsOfRedaxo\AssetImport\Provider\PixabayProvider;
use FriendsOfRedaxo\AssetImport\Provider\PexelsProvider;

if (rex_backend_login::hasSession()) {
   rex_csrf_token::factory('asset_import');
}

AssetImporter::registerProvider(PixabayProvider::class);
AssetImporter::registerProvider(PexelsProvider::class);

if (rex::isBackend() && rex::getUser()) {
   if (rex_be_controller::getCurrentPage() == 'asset_import/main') {
       rex_view::addCssFile(rex_addon::get('asset_import')->getAssetsUrl('asset_import.css'));
       rex_view::addJsFile(rex_addon::get('asset_import')->getAssetsUrl('asset_import.js'));
   }

   if (rex_request('asset_import_api', 'bool', false)) {
       try {
           $action = rex_request('action', 'string');
           $provider = rex_request('provider', 'string');
           
           $providerInstance = AssetImporter::getProvider($provider);
           if (!$providerInstance) {
               throw new rex_exception('Invalid provider');
           }

           if (!$providerInstance->isConfigured()) {
               throw new rex_exception('Provider not configured');
           }

           switch ($action) {
               case 'search':
                   $query = rex_request('query', 'string', '');
                   $page = rex_request('page', 'integer', 1);
                   $options = rex_request('options', 'array', []);
                   $results = $providerInstance->search($query, $page, $options);
                   rex_response::sendJson(['success' => true, 'data' => $results]);
                   break;

               case 'import':
                   $url = rex_request('url', 'string');
                   $filename = rex_request('filename', 'string');
                   $result = $providerInstance->import($url, $filename);
                   rex_response::sendJson(['success' => $result]);
                   break;

               default:
                   throw new rex_exception('Invalid action');
           }
       } catch (\Exception $e) {
           rex_logger::logException($e);
           rex_response::sendJson([
               'success' => false, 
               'error' => $e->getMessage()
           ]);
       }
       exit;
   }
}
