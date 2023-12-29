<?php
/**
 * Класс для создания отдельной миниатюры и функции обертки для этого класса.
 */
class Kama_Make_Thumb {

    /** @var string */
	public $src;

    /** @var int */
	public $width;

    /** @var int */
	public $height;

    /** @var bool|array */
	public $crop;

	/** @var int|float */
	public $quality;

	/** @var int */
	public $post_id;

	/** @var bool */
	public $no_stub;

	/** @var string - URL заглушки */
	public $stub_url;

	/** @var bool - webp support */
	public $webp;

	/** @var bool - в приоритете над crop */
	public $notcrop;

    /** @var bool - не увеличивать маленькие картинки до указанных размеров. С версии 3.6.*/
	public $rise_small;

	/** @var array| string - переданные аргументы */
	public $args;

	/** @var object - опции плагина */
	public $opt;

	/** @var bool|null - устанавливается в настройках админки */
	public $debug = null;

	/** @var string */
	public $thumb_path;

	/** @var string */
	public $thumb_url;

	/** @var array - разные данные для дебага */
	public $metadata = [];

	/** @var Kama_Make_Thumb - последний экземпляр, для доступа к $width, $height и т.д. */
	static $last_instance;

	/** @var string - текущий домен без www. и поддоменов: www.foo.site.com >>> site.com */
	static $_main_host = '';

	/** @var int - миниатюр создано за поток */
	static $_thumbs_created = 0;

	/** @var bool - принудительно менять формат */
    public $force_format;

    public $disable_http;

    public function __construct( $args = array(), $src = 'notset' ){
		$this->opt = clone Kama_Thumbnail_Plugin::$opt;

		if( ! self::$_main_host ) { // multisite support
            self::$_main_host = self::parse_main_dom(get_option('home'));
        }

		$this->opt->allow_hosts = array_merge( // добавляем разрешенные
			$this->opt->allow_hosts, [ self::$_main_host, 'youtube.com', 'youtu.be' ]
		);

		if( null === $this->debug ) {
            $this->debug = !empty($this->opt->debug);
        }

		$this->set_args( $args, $src );

		self::$last_instance = $this;
	}


