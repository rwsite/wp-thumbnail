<?php

/**
 * Вернет только ссылку на миниатюру.
 *
 * @param array  $args
 * @param string $src
 *
 * @return string
 */
function kama_thumb_src( $args = [], $src = 'notset' ){
    $kt = new Kama_Make_Thumb( $args, $src );
    return $kt->src();
}

/**
 * Вернет картинку миниатюры (готовый тег img).
 *
 * @param array  $args
 * @param string $src
 *
 * @return string
 */
function kama_thumb_img( $args = [], $src = 'notset' ){
    $kt = new Kama_Make_Thumb( $args, $src );
    return $kt->img();
}

/**
 * Вернет картинку миниатюры, которая будет анкором ссылки на оригинал.
 *
 * @param array  $args
 * @param string $src
 *
 * @return mixed|string|void
 */
function kama_thumb_a_img( $args = [], $src = 'notset' ){
    $kt = new Kama_Make_Thumb( $args, $src );
    return $kt->a_img();
}

/**
 * Обращение к последнему экземпляру за свойствами класса: высота, ширина или др...
 *
 * @param string $optname
 *
 * @return mixed|Kama_Make_Thumb|null The value of specified property or
 *                                    `Kama_Make_Thumb` object if no property is specified.
 */
function kama_thumb( $optname = '' ){
    $instance = Kama_Make_Thumb::$last_instance;
    if( ! $optname ) {
        return $instance;
    }
    if( property_exists( $instance, $optname ) ) {
        return $instance->$optname;
    }
    return null;
}