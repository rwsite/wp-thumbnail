<?php
/**
 * Code Admin panel Only
 */

/**
 * Plugin Upgrade
 * Нужно вызывать на странице настроек плагина, чтобы не грузить лишний раз сервер.
 */
function kama_thumb_upgrade(){
	$ver_key = 'kama_thumb_version';
	$cur_ver = get_file_data( KT_MAIN_FILE, ['Version' => 'Version'] )['Version'];
	$old_ver = get_option( $ver_key );
	if( $old_ver === $cur_ver ){
		return;
	}
	update_option( $ver_key, $cur_ver );
}