    /**
     * Обработка параметров для создания миниатюр.
     *
     * @param array  $args
     * @param string|int $src - attach_id if is num
     */
    protected function set_args( $args = [], $src = 'notset' ){

        $def_args = apply_filters( 'kama_thumb_default_args', [
            'force_format' => null, // формат выходного изображения: jpg, png, gif
            'webp'        => $this->opt->webp,
            'stub_url'    => $this->opt->no_photo_url,  // url картинки заглушки
            'allow'       => $this->opt->allow_hosts,  // разрешенные хосты для этого запроса, чтобы не указывать настройку глобально
            'width'       => '',  // пропорционально
            'height'      => '',  // пропорционально
            'attach_id'   => is_numeric($src) ? intval($src) : 0,
            'src'         => $src, // алиасы 'url', 'link', 'img'
            'quality'     => $this->opt->quality,
            'post_id'     => '', // алиас 'post'
            'rise_small'  => $this->opt->rise_small, // увеличивать ли изображения, если они меньше указанных размеров. По умолчанию: true.
            'crop'        => true, // чтобы отключить кадрирование, укажите: 'false/0/no/none' или определите параметр 'notcrop'.
            // можно указать строку: 'right/bottom' или 'top', 'bottom', 'left', 'right', 'center' и любые их комбинации.
            // это укажет область кадрирования:
            // 'left', 'right' - для горизонтали
            // 'top', 'bottom' - для вертикали
            // 'center' - для обоих сторон
            // когда указывается одно значение, второе будет по умолчанию. По умолчанию 'center/center'
            // атрибуты тегов IMG и A
            'class'     => 'aligncenter',
            'style'     => '',
            'alt'       => '',
            'title'     => '',
            'attr'      => '', // произвольная строка, вставляется как есть
            'a_class'   => '',
            'a_style'   => '',
            'a_attr'    => '',
            'force_lib' => '', // GD (gd), Imagick (imagick) - force lib use
            'disable_http' => $this->opt->disable_http,
        ] );

        if( is_string( $args ) ){
            // parse_str превращает пробелы в "_", например тут "w=230 &h=250 &notcrop &class=aligncenter" notcrop будет notcrop_
            $args = preg_replace( '/ +&/', '&', trim($args) );
            parse_str( $args, $rg );
        } else {
            $rg = $args;
        }

        $rg = array_merge( $def_args, $rg );


        foreach( $rg as & $val ) {
            if (is_string($val)) {
                $val = trim($val);
            }
        }
        unset( $val );

        // aliases
        if( isset($rg['w']) )           $rg['width']   = $rg['w'];
        if( isset($rg['h']) )           $rg['height']  = $rg['h'];
        if( isset($rg['q']) )           $rg['quality'] = $rg['q'];
        if( isset($rg['post']) )        $rg['post_id'] = $rg['post'];
        if( is_object($rg['post_id']) ) $rg['post_id'] = $rg['post_id']->ID; // если в post_id передан объект поста
        if( isset($rg['url']) )         $rg['src']     = $rg['url'];
        elseif( isset($rg['link']) )    $rg['src']     = $rg['link'];
        elseif( isset($rg['img']) )     $rg['src']     = $rg['img'];

        if( is_numeric($rg['src']) && 'notset' === $src) {
            $rg['attach_id'] = $rg['src'];
            //$src = $rg['attach_id'];
        }

        // fixes
        if( $rg['attach_id'] && $atch_url = wp_get_attachment_url($rg['attach_id']) ) {
            $rg['src'] = $atch_url;
        }

        if( in_array($rg['crop'], ['no','none'], true) ) {
            $rg['crop'] = false;
        }

        if( 'notset' === $rg['src'] ) {
            $rg['src'] = '';
        }
        if( empty($rg['src']) ) { // when src = ''/null/false
            $rg['src'] = 'no_photo';
        }

        // set props
        $this->src        = (string) $rg['src'];
        $this->stub_url   = (string) $rg['stub_url'];
        $this->width      = (int)    $rg['width'];
        $this->height     = (int)    $rg['height'];
        $this->quality    = (int)    $rg['quality'];
        $this->post_id    = (int)    $rg['post_id'];
        $this->webp       = !!       $rg['webp'];

        $this->notcrop    = isset( $rg['notcrop'] ); // до $this->crop
        $this->crop       = $this->notcrop ? false : $rg['crop'];
        $this->rise_small = !! $rg['rise_small'];

        $this->force_format = null;
        if( $rg['force_format'] ){
            $format = strtolower( sanitize_key($rg['force_format']) );
            if( 'jpg' === $format )
                $format = 'jpeg';
            if( in_array( $format, ['jpeg','png','gif'], true ) )
                $this->force_format = $format;
        }

        // default thumb size
        if( ! $this->width && ! $this->height ){
            $this->width = $this->height = 100;
        }

        // кадрирование не имеет смысла если одна из сторон равна 0 - она всегда будет подограна пропорционально...
        if( ! $this->height || ! $this->width ){
            $this->crop = false;
        }

        // crop to array
        if( $this->crop ){
            if( in_array($this->crop, [ true, 1, '1' ], true) ){
                $this->crop = [ 'center','center' ];
            }
            else {
                if( is_string($this->crop) )  $this->crop = preg_split( '~[/,: -]~', $this->crop ); // top/right
                if( ! is_array($this->crop) ) $this->crop = [];

                $xx = & $this->crop[0];
                $yy = & $this->crop[1];

                // поправим если неправильно указаны оси...
                if( in_array($xx, [ 'top','bottom' ] ) ){ $this->crop[1] = $xx; $this->crop[0] = 'center'; }
                if( in_array($yy, [ 'left','right' ] ) ){ $this->crop[0] = $yy; $this->crop[1] = 'center'; }

                if( ! $xx || ! in_array($xx, [ 'left','center','right' ] ) ) $xx = 'center';
                if( ! $yy || ! in_array($yy, [ 'top','center','bottom' ] ) ) $yy = 'center';
            }
        }

        if( isset($rg['yes_stub']) ) {
            $this->no_stub = false;
        } else {
            $this->no_stub = (isset($rg['no_stub']) || !empty($this->opt->no_stub));
        }

        // add allowed hosts
        if( !empty($rg['allow']) ){
            $hosts = is_string($rg['allow']) ? preg_split( '/[, ]+/', $rg['allow'] ) : $rg['allow'];
            foreach($hosts as $host ) {
                $this->opt->allow_hosts[] = ($host === 'any') ? $host : self::parse_main_dom($host);
            }
        }

        $this->disable_http = !! $rg['disable_http'];

        $this->args = apply_filters( 'kama_thumb_set_args', $rg, $this );
    }


    /**
     * Создает миниатюру и/или получает URL миниатюры.
     * @return string
     */
    public function src(){
        $src = $this->do_thumbnail();
        return apply_filters( 'kama_thumb_src', $src, $this->args );
    }

    /**
     * Получает IMG тег миниатюры.
     * @return string
     */
    public function img(){

        if( ! $src = $this->src() ) {
            return '';
        }

        $rg = & $this->args;

        // alt
        if( ! $rg['alt'] && $rg['attach_id'] ) {
            $rg['alt'] = get_post_meta($rg['attach_id'], '_wp_attachment_image_alt', true);
        }
        if( ! $rg['alt'] && $rg['title'] ){
            $rg['alt'] = $rg['title'];
        }

        $attrs = [
            'src' => esc_url( $src )
        ];

        // width height на этот момент всегда точные!
        if( $this->width )  $attrs['width']  = intval( $this->width );
        if( $this->height ) $attrs['height'] = intval( $this->height );

        $attrs['alt'] = $rg['alt'] ? esc_attr( $rg['alt'] ) : '';
        $attrs['loading'] = 'lazy';

        if( $rg['class'] ) $attrs['class'] = preg_replace('/[^A-Za-z0-9 _-]/', '', $rg['class'] );
        if( $rg['title'] ) $attrs['title'] = esc_attr( $rg['title'] );
        if( $rg['style'] ) $attrs['style'] = str_replace( '"', "'", strip_tags( $rg['style'] ) );
        if( $rg['attr'] )  $attrs['attr']  = $rg['attr'];

        $implode = [];
        foreach( $attrs as $attr => $val ){
            $implode[] = ( 'attr' === $attr ) ? $val : "$attr=\"$val\"";
        }

        $out = '<img '. implode( ' ', $implode ) .'>';

        return apply_filters( 'kama_thumb_img', $out, $rg, $attrs );
    }

