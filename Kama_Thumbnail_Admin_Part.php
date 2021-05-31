<?php


trait Kama_Thumbnail_Admin_Part {

    static function def_options(){

        // позволяет изменить дефолтные опции.
        // если хук используется, то страница опций плагина автоматически отключается.
        return apply_filters( 'kama_thumb_def_options', [
            'meta_key'          => 'photo_URL', // называние мета поля записи.
            'cache_folder'      => '',          // полный путь до папки миниатюр.
            'cache_folder_url'  => '',          // URL до папки миниатюр.
            'no_photo_url'      => '',          // УРЛ на заглушку.
            'use_in_content'    => 'mini',      // искать ли класс mini у картинок в тексте, чтобы изменить их размер.
            'no_stub'           => false,       // не выводить картинку-заглушку.
            'auto_clear'        => false,       // очищать ли кэш каждые Х дней.
            'auto_clear_days'   => 7,           // каждые сколько дней очищать кэш.
            'rise_small'        => true,        // увеличить создаваемую миниатюру (ширину/высоту), если её размер меньше указанного размера.
            'quality'           => 90,          // качество создаваемых миниатюр.
            'allow_hosts'       => '',          // доступные хосты, кроме родного, через запятую. 'any' - любые хосты.
            'stop_creation_sec' => 20,          // макс кол-во секунд, с момента работы PHP, в которые миниатюры будут создаваться.
            'webp'              => false,       // использвоать ли webp формат для создания миниатюр?
            'debug'             => 0,           // режим дебаг (для разработчиков).
            'disable_http'      => false
        ] );
    }

    ## для вывода сообещний в админке
    static function show_message( $text = '', $class = 'updated' ){

        add_action( 'admin_notices', function() use ( $text, $class ){
            echo '<div id="message" class="'. $class .' notice is-dismissible"><p>'. $text .'</p></div>';
        } );
    }

    function admin_options(){
        // для мультисайта создается отдельная страница в настройках сети
        if( is_multisite() ){
            $hook = add_submenu_page( 'settings.php', 'Kama Thumbnail', 'Kama Thumbnail', 'manage_network_options', self::$opt_pagename, array( $this, '_network_options_page') );
        }

        // Добавляем блок опций на базовую страницу "Чтение"
        add_settings_section( 'kama_thumbnail', __('Kama Thumbnail Settings','thumbnail'), '', self::$opt_pagename );

        // Добавляем поля опций. Указываем название, описание,
        // функцию выводящую html код поля опции.
        add_settings_field( 'kt_options_field',
            '
			<p><a class="button" target="_blank" href="'. add_query_arg('kt_clear', 'clear_cache_stub') .'">'. __('Clear nophoto Cache','thumbnail') .'</a></p>
			
			<p><a class="button" target="_blank" href="'. add_query_arg('kt_clear', 'clear_cache') .'">'. __('Clear all images','thumbnail') .'</a></p>
			
			<p><a class="button" target="_blank" href="'. add_query_arg('kt_clear', 'delete_meta') .'" onclick="return confirm(\''. __('Are You Sure?','thumbnail') .'\')">'. __('Remove Releted Custom Fields','thumbnail') .'</a></p>
			
			<p><a class="button" target="_blank" href="'. add_query_arg('kt_clear', 'clear_all_data') .'" onclick="return confirm(\''. __('Are You Sure?','thumbnail') .'\')">'. __('Remove all data','thumbnail') .'</a></p>
			',
            array( $this, '_options_field' ),
            self::$opt_pagename, // страница
            'kama_thumbnail' // секция
        );

        // Регистрируем опции, чтобы они сохранялись при отправке
        // $_POST параметров и чтобы callback функции опций выводили их значение.
        register_setting( self::$opt_pagename, self::$opt_name, [ $this, 'sanitize_options' ] );
    }

