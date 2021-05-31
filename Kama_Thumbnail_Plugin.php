<?php

final class Kama_Thumbnail_Plugin {

    static $opt_name     = 'kama_thumbnail';
    static $opt_pagename = 'media'; // kama_thumb on multisite
    static $opt;
    private $skip_setting_page = false;

	use Kama_Thumbnail_Admin_Part;
	use Kama_Thumbnail_Clear_Cache;

	public function __get( $name ){
		if( $name === 'opt' ) {
            return self::$opt;
        }
	}

	public function __construct(){

		if( $this->skip_setting_page = has_filter( 'kama_thumb_def_options' ) ){
			$opts = [];
		}
		else {
			if( is_multisite() ){
				$opts = get_site_option( self::$opt_name, [] );
				self::$opt_pagename = 'kama_thumb';
			} else {
                $opts = get_option(self::$opt_name, []);
            }
		}

		self::$opt = (object) array_merge( self::def_options(), $opts );

		$opt = & self::$opt;

		// дополним опции (ниже определения опций)
		if( ! $opt->no_photo_url )     $opt->no_photo_url     = KT_URL . 'no_photo.jpg';
		if( ! $opt->cache_folder )     $opt->cache_folder     = str_replace( '\\', '/', WP_CONTENT_DIR . '/cache/thumb');
		if( ! $opt->cache_folder_url ) $opt->cache_folder_url = content_url() . '/cache/thumb';

		$opt->cache_folder     = untrailingslashit( $opt->cache_folder );
		$opt->cache_folder_url = untrailingslashit( $opt->cache_folder_url );

		// allow_hosts
		$ah = & self::$opt->allow_hosts;
		if( $ah && ! is_array($ah) ){
			$ah = preg_split( '/[\s,]+/s', trim( $ah ) ); // сделаем массив
			foreach( $ah as & $host )
				$host = str_replace( 'www.', '', $host );
		} else {
            $ah = [];
        }

        if( is_admin() ){

            add_action( 'admin_menu', [ $this, 'cache_clear_handler' ] );
            add_filter( 'save_post',  [ $this, 'clear_post_meta' ] );

            // Включает страницу опций только если не были переопределных дефолтные опции через хук `kama_thumb_def_options`
            if( ! defined('DOING_AJAX') && ! $this->skip_setting_page ){
                add_action( (is_multisite() ? 'network_admin_menu' : 'admin_menu'), [ $this, 'admin_options' ] );
                // ссылка на настойки со страницы плагинов
                add_filter( 'plugin_action_links', [ $this, 'setting_page_link' ], 10, 2 );
                // ловля обновления опций
                if( is_multisite() ) {
                    add_action('network_admin_edit_' . 'kt_opt_up', [$this, 'network_options_update']);
                }
            }
        }

        if( self::$opt->use_in_content ){
            add_filter( 'the_content',     [ $this, 'replece_in_content' ] );
            add_filter( 'the_content_rss', [ $this, 'replece_in_content' ] );
        }

        // re-set (for multisite)
        is_multisite() && add_action( 'switch_blog', function(){
            Kama_Make_Thumb::$_main_host = Kama_Make_Thumb::parse_main_dom( get_option('home') );
        });

        do_action( 'kama_thumb_inited', self::$opt );
	}


	/**
	 * Поиск и замена в контенте записи.
	 *
	 * @param string $content
	 * @return string
	 */
	public function replece_in_content( $content ){
		$miniclass = (self::$opt->use_in_content == 1) ? 'mini' : self::$opt->use_in_content;
		if( false !== strpos( $content, '<img ') && strpos( $content, $miniclass ) ){
			$img_ex = '<img([^>]*class=["\'][^\'"]*(?<=[\s\'"])'. $miniclass .'(?=[\s\'"])[^\'"]*[\'"][^>]*)>';
			// разделение ускоряет поиск почти в 10 раз
			$content = preg_replace_callback( "~(<a[^>]+>\s*)$img_ex|$img_ex~", [ $this, '_replece_in_content' ], $content );
		}
		return $content;
	}

	private function _replece_in_content( $m ){

		$a_prefix = $m[1];
		$is_a_img = '<a' === substr( $a_prefix, 0, 2);
		$attr     = $is_a_img ? $m[2] : $m[3];

		$attr = trim( $attr, '/ ');

		// get src="xxx"
		preg_match('~src=[\'"]([^\'"]+)[\'"]~', $attr, $match_src );
		$src = $match_src[1];
		$attr = str_replace( $match_src[0], '', $attr );

		// make args from attrs
		$args = preg_split('~ *(?<!=)["\'] *~', $attr );
		$args = array_filter( $args );

		$_args = array();
		foreach( $args as $val ){
			list( $k, $v ) = preg_split('~=[\'"]~', $val );
			$_args[ $k ] = $v;
		}
		$args = $_args;

		// parse srcset if set
		if( isset($args['srcset']) ){
			$srcsets = array_map( 'trim', explode(',', $args['srcset']) );
			$_cursize = 0;
			foreach( $srcsets as $_src ){
				preg_match( '/ ([0-9]+[a-z]+)$/', $_src, $mm );
				$size = $mm[1];
				$_src = str_replace( $mm[0], '', $_src );

				// retina
				if( $size === '2x' ){
					$src = $_src;
					break;
				}

				$size = intval($size);
				if( $size > $_cursize )
					$src = $_src;

				$_cursize = $size;
			}

			unset( $args['srcset'] );
		}

		$kt = new Kama_Make_Thumb( $args, $src );

		return $is_a_img ? $a_prefix . $kt->img() : $kt->a_img();
	}

	/**
	 * Очищает произвольное поле со ссылкой при обновлении поста,
	 * чтобы создать его снова. Только если метаполе у записи существует.
	 *
	 * @param int $post_id
	 */
	public function clear_post_meta( $post_id ){
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, self::$opt->meta_key
		) );

		if( $row ) {
            update_post_meta($post_id, self::$opt->meta_key, '');
        }
	}

}