    /**
     * Получает IMG в A теге.
     * @return string
     */
    public function a_img(){
        if( ! $img = $this->img() ) {
            return '';
        }
        $rg = & $this->args;
        $attrs = [
            'href' => esc_url( $this->src )
        ];

        /*if(is_admin()){
            $rg['a_class']  = $rg['a_class'] ? $rg['a_class'].' thickbox' : 'thickbox';
        }*/

        if( $rg['a_class'] ){
            $attrs['class'] = preg_replace('/[^A-Za-z0-9 _-]/', '', $rg['a_class'] );
        }
        if( $rg['a_style'] ){
            $attrs['style'] = str_replace( '"', "'", strip_tags( $rg['a_style'] ) );
        }
        if( $rg['a_attr'] ) {
            $attrs['attr']  = $rg['a_attr'];
        }

        $implode = [];
        foreach( $attrs as $attr => $val ){
            $implode[] = ( 'attr' === $attr ) ? $val : "$attr=\"$val\"";
        }
        $out = '<a '. implode( ' ', $implode ) .'>'. $img .'</a>';

        return apply_filters( 'kama_thumb_a_img', $out, $rg, $attrs );
    }

	/**
	 * Create thumbnail or get it from cache
     *
	 * @return null|false|string - Thumbnail URL or false.
	 */
	protected function do_thumbnail(){

        if (empty($this->src) || 'no_photo' === $this->src) { // если не передана ссылка, то ищем её в контенте и записываем пр.поле

            if(!empty($this->post_id)) {
                $this->src = $this->get_src_and_set_postmeta();
            }

            if( empty($this->src) ){
                trigger_error( 'ERROR: No $src prop.', E_USER_NOTICE );
                return false;
            } elseif( 'no_photo' === $this->src ){ // if it's placeholder image
                if( $this->no_stub ) {
                    return '';
                }
                $this->src = $this->stub_url;
            }
            $this->src = html_entity_decode( $this->src ); // 'sd&#96;asd.jpg' to 'sd`asd.jpg'
        }

		// запрос отправил этот плагин, выходим чтобы избежать рекурсии:  это запрос на картинку, которой нет (404 страница).
		if( isset($_GET['kthumb']) ) {
            return null;
        }

		// позволяет обработать src и вернуть его прервав дальнейшее выполенение кода.
		if( $res = apply_filters_ref_array( 'pre_do_thumbnail_src', [ '', & $this ] ) ) {
            return $res;
        }

		// get new file data
		$name_data = $this->file_name_data();
		if( !isset($name_data) ) { // что-то не то с src
            return null;
        } else if( empty($name_data->file_name) ) { // пропускаем SVG
            return $this->src;
        }

		$this->thumb_path = $this->opt->cache_folder     ."/$name_data->sub_dir/$name_data->file_name";
		$this->thumb_url  = $this->opt->cache_folder_url ."/$name_data->sub_dir/$name_data->file_name";

		// CACHE --
		if( ! $this->debug ){

			$thumb_url = apply_filters_ref_array( 'cached_thumb_url', [ '', & $this ] );

			if( ! $thumb_url && file_exists($this->thumb_path) ){
				$thumb_url = $this->thumb_url;

				$this->metadata['cache'] = 'found';
			}

			// есть заглушка возвращаем её
			if( ! $thumb_url && file_exists( $stub_thumb_path = $this->_change_to_stub($this->thumb_path) ) ){
				$this->thumb_path = $stub_thumb_path;
				$this->thumb_url  = $this->_change_to_stub( $this->thumb_url );
				$this->metadata['cache'] = 'stub';
				if( $this->no_stub ) {
                    return false;
                }

				$thumb_url = $this->thumb_url;
			}

			// Кэш найден. Установим/проверим оригинальные размеры.
			if( $thumb_url ){
				$this->checkset_width_height();
				return $thumb_url;
			}
		}

		// STOP if execution time exceed
		if( microtime( true ) - $GLOBALS['timestart'] > $this->opt->stop_creation_sec ){
			static $stop_error_shown;
			if( ! $stop_error_shown && $stop_error_shown = 1 ){
				trigger_error( sprintf('Kama Thumb STOPED (time exceed). %d thumbs created.', self::$_thumbs_created), E_USER_NOTICE );
			}
			return $this->src;
		}

		// NO CACHE - create thumb --
		if( false === $this->check_create_folder() ){
			if( class_exists('Kama_Thumbnail_Plugin') ){
				Kama_Thumbnail_Plugin::show_message(
					sprintf( __('Folder where thumbs will be created not exists. Create it manually: "s%"','thumbnail'), $this->opt->cache_folder ), 'error'
				);
				return false;
			} else {
                wp_die('Kama_Thumbnail plugin: No cache folder. Create it: ' . $this->opt->cache_folder);
            }
		}

		if( true !== $this->_is_allow_host($this->src) ){
			$this->src = $this->stub_url;
			$this->metadata['stub'] = 'stub not allowed host';
		}

		$this->src = self::_fix_src_protocol_domain( $this->src );
		$img_string = $this->get_img_string();
		$size = !empty($img_string) ? $this->_image_size_from_string( $img_string ) : false; // false

        /* Что-то не то. Вернем заглушку.
        Если не удалось получить картинку: недоступный хост, файл пропал после переезда или еще чего.
        То для указаного УРЛ будет создана миниатюра из заглушки no_photo.jpg
        Чтобы после появления файла, миниатюра создалась правильно, нужно очистить кэш картинки.
        */
		if( empty($img_string) || empty($size['mime']) || false === strpos( $size['mime'], 'image' ) ){
			$this->metadata['stub'] = 'stub: image not found';
			$this->src = self::_fix_src_protocol_domain( $this->stub_url );
            $this->thumb_path = $this->_change_to_stub( $this->thumb_path ); // path to url
            $this->thumb_url  = $this->_change_to_stub( $this->thumb_url );
            return $this->opt->no_photo_url ?? $this->thumb_url;
		} else {
			$this->metadata += [
				'mime'   => $size['mime'],
				'width'  => $size[0],
				'weight' => $size[1],
			];
		}

		// Изменим название файла, если это картинка заглушка и вернем ее.
        // stub: URL not image
		// if( isset($this->metadata['stub']) && !empty($this->metadata['stub']) )

		if( empty($img_string) ){
			trigger_error( 'ERROR: Couldn`t get img data, even no_photo.', E_USER_NOTICE );
			return false;
		}

		// Create thumb
		$use_lib = strtolower( $this->args['force_lib'] );
		if( ! $use_lib ) $use_lib = extension_loaded('imagick') ? 'imagick' : '';
		if( ! $use_lib ) $use_lib = extension_loaded('gd')      ? 'gd'      : '';

		// Imagick
		if( 'imagick' === $use_lib ){
			$this->metadata['lib'] = 'Imagick'; // до вызова!
			$done = $this->make_thumbnail_Imagick( $img_string );
		} elseif( 'gd' === $use_lib ){ // GD if Imagick was fail
			$this->metadata['lib'] = 'GD';
			$done = $this->make_thumbnail_GD( $img_string );
		} else { // no lib
			trigger_error( 'ERROR: There is no one of the Image libraries (GD or Imagick) installed on your server.', E_USER_NOTICE );
			$done = false;
		}


		if( $done ){
			// установим/изменим размеры картинки в свойствах класса, если надо
			$this->checkset_width_height();
		} else {
			$this->thumb_url = '';
		}

		// allow process created thumbnail, for example, to compress it
		do_action( 'kama_thumb_created', $this->thumb_path, $this );

		self::$_thumbs_created++;

		return $this->thumb_url;
	}

