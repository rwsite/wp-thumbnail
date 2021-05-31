=== Plugin Name ===
Stable tag: trunk
Tested up to: 5.5.1
Requires at least: 2.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Contributors: Tkama
Tags: thumbnail, image

### Usage ###

The plugin for developers firstly, because it don't do anything after install. In order to the plugin begin to work, you need use one of plugin function in your theme or plugin. Example:

`
<?php echo kama_thumb_img('w=150 &h=150'); ?>
`

Using the code in the loop you will get ready thumbnail IMG tag. Plugin takes post thumbnail image or find first image in post content, resize it and create cache. Also creates custom field for the post with URL to original image. In simple words it cache all routine and in next page loads just take cache result.

You can make thumbs from custom URL, like this:
`<?php echo kama_thumb_img('w=150 &h=150', 'URL_TO_IMG'); ?>`

The `URL_TO_IMG` must be from local server: by default, plugin don't work with external images, because of security. But you can set allowed hosts on settings page: `Settings > Media`.

**All plugin functions:**

`
// return thumb url URL
echo kama_thumb_src( $args, $src );

// return thumb IMG tag
echo kama_thumb_img( $args, $src );

// return thumb IMG tag wraped with <a>. A link of A will leads to original image.
echo kama_thumb_a_img( $args, $src );

// to get image width or height after thumb creation
echo kama_thumb( $optname );
// ex:
echo '<img src="'. kama_thumb_src('w=200') .'" width="'. kama_thumb('width') .'" height="'. kama_thumb('height') .'" alt="" />';
`

Parameters:

* **$args** (array/string)
	Arguments to create thumb. Accepts:

	* **w | width**
		(int) desired width.

	* **h | height**
		(int) desired height.

		if parameters `w` and `h` not set, both of them became 100 - square thumb 100х100 px.

	* **notcrop**
		(isset) if set `crop` parameter become false - `crop=false`.

	* **crop**
		(isset) Control image cropping. By default always `true`.

		To disable cropping set here `false/0/no/none` or set parameter `'notcrop'`. Then image will not be cropped and will be created as small copy of original image by sizes settings of one side: width or height - here plugin select the smallest suitable side. So one side will be as it set in `w` or `h` and another side will be smaller then `w` or `h`.

		**Cropping position**

		Also, you can specify string: `'top'`, `'bottom'`, `'left'`, `'right'` or `'center'` and any other combinations of this strings glued with `/`. Ex: `'right/bottom'`. All this will set cropping area:

		- `'left', 'right'` - horizontal side (w)
		- `'top', 'bottom'` - vertical side (h)
		- `'center'` - for both sides (w and h)

		When only one value is set, the other will be by default. By default: `'center/center'`.

		Examples:

		~~~
		// image will be reduced by height, and width will be cropped.
		// "right" means that right side of image will be shown and left side will be cut.
		kama_thumb_img('w=200 &h=400 &crop=right');

		// image will be redused by width, and height will be cropped.
		// "top" means that the top of the image will be shown and bottom side will be cut.
		kama_thumb_img('w=400 &h=200 &crop=top');

		// you can specify two side position at once, order doesn't matter
		kama_thumb_img('w=400 &h=200 &crop=top/right');
		~~~

		**Reduce image by specified side**

		In order to get not cropped proportionally rediced image by specified side: by width or height. You need specify only width or only height, then other side will be reduced proportional. And no cropping will appear here.

		~~~
		kama_thumb_img('w=200');
		~~~

		So, width of our image will be 200, and height will be as it will...
		Теперь ширина всегда будет 200, а высота какая получится... And the picture will be always full, without cropping.


	* **q | quality**
		(int) jpg compression quality (Default 85. max.100)

	* **stub_url**
		(string) URL to no_photo image.

	* **alt**
		(str) alt attr of img tag.

	* **title**
		(str) title attr of img tag.

	* **class**
		(str) class attr of img tag.

	* **style**
		(str) style attr of img tag.

	* **attr**
		(str) Allow to pass any attributes in IMG tag. String passes in IMG tag as it is, without escaping.

	* **a_class**
		(str) class attr of A tag.

	* **a_style**
		(str) style attr of A tag.

	* **a_attr**
		(str) Allow to pass any attributes in A tag. String passes in A tag as it is, without escaping.

	* **no_stub**
		(isset) don't show picture stub if there is no picture. Return empty string.

	* **yes_stub**
		(isset) show picture stub if global option in option disable stub showing, but we need it...

	* **post_id | post**
		(int|WP_Post) post ID. It needs when use function not from the loop. If pass the parameter plugin will exactly knows which post to process. Parametr 'post' added in ver 2.1.

	* **attach_id**
		(int) ID of wordpress attachment image. Also, you can set this parametr by pass attachment ID to '$src' parament - second parametr of plugin functions: `kama_thumb_img('h=200', 250)` or `kama_thumb_img('h=200 &attach_id=250')`

	* **allow**
		(str) Which hosts are allowed. This option sets globally in plugin setting, but if you need allow hosts only for the function call, specify allow hosts here. Set 'any' to allow to make thumbs from any site (host).


