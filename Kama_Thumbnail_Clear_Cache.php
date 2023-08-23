<?php


trait Kama_Thumbnail_Clear_Cache {

    public function cache_clear_handler(){

        if( isset($_GET['kt_clear']) && current_user_can('manage_options') ){
            $this->force_clear( $_GET['kt_clear'] );
            return;
        }

        $this->smart_clear( 'stub' );

        if( ! empty(self::$opt->auto_clear) ) {
            $this->smart_clear();
        }
    }

    ## очистка кэша с проверкой
    public function smart_clear( $type = '' ){

        $_stub       = ( $type === 'stub' );
        $cache_dir   = self::$opt->cache_folder;
        $expire_file = $cache_dir .'/'. ( $_stub ? 'expire_stub' : 'expire' );

        if( ! is_dir($cache_dir) ) {
            return;
        }

        $expire = $cleared = 0;
        if( file_exists($expire_file) )
            $expire = (int) file_get_contents( $expire_file );

        if( $expire < time() )
            $cleared = $this->_clear_cache( $_stub ? 'only_stub' : '' );

        if( $cleared || ! $expire )
            @ file_put_contents( $expire_file, time() + ($_stub ? DAY_IN_SECONDS : self::$opt->auto_clear_days * DAY_IN_SECONDS) );
    }

    ## ?kt_clear=clear_cache - очистит кеш картинок ?kt_clear=delete_meta - удалит произвольные поля
    public function force_clear( $type ){

        switch( $type ){
            case 'clear_cache_stub':
                $this->_clear_cache('only_stub');
                break;
            case 'clear_cache':
                $this->_clear_cache();
                break;
            case 'delete_meta':
                $this->_delete_meta();
                break;
            case 'clear_all_data':
                $this->_clear_cache();
                $this->_delete_meta();
                break;
        }
    }


    /**
     * Removes cached images files.
     *
     * @param bool $only_stub
     *
     * @return bool
     */
    public function _clear_cache( $only_stub = false ){

        $cache_dir = self::$opt->cache_folder;

        if( ! $cache_dir ){
            self::show_message( __('Path to cache not set.','thumbnail'), 'error' );
            return false;
        }

        if( is_dir($cache_dir) ){

            // delete stub only
            if( $only_stub ){
                foreach( glob("$cache_dir/stub_*") as $file ){
                    unlink($file);
                }

                if( WP_DEBUG && current_user_can('manage_options') ){
                    self::show_message(
                        __('All nophoto thumbs was deleted from <b>Thumbnail</b> cache.','thumbnail'), 'notice-info'
                    );
                }
            }
            // delete all
            else {
                self::_clear_folder( $cache_dir );
                self::show_message( __('<b>Thumbnail</b> cache has been cleared.','thumbnail') );
            }
        }

        return true;
    }

    /**
     * Удаляет все метаполя `photo_URL` у записей.
     */
    public function _delete_meta(){

        global $wpdb;

        if( ! self::$opt->meta_key ){
            self::show_message( 'meta_key option not set.', 'error' );
            return;
        }

        if( is_multisite() ){
            $deleted = [];
            $sites = get_sites([
                'fields' => 'ids',
                'number' => 500,
            ]);
            foreach( $sites as $blog_id ){
                $deleted[] = $wpdb->delete($wpdb->get_blog_prefix( $blog_id ) .'postmeta',
                    [ 'meta_key' => self::$opt->meta_key ]);
            }
            $deleted = !! array_filter( $deleted );
        } else {
            $deleted = $wpdb->delete($wpdb->postmeta, ['meta_key' => self::$opt->meta_key]);
        }

        if( false !== $deleted ) {
            self::show_message(sprintf(__('All custom fields <code>%s</code> was deleted.', 'thumbnail'), self::$opt->meta_key));
        } else {
            self::show_message(sprintf(__('Couldn\'t delete <code>%s</code> custom fields', 'thumbnail'), self::$opt->meta_key));
        }
        wp_cache_flush();
    }

    /**
     * Удаляет все файлы и папки в указанной директории.
     *
     * @param string $folder_path Путь до папки которую нужно очистить.
     */
    static function _clear_folder( $folder_path, $del_current = false ){

        $folder_path = untrailingslashit( $folder_path );

        foreach( glob("$folder_path/*") as $file ){
            if( is_dir($file) )
                call_user_func( __METHOD__, $file, true );
            else
                unlink( $file );
        }

        if( $del_current )
            rmdir( $folder_path );
    }

}