    /**
     * Ядро: создание и запись файла-картинки на основе библиотеки Imagick
     *
     * @param string $img_string - картинка в строковом формате
     * @return bool
     */
	protected function make_thumbnail_Imagick( string $img_string){

		try {
			$image = new Imagick();
			$image->readImageBlob( $img_string );
			// Select the first frame to handle animated images properly
			if( is_callable( [ $image, 'setIteratorIndex' ] ) ) {
                $image->setIteratorIndex(0);
            }
			// устанавливаем качество
			$format = $image->getImageFormat();
			if( in_array( $format, ['JPEG', 'JPG'] ) ) {
                $image->setImageCompression(Imagick::COMPRESSION_JPEG);
            }
			if( 'PNG' === $format ){
				$image->setOption( 'png:compression-level', $this->quality );
			}

			$image->setImageCompressionQuality( $this->quality );
			$origin_h = $image->getImageHeight();
			$origin_w = $image->getImageWidth();

			// получим координаты для считывания с оригинала и размер новой картинки
			[ $dx, $dy, $wsrc, $hsrc, $width, $height ] = $this->_resize_coordinates( $origin_w, $origin_h );

			// crop
			$image->cropImage( $wsrc, $hsrc, $dx, $dy );
			$image->setImagePage( $wsrc, $hsrc, 0, 0 );

			// strip out unneeded meta data
			$image->stripImage();

			// уменьшаем под размер
			$image->scaleImage( $width, $height );

			if( $this->force_format )
				$image->setImageFormat( $this->force_format );

			$this->metadata['thumb_format'] = $image->getImageFormat();

            if( $this->webp ) {
                $image->writeImage('webp:'. $this->thumb_path );
            } else {
                $image->writeImage( $this->thumb_path );
            }

            // chmod( $this->thumb_path, 0644 );
			$image->clear();
			$image->destroy();

			return true;
		}
		catch( ImagickException $e ){
			error_log( 'ImagickException: '. $e->getMessage() );
			$this->metadata['lib'] = 'GD (force)';
			return $this->make_thumbnail_GD( $img_string ); // Пробуем создать через GD.
		}
	}