    function _network_options_page(){
        echo '<form method="POST" action="edit.php?action=kt_opt_up" style="max-width:900px;">';
        wp_nonce_field( self::$opt_pagename ); // settings_fields() не подходит для мультисайта...
        do_settings_sections( self::$opt_pagename );
        submit_button();
        echo '</form>';
    }

    function _options_field(){

        $opt_name = self::$opt_name;

        $opts = is_multisite() ? get_site_option( $opt_name ) : get_option( $opt_name );
        $opt  = (object) array_merge( self::def_options(), (array) $opts );

        $def_opt = (object) self::def_options();

        $elems = [

            'cache_folder' =>
                '<input type="text" name="'. $opt_name .'[cache_folder]" value="'. $opt->cache_folder .'" style="width:80%;" placeholder="'. self::$opt->cache_folder .'">'.
                '<p class="description">'. __('Full path to the cache folder with 755 rights or above.','thumbnail') .'</p>',

            'cache_folder_url' =>
                '<input type="text" name="'. $opt_name .'[cache_folder_url]" value="'. $opt->cache_folder_url .'" style="width:80%;" placeholder="'. self::$opt->cache_folder_url .'">
				<p class="description">'. __('URL of cache folder.','thumbnail') .'</p>',

            'no_photo_url' =>
                '<input type="text" name="'. $opt_name .'[no_photo_url]" value="'. $opt->no_photo_url .'" style="width:80%;" placeholder="'. self::$opt->no_photo_url .'">
				<p class="description">'. __('URL of stub image.','thumbnail') .'</p>',

            'meta_key' =>
                '<input type="text" name="'. $opt_name .'[meta_key]" value="'. $opt->meta_key .'" class="regular-text">
				<p class="description">'. __('Custom field key, where the thumb URL will be. Default:','thumbnail') .' <code>'. $def_opt->meta_key .'</code></p>',

            'allow_hosts' =>
                '<textarea name="'. $opt_name .'[allow_hosts]" style="width:350px;height:45px;">'. esc_textarea($opt->allow_hosts) .'</textarea>
				<p class="description">'. __('Hosts from which thumbs can be created. One per line: <i>sub.mysite.com</i>. Specify <code>any</code>, to use any hosts.','thumbnail') .'</p>',

            'quality' =>
                '<input type="text" name="'. $opt_name .'[quality]" value="'. $opt->quality .'" style="width:50px;">
				<p class="description" style="display:inline-block;">'. __('Quality of creating thumbs from 0 to 100. Default:','thumbnail') .' <code>'. $def_opt->quality .'</code></p>',

            'webp' => '
				<label>
					<input type="hidden" name="'. $opt_name .'[webp]" value="0">
					<input type="checkbox" name="'. $opt_name .'[webp]" value="1" '. checked(1, @ $opt->webp, 0) .'>
					'. __('Use webp image.','thumbnail') .'
				</label>',

            'no_stub' => '
				<label>
					<input type="hidden" name="'. $opt_name .'[no_stub]" value="0">
					<input type="checkbox" name="'. $opt_name .'[no_stub]" value="1" '. checked(1, @ $opt->no_stub, 0) .'>
					'. __('Don\'t show nophoto image.','thumbnail') .'
				</label>',

            'auto_clear' => '
				<label>
					<input type="hidden" name="'. $opt_name .'[auto_clear]" value="0">
					<input type="checkbox" name="'. $opt_name .'[auto_clear]" value="1" '. checked(1, @ $opt->auto_clear, 0) .'>
					'. sprintf(
                    __('Clear all cache automaticaly every %s days.','thumbnail'),
                    '<input type="number" name="'. $opt_name .'[auto_clear_days]" value="'. @ $opt->auto_clear_days .'" style="width:50px;">'
                ) .'
				</label>',

            'disable_http' => '
				<label>
					<input type="hidden" name="'. $opt_name .'[disable_http]" value="0">
					<input type="checkbox" name="'. $opt_name .'[disable_http]" value="1" '. checked(1, @ $opt->disable_http, 0) .'>
					'. __('Disable HTTP requests.','thumbnail') .'
				</label>',

