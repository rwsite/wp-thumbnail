<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$KT = new \Kama_Thumbnail_Plugin();

$KT->_clear_cache();
$KT->_delete_meta();

delete_option(Kama_Thumbnail_Plugin::$opt_name);
delete_option('kama_thumb_version');

@ rmdir($KT->opt->cache_folder);