	/**
	 * Ядро: создание и запись файла-картинки на основе библиотеки GD
	 *
	 * @param string $img_string
	 *
	 * @return bool
	 */
	protected function make_thumbnail_GD( $img_string ){

		$size = $this->_image_size_from_string( $img_string );

		// нет параметров у файла
		if( $size === false )
			return false;

		// Создаем ресурс
		$image = imagecreatefromstring( $img_string );
		if ( ! is_resource( $image ) ) {
            return false;
        }

		[ $origin_w, $origin_h ] = $size;

		// получим координаты для считывания с оригинала и размер новой картинки
		[ $dx, $dy, $wsrc, $hsrc, $width, $height ] = $this->_resize_coordinates( $origin_w, $origin_h );

		// холст
		$thumb = imagecreatetruecolor( $width, $height );

		if( function_exists('imagealphablending') && function_exists('imagesavealpha') ){
			imagealphablending( $thumb, false ); // режим сопряжения цвета и альфа цвета
			imagesavealpha( $thumb, true );      // флаг сохраняющий прозрачный канал
		}

		// включим функцию сглаживания
		if( function_exists('imageantialias') ){
			imageantialias( $thumb, true );
		}

		// изменяем размер
		if( ! imagecopyresampled( $thumb, $image, 0, 0, $dx, $dy, $width, $height, $wsrc, $hsrc ) ){
			return false;
		}

		// save image
		$thumb_format = explode( '/', $size['mime'] )[1];
		if( $this->force_format )
			$thumb_format = $this->force_format;

		if( $this->webp )
			$thumb_format = 'webp';

		// convert from full colors to index colors, like original PNG.
		if( 'png' === $thumb_format ){
			$this->quality = floor( $this->quality / 10 );

			if( function_exists('imageistruecolor') && ! imageistruecolor( $thumb ) )
				imagetruecolortopalette( $thumb, false, imagecolorstotal( $thumb ) );
		}

		// transparent
		if( 'gif' === $thumb_format ){
			$transparent = imagecolortransparent( $thumb, imagecolorallocate($thumb, 0, 0, 0) );
			$_width  = imagesx( $thumb );
			$_height = imagesy( $thumb );
			for( $x = 0; $x < $_width; $x++ ){
				for( $y = 0; $y < $_height; $y++ ){
					$pixel = imagecolorsforindex( $thumb, imagecolorat($thumb, $x, $y) );
					if( $pixel['alpha'] >= 64 ){
						imagesetpixel( $thumb, $x, $y, $transparent );
					}
				}
			}
		}

		// jpg / png / webp / gif
		$func_name = function_exists( "image$thumb_format" ) ? "image$thumb_format" : 'imagejpeg';

		$this->metadata['thumb_format'] = $func_name;
		$func_name( $thumb, $this->thumb_path, $this->quality );
        imagedestroy( $image );
        imagedestroy( $thumb );
		chmod( $this->thumb_path, 0644 );

		return true;
	}

    /**
     * Исправляет указанный URL: добавляет протокол, домен (для относительных ссылок) и т.д.
     *
     * @param string $src
     * @return string full URL
     */
	protected static function _fix_src_protocol_domain(string $src){
		if( 0 === strpos($src, '//') ) { // УРЛ без протокола: //site.ru/foo
            $src = (is_ssl() ? 'https' : 'http') . ":$src";
        } elseif (isset($src[0]) && '/' === $src[0]) { // относительный УРЛ
            $src = home_url($src);
        }
		return $src;
	}

	/**
	 * Изменяет переданный path/URL файла миниатюры, делая его путём к заглушке (stub).
	 *
	 * @param string $path_url Path/URL до файла миниатюры.
	 *
	 * @return string Новые Path/URL.
	 */
	protected function _change_to_stub( $path_url ){

		$bname = basename( $path_url );

		$base = preg_match( '~^(http|/)~', $path_url ) ? $this->opt->cache_folder_url : $this->opt->cache_folder;

		return "$base/stub_$bname";
	}