            'rise_small' => '
				<label>
					<input type="hidden" name="'. $opt_name .'[rise_small]" value="0">
					<input type="checkbox" name="'. $opt_name .'[rise_small]" value="1" '. checked(1, @ $opt->rise_small, 0) .'>
					'. __('Increase the thumbnail you create (width/height) if it is smaller than the specified size.','thumbnail') .'
				</label>',

            'use_in_content' => '
				<input type="text" name="'. $opt_name .'[use_in_content]" value="'.( isset($opt->use_in_content) ? esc_attr($opt->use_in_content) : 'mini' ).'">
				<p class="description">'. sprintf( __('Find specified here class of IMG tag in content and make thumb from found image by it sizes. Leave this field empty to disable this function. Default: %s','thumbnail'), '<code>mini</code>' ) .'</p>',

            'stop_creation_sec' => '
				<input type="number" step="0.5" name="'. $opt_name .'[stop_creation_sec]" value="'.( isset($opt->stop_creation_sec) ? esc_attr($opt->stop_creation_sec) : 20 ).'" style="width:4rem;"> '. __('seconds','thumbnail') .'
				<p class="description">'. sprintf( __('The maximum number of seconds since PHP started, after which thumbnails creation will be stopped. Must be less then %s (current PHP `max_execution_time`).','thumbnail'), ini_get('max_execution_time') ) .'</p>',

        ];

        $elems = apply_filters( 'kama_thumb_options_field_elems', $elems, $opt_name, $opt, $def_opt );

        $elems['debug'] = '
			<label>
				<input type="hidden" name="'. $opt_name .'[debug]" value="0">
				<input type="checkbox" name="'. $opt_name .'[debug]" value="1" '. checked(1, @ $opt->debug, 0) .'>
				'. __('Debug mode. Recreates thumbs all time (disables the cache).','thumbnail') .'
			</label>';

        echo '
		<style>
			.ktumb-line{ padding:.5em 0; }
		</style>
		<div class="ktumb-line">'. implode( '</div><div class="ktumb-line">', $elems ) .'</div>';

    }

    ## update options from network settings.php
    function network_options_update(){
        // nonce check
        check_admin_referer( self::$opt_pagename );

        $new_opts = wp_unslash( $_POST['kama_thumbnail'] );
        //$new_opts = self::sanitize_options( $new_opts ); // сработает автоматом из register_setting() ...

        update_site_option( self::$opt_name, $new_opts );

        wp_redirect( add_query_arg( 'updated', 'true', network_admin_url( 'settings.php?page='. self::$opt_pagename  ) ) );
        exit();
    }

    ## sanitize options
    function sanitize_options( $opts ){
        $defopt = self::def_options();

        foreach( $opts as $key => & $val ){
            if( $key === 'allow_hosts' ){
                $ah = preg_split( '/[\s,]+/s', trim( $val ) ); // сделаем массив

                foreach( $ah as & $host ){
                    $host = sanitize_text_field( $host );
                    $host = str_replace( 'www.', '', $host );
                }

                $val = implode( "\n", $ah );
            }
            elseif( $key === 'meta_key' && ! $val ){

                $val = $defopt['meta_key'];
            }
            elseif( $key === 'stop_creation_sec' ){
                if(0 == ini_get('max_execution_time')){
                    $maxtime = intval($val);
                } else {
                    $maxtime = intval(ini_get('max_execution_time') * 0.95); // -5%
                }
                $val     = floatval( $val );
                $val     = ($val > $maxtime || ! $val ) ? $maxtime : $val;
            }
            else
                $val = sanitize_text_field( $val );
        }

        return $opts;
    }

    public function setting_page_link( $actions, $plugin_file ){
        if( false === strpos( $plugin_file, basename(KT_PATH) ) ) return $actions;
        $settings_link = '<a href="'. admin_url('options-media.php') .'">' . __('Settings','thumbnail') . '</a>';
        array_unshift( $actions, $settings_link );
        return $actions;
    }
}