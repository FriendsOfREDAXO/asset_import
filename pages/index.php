<?php

namespace FriendsOfRedaxo\AssetImport;

use rex_addon;
use rex_be_controller;
use rex_view;

$addon = rex_addon::get('asset_import');
echo rex_view::title($addon->i18n('title'));
rex_be_controller::includeCurrentPageSubPath();