	/**
	 * Пытается получить данные картинки (в виде строки) по указанному URL картинки.
	 *
	 * @return string Данные картинки или пустую строку.
	 */
	public function get_img_string(){

		$img_str = '';
		$img_url = $this->src;

		// Добавим метку к внутренним URL, чтобы избежать рекурсии, когда фотки нет
		// и мы попадаем на 404 страницу, где опять создается эта же миниатюра.
		if( false !== strpos($this->src, self::$_main_host) )
			$img_url .= ( strpos($this->src, '?') ? '&' : '?' ) . 'kthumb'; // add_query_arg() юзать нельзя

		if( false === strpos( $img_url, 'http') && '//' !== substr( $img_url, 0, 2 )  )
			die( 'ERROR: image url begins with not "http" or "//". The URL: ' . esc_html($img_url) );

		// by ABSPATH ----
		//if(0) // off
		if( ! $img_str && strpos( $img_url, $_SERVER['HTTP_HOST'] ) ){
			$this->metadata['request_type'] = 'ABSPATH';

			// получим корень сайта $_SERVER['DOCUMENT_ROOT'] может быть неверный
			$root = ABSPATH;

			// maybe WP in sub dir?
			$root_parent = dirname( ABSPATH ) .'/';
			if( @ file_exists( $root_parent . 'wp-config.php') && ! file_exists( $root_parent . 'wp-settings.php' ) ){
				$root = $root_parent;
			}

			// skip query args
			$img_path = preg_replace( '~^https?://[^/]+/(.*?)([?].+)?$~', "$root\\1", $img_url );

			if( file_exists( $img_path ) )
				$img_str = $this->debug ? file_get_contents( $img_path ) : @ file_get_contents( $img_path );
		}

		// disable http
		if(true === $this->disable_http){
		    return $img_str ?: '';
        }

		// WP HTTP API ----
		//if(0) // off
		if( ! $img_str && function_exists('wp_remote_get') ){
			$this->metadata['request_type'] = 'wp_remote_get';
			$img_str = wp_remote_retrieve_body( wp_remote_get($img_url) );
		}

		// file_get_contents ----
		//if(0) // off
		if( ! $img_str && ini_get('allow_url_fopen') ){
			$this->metadata['request_type'] = 'file_get_contents';
			// try find 200 OK. it may be 301, 302 redirects. In 3** redirect first status will be 3** and next 200 ...
			$OK_200 = false;
			$headers = (array) @ get_headers( $img_url );
			foreach( $headers as $line ){
				if( false !== strpos( $line, '200 OK' ) ){
					$OK_200 = true;
					break;
				}
			}
			if( $OK_200 ) {
                $img_str = file_get_contents($img_url);
            }
		}

		// CURL ----
		//if(0) // off
		if( ! $img_str && (extension_loaded('curl') || function_exists('curl_version')) ){
			$this->metadata['request_type'] = 'curl';
			$ch = curl_init();
			curl_setopt_array( $ch, [
				CURLOPT_URL            => $img_url,
				CURLOPT_FOLLOWLOCATION => true,  // To make cURL follow a redirect
				CURLOPT_HEADER         => false,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_SSL_VERIFYPEER => false, // accept any server certificate
			]);
			$img_str = curl_exec( $ch );
			//$errmsg = curl_error( $ch );
			$info = curl_getinfo( $ch );
			curl_close( $ch );
			if( @ $info['http_code'] != 200 ) {
                $img_str = '';
            }
		}

		// если по URL вернулся HTML код (например страница 404)
		// проверяем только начальные 400 (может надо поменять) символов,
		// потому что '<!DOCTYPE' может быть в метаданных картинки
		if( false !== strpos( trim( substr( $img_str, 0, 400 ) ), '<!DOCTYPE') ){
			$this->metadata['img_str_error'] = 'DOCTYPE in img_str';
			$img_str = '';
		}

		// в метаданных есть <script> - опасная картинка...
		if( false !== strpos( $img_str, '<script') ){
			trigger_error( 'The &lt;script&gt; found in image data URL: '. esc_html( $img_url ) );
			$img_str = '';
		}

		return $img_str ?: '';
	}

	/**
	 * Получает координаты кадрирования.
	 *
	 * @param int $origin_w Оригинальная ширина
	 * @param int $origin_h Оригинальная высота
	 *
	 * @return array   отступ по Х и Y и сколько пикселей считывать по высоте и ширине у источника: $dx, $dy, $wsrc, $hsrc
	 */
	protected function _resize_coordinates( $origin_w, $origin_h ){

		// если указано не увеличивать картинку и она меньше указанных размеров, укажем максимальный размер - это размер самой картинки
		// важно указать глобальные значения, они юзаются в width и height IMG атрибута и может еще где-то...
		if( ! $this->rise_small ){
			if( $origin_w < $this->width )  $this->width  = $origin_w;
			if( $origin_h < $this->height ) $this->height = $origin_h;
		}

		$crop   = $this->crop;
		$width  = $this->width;
		$height = $this->height;

		// елси не нужно кадрировать и указаны обе стороны, то находим меньшую подходящую сторону у картинки и обнуляем её
		if( ! $crop && ($width > 0 && $height > 0) ){
			if( $width/$origin_w < $height/$origin_h )
				$height = 0;
			else
				$width = 0;
		}

		// если не указана одна из сторон задаем ей пропорциональное значение
		if( ! $width ) 	$width  = round( $origin_w * ($height/$origin_h) );
		if( ! $height ) $height = round( $origin_h * ($width/$origin_w) );

		// определяем необходимость преобразования размера так чтоб вписывалась наименьшая сторона
		// if( $width < $origin_w || $height < $origin_h )
			$ratio = max( $width/$origin_w, $height/$origin_h );

		// определяем позицию кадрирования
		$dx = $dy = 0;
		if( is_array($crop) ){

			$xx = $crop[0];
			$yy = $crop[1];

			// срезать слева и справа
			if( $height/$origin_h > $width/$origin_w ){
				if(0){}
				elseif( $xx === 'center' ) $dx = round( ($origin_w - $width * ($origin_h/$height)) / 2 ); // отступ слева у источника
				elseif( $xx === 'left' )   $dx = 0;
				elseif( $xx === 'right' )  $dx = round( ($origin_w - $width * ($origin_h/$height)) ); // отступ слева у источника
			}
			// срезать верх и низ
			else {
				if(0){}
				elseif( $yy === 'center' ) $dy = round( ($origin_h - $height * ($origin_w/$width)) / 2 );
				elseif( $yy === 'top' )    $dy = 0;
				elseif( $yy === 'bottom' ) $dy = round( ($origin_h - $height * ($origin_w/$width)) );
				// $height*$origin_w/$width)/2*6/10 - отступ сверху у источника *6/10 - чтобы для вертикальных фоток отступ сверху был не половина а процентов 30
			}
		}

		// сколько пикселей считывать c источника
		$wsrc = round( $width/$ratio );
		$hsrc = round( $height/$ratio );

		return array( $dx, $dy, $wsrc, $hsrc, $width, $height );
	}