* **$src**
	(string) URL to any image. In this case plugin will not parse URL from post thumbnail/content/attachments.

	If parameters passes as array second argument `$src` can be passed in this array, with key: `src` или `url` или `link` или `img`:

	`
	echo kama_thumb_img( array(
		'src' => 'http://yousite.com/IMAGE_URL.jpg',
		'w' => 150,
		'h' => 100,
	) );
	`



### Notes ###

1. You can pass `$args` as string or array:

	`
	// string
	kama_thumb_img('w=200 &h=100 &alt=IMG NAME &class=aligncenter', 'IMG_URL');

	// array
	kama_thumb_img( array(
		'width'  => 200,
		'height' => 150,
		'class'  => 'alignleft'
		'src'    => ''
	) );
	`

2. You can set only one side: `width` | `height`, then other side became proportional.
3. `src` parameter or second function argument is for cases when you need create thumb from any image not image of WordPress post.
4. For test is there image for post, use this code:

	`
	if( ! kama_thumb_img('w=150&h=150&no_stub') )
		echo 'NO img';
	`


### Examples ###

#### #1 Get Thumb ####

In the loop where you need the thumb 150х100:

`
<?php echo kama_thumb_img('w=150 &h=100 &class=alignleft myimg'); ?>
`
Result:
`
<img src='thumbnail_URL' alt='' class='alignleft myimg' width='150' height='100'>
`

#### #2 Not show stub image ####
`
<?php echo kama_thumb_img('w=150 &h=100 &no_stub'); ?>
`

#### #3 Get just thumb URL ####
`
<?php echo kama_thumb_src('w=100&h=80'); ?>
`
Result: `/wp-content/cache/thumb/ec799941f_100x80.png`

This url you can use like:
`
<img src='<?php echo kama_thumb_src('w=100 &h=80 &q=75'); ?>' alt=''>
`

#### #4 `kama_thumb_a_img()` function ####
`
<?php echo kama_thumb_a_img('w=150 &h=100 &class=alignleft myimg &q=75'); ?>
`
Result:
`
<a href='ORIGINAL_URL'><img src='thumbnail_URL' alt='' class='alignleft myimg' width='150' height='100'></a>
`

#### #5 Thumb of any image URL ####
Pass arguments as array:
`
<?php
	echo kama_thumb_img( array(
		'src' => 'http://yousite.com/IMAGE_URL.jpg',
		'w' => 150,
		'h' => 100,
	) );
?>
`

Pass arguments as string:
`
<?php
	echo kama_thumb_img('w=150 &h=200 ', 'http://yousite.com/IMAGE_URL.jpg');
?>
`
When parameters passes as string and "src" parameter has additional query args ("src=$src &w=200" where $src = http://site.com/img.jpg?foo&foo2=foo3) it might be confuse. That's why "src" parameter must passes as second function argument, when parameters passes as string (not array).


#### #6 Parameter post_id ####

Get thumb of post ID=50:

`
<?php echo kama_thumb_img("w=150 &h=100 &post_id=50"); ?>
`

### I don't need plugin ###
This plugin can be easily used not as a plugin, but as a simple php file.

If you are themes developer, and need all it functionality, but you need to install the plugin as the part of your theme, this short instruction for you:

1. Create folder in your theme, let it be 'thumbmaker' - it is for convenience.
2. Download the plugin and copy the files: `class.Kama_Make_Thumb.php` and `no_photo.jpg` to the folder you just create.
3. Include `class.Kama_Make_Thumb.php` file into theme `functions.php`, like this:
`require 'thumbmaker/class.Kama_Make_Thumb.php';`
4. Bingo! Use functions: `kama_thumb_*()` in your theme code.
5. If necessary, open `class.Kama_Make_Thumb.php` and edit options (at the top of the file): cache folder URL/PATH, custom field name etc.

* Conditions of Use - mention of this plugin in describing of your theme.



== Screenshots ==

1. Setting block on standart "Media" admin page.


== Installation ==

### Instalation via Admin Panel ###
1. Go to `Plugins > Add New > Search Plugins` enter "Thumbnail"
2. Find the plugin in search results and install it.


### Instalation via FTP ###
1. Download the `.zip` archive
2. Open `/wp-content/plugins/` directory
3. Put `thumbnail` folder from archive into opened `plugins` folder
4. Activate the `Thumbnail` in Admin plugins page
5. Go to `Settings > Media` page to customize plugin


==== TODO ====


== Changelog ==

= 3.4 =
* NEW: Parameter `force_format`.
* NEW: Force GD lib if Imagick fails thumb creation.