	/**
	 * Проверяет наличие указанной директории, пытается создать, если её нет.
	 *
	 * @return bool
	 */
	protected function check_create_folder(){
		$path = dirname( $this->thumb_path );
		if( is_dir( $path ) ) {
            return true;
        }
		return mkdir( $path, 0755, true );
	}

	/**
	 * Получает реальные размеры картинки.
	 *
	 * @param string $img_data
	 *
	 * @return array|bool
	 */
	protected function _image_size_from_string( $img_data )
    {

        if (function_exists('getimagesizefromstring'))
            return getimagesizefromstring($img_data);

        return @getimagesize('data://application/octet-stream;base64,' . base64_encode($img_data));
    }


    /**
     * Получает ссылку на картинку из произвольного поля текущего поста.
     * Или из текста и создает произвольное поле.
     * Если в тексте картинка не нашлась, то в произвольное поле запишется заглушка `no_photo`.
     *
     * @return string
     */
    public function get_src_and_set_postmeta(){
        global $post, $wpdb;

        if( $src = get_post_meta( $this->post_id, $this->opt->meta_key, true ) ) {
            return $src;
        }

        // проверяем наличие стандартной миниатюры
        if( $_thumbnail_id = get_post_meta( $this->post_id, '_thumbnail_id', true ) ) {
            $src = wp_get_attachment_url((int)$_thumbnail_id);
        }

        // получаем ссылку из контента
        if( ! $src ){
            $content = $this->post_id ? $wpdb->get_var( "SELECT post_content FROM $wpdb->posts WHERE ID = ". intval($this->post_id) ." LIMIT 1" ) : $post->post_content;
            $src = $this->_get_url_from_text( $content );
        }

        // получаем ссылку из вложений - первая картинка
        if( ! $src ){
            $attch_img = get_children( [
                'numberposts'    => 1,
                'post_mime_type' => 'image',
                'post_parent'    => $this->post_id,
                'post_type'      => 'attachment'
            ] );

            if( $attch_img = array_shift( $attch_img ) ) {
                $src = wp_get_attachment_url($attch_img->ID);
            }
        }

        // Заглушка no_photo, чтобы постоянно не проверять
        if( ! $src ) {
            $src = 'no_photo';
        }

        update_post_meta( $this->post_id, $this->opt->meta_key, wp_slash($src) );

        return $src;
    }

    /**
     * Ищет ссылку на картинку в тексте и возвращает её.
     *
     * @param string $text
     *
     * @return mixed|string|void
     */
    public function _get_url_from_text( $text ){

        $allows_patt = '';
        if( ! in_array('any', $this->opt->allow_hosts ) ){
            $hosts_regex = implode( '|', array_map('preg_quote', $this->opt->allow_hosts ) );
            $allows_patt = '(?:www\.)?(?:'. $hosts_regex .')';
        }

        $hosts_patt = '(?:https?://'. $allows_patt .'|/)';

        if(
            ( false !== strpos( $text, 'src=') ) &&
            preg_match('~(?:<a[^>]+href=[\'"]([^>]+)[\'"][^>]*>)?<img[^>]+src=[\'"]\s*('. $hosts_patt .'.*?)[\'"]~i', $text, $match )
        ){
            // проверяем УРЛ ссылки
            $src = $match[1];
            if( ! preg_match('~\.(jpg|jpeg|png|gif)(?:\?.+)?$~i', $src) || ! $this->_is_allow_host($src) ){
                // проверям УРЛ картинки, если не подходит УРЛ ссылки
                $src = $match[2];
                if( ! $this->_is_allow_host($src) )
                    $src = '';
            }

            return $src;
        }

        return apply_filters( 'kama_thumb__get_url_from_text', '', $text );
    }

    /**
     * Проверяет что картинка с разрешенного хоста.
     *
     * @param string $url
     *
     * @return bool|mixed|void
     */
    public function _is_allow_host( $url ){

        // pre filter to change the behavior
        if( $return = apply_filters( 'kama_thumb_is_allow_host', false, $url, $this->opt ) ) {
            return $return;
        }

        if( ( '/' === $url[0] && '/' !== $url[1] ) ||  in_array( 'any', $this->opt->allow_hosts )) {
            return true;
        }

        $host = self::parse_main_dom( $url );
        if( $host && in_array( $host, $this->opt->allow_hosts ) ) {
            return true;
        }

        return false;
    }

    /**
     * Get main domain name from URL or Subdomain:
     * foo.site.com > site.com | sub.site.co.uk > site.co.uk | sub.site.com.ua > site.com.ua
     *
     * @param string  $host  URL or Host like: site.ru, site1.site.ru, xn--n1ade.xn--p1ai
     *
     * @return string Main domain name.
     */
    public static function parse_main_dom( $host ){

        // URL passed || port is specified (dom.site.ru:8080 > dom.site.ru) (59.120.54.215:8080 > 59.120.54.215)
        if( preg_match( '~/|:\d{2}~', $host ) )
            $host = parse_url( $host, PHP_URL_HOST );

        // for http://localhost/foo  or  IP
        if( ! strpos($host, '.') || filter_var($host, FILTER_VALIDATE_IP) )
            return $host;

        $host = preg_replace( '/^www\./', '', $host );

        // cirilic: .сайт, .онлайн, .дети, .ком, .орг, .рус, .укр, .москва, .испытание, .бг
        if( false !== strpos($host, 'xn--') )
            preg_match( '/xn--[^.]+\.xn--[^.]+$/', $host, $mm );
        // other: foo.academy, regery.com.ua, site.ru, foo.bar.photography, bar.tema.agr.co, ps.w.org
        else
            preg_match( '/[a-z0-9][a-z0-9\-]{1,63}\.(?:[a-z]{2,11}|[a-z]{1,3}\.[a-z]{2,3})$/i', $host, $mm );

        return apply_filters( 'kama_thumb_parse_main_dom', $mm[0], $host );
    }

    /**
     * Устанавливает свойства класса width или height, если они неизвестны или не точные (при notcrop).
     * Данные могут пригодится для добавления в HTML.
     *
     * @return null
     */
    protected function checkset_width_height(){

        if( $this->width && $this->height && $this->crop ) {
            return;
        }

        // getimagesize support webP from PHP 7.1
        // speed: 2 sec per 50 000 iterations (fast)
        [ $width, $height ] = @getimagesize( $this->thumb_path );

        if( ! $this->crop ){ // не кадрируется и поэтому одна из сторон всегда будет отличаться от указанной...
            if( $width ) {
                $this->width = $width;
            }
            if( $height ){
                $this->height = $height;
            }
        } else { // кадрируется, но одна из сторон может быть не указана, проверим и определим если надо
            if( ! $this->width )  $this->width  = $width;
            if( ! $this->height ) $this->height = $height;
        }
    }

    /**
     * Parse src and make thumb file name and other name data.
     *
     * @return object|null {
     *     Object of data.
     *
     *     @type string $ext       File extension.
     *     @type string $suffix    file suffix if not crop or not rise
     *     @type string $src_md5   file md5
     *     @type string $file_name Thumb File name.
     *     @type string $sub_dir   Thumb File parent directory name.
     * }
     * null - if error
     */
    protected function file_name_data(){
        $srcpath = parse_url( $this->src, PHP_URL_PATH );
        if( ! $srcpath ) { // неправильный URL
            return null;
        }

        $data = new stdClass();

        if( preg_match( '~\.([a-z0-9]{2,4})$~i', $srcpath, $mm ) ) {
            $data->ext = strtolower($mm[1]);
        } elseif( preg_match( '~\.(jpe?g|png|gif|svg|bmp|webp)~i', $this->src, $mm ) ) {
            $data->ext = strtolower($mm[1]);
        } else {
            $data->ext = 'png';
        }


        if( $this->webp ) {
            $data->ext = 'webp';
        }
        if( $this->force_format ) {
            $data->ext = $this->force_format;
        }

        if( 'svg' === $data->ext ){
            $data->file_name = '';
        } else {
            $data->suffix = '';
            if( ! $this->crop && ( $this->height && $this->width ) ) {
                $data->suffix .= '_notcrop';
            }
            if( is_array( $this->crop ) && preg_match( '~top|bottom|left|right~', implode('/', $this->crop), $mm ) ) {
                $data->suffix .= "_$mm[0]";
            }
            if( ! $this->rise_small ) {
                $data->suffix .= '_notrise';
            }

            // Нельзя юзать `md5( $srcpath )` т.к. URL может отличаться по параметрам запроса.
            // отрежем домен и создадим хэш.
            $data->src_md5 = md5( preg_replace( '~^(https?:)?//[^/]+~', '', $this->src ) );

            $file_name = substr( $data->src_md5, -15 ) . "_{$this->width}x{$this->height}$data->suffix.$data->ext";
            $sub_dir   = substr( $data->src_md5, -2 );
            $data->file_name = apply_filters_ref_array( 'kama_thumb_make_file_name', [ $file_name, $data, & $this ] );
            $data->sub_dir   = apply_filters_ref_array( 'kama_thumb_file_sub_dir',   [ $sub_dir,   $data, & $this ] );
        }

        return $data;
    }

}



