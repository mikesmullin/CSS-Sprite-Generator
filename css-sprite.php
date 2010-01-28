#!/usr/bin/php-cgi -q
<?php

/**
 * CSS Sprite Generator
 *
 * Combine any number of images in a given directory into a select few composite 
 * CSS Sprite images. (e.g. one for horizontally-repeating, one for vertically-
 * repeating, and one for images that do not repeat like buttons.) Note that 
 * images which repeat on both the X and Y axis, like tiled backgrounds, must be
 * contained within their own image and cannot be used in sprites.
 * 
 * Requires the GD image library PECL extension for PHP to be installed.
 * Requires PHP-CGI to be installed.
 *
 * PHP versions 4 and 5
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @category   Tools
 * @package    SmullinDesign
 * @author     Mike Smullin <mike@smullindesign.com>
 * @copyright  Copyright 2006-2010, Smullin Design and Development, LLC (http://www.smullindesign.com) 
 * @license    http://www.opensource.org/licenses/mit-license.php The MIT License
 * @version    SVN: $Id$
 * @link       http://www.mikesmullin.com/
 */

if (isset($_GET['--help']) || isset($_GET['-h']) || isset($_GET['-?']) || isset($_GET['/h'])) {
  echo <<<HELP
CSS Sprite Generator
Usage: css-sprite.php [OPTION]... [PATH]

  --path <path>     Path where sliced images reside. Default is current working 
                    directory where the program is launched.
  --prefix <prefix> Optional prefix used in output filenames for composite 
                    images. Default is "sprites". 
  --matte <rgb>     RGB color matte. Default is "255,255,255" which is white.
                    For PNG 24-bit transparency, use "transparent".

Naming files:

  The suffix of your sliced image filenames determines how they are composited:
  
  -x = horizontally repeating (repeat-x) 
  -y = vertically repeating (repeat-y)
  -n = not repeating (no-repeat)
  
  Filename examples:
   
    border1-bottomleftcorner-n.png
    border1-bottommiddle-x.png
    border1-middleright-y.png

  You can also modify the files as they are composited.
  
  -pr<pixels> = add padding to the right of the image 
  -pl<pixels> = add padding to the left of the image
  
  Filename examples:
    
    border1-middleleft-y-pr300.png
    border1-middleright-y-pl300.png

Report bugs to: mike@smullindesign.com


HELP;
  exit(1);
}

error_reporting(E_ALL); // helpful
ini_set('memory_limit', '1024M'); // needs enough memory to combine all the images into one
$path =& $_GET['--path']? $_GET['--path'] : getcwd();
$prefix =& $_GET['--prefix']? $_GET['--prefix'] : 'sprites';
$matte =& $_GET['--matte']? $_GET['--matte'] : 'transparent';

/**
 * Order of Operations:
 * 
 * run through each image and get its dimensions
 * use this + filename to calculate the final position and total size of the new sprite image.
 * load each image one-by-one and add it in its place in the final image
 * store the final image as PNG w/ 24-bit  transparency
 *   image quality settings should be set to 100% for jpg, but it probably will not matter
 * 
 * store one image output that includes n + y (since right now it is mostly wider than taller)
 * and then a separate image output that includes y.
 *   unless i want to specify very far-off x starting point for the y images
 *     @TODO: i wonder which would be more efficient?
 *     
 * @TODO: when tiling -x graphics, what if they are not the same widths? or what if one is odd width vs. even? how does it tile to the end without cutting off the pattern?    
 */

// Open a known directory, and proceed to read its contents
// run through each image and get its dimensions
if (!is_dir($path)) {
  die("The path '{$path}' does not exist!\n");
} else if ($dh = opendir($path)) {
  $imgs = array('n' => array(), 'y' => array(), 'x' => array(), 'r' => array());
  while (($file = readdir($dh)) !== false) {
    $ext = strtolower(substr($file, strrpos($file, '.')+1));
    $basename = basename($file, '.'.$ext);
    if (in_array($ext, array('png','gif','jpg','jpeg')) && !in_array($file, array('sprites.png', 'sprites-x.png', 'sprites-y.png'))) {
      if (preg_match('/-([nxy])(-pl(\d+))?(-pr(\d+))?$/i', $basename, $matches)) {
        list($null, $type, $null, $padding_left, $null, $padding_right) = array_pad($matches, 6, 0);
        $imgs[$type][$file] = image_get_info($path .'/'. $file) + compact('padding_left', 'padding_right');
        unset($matches, $null, $type, $padding_left, $padding_right); // free memory
      }
    }
  }
  closedir($dh);
  unset($dh, $file, $ext, $basename); // free memory
}


// use img dimensions + filename to calculate the final position and total size of the new sprite image.
if (!count($imgs)) {
  die("No images found!\n");
}
// start with no-repeat images
// @TODO: build an algorithm to optimize this space by packing odd shaped images as tightly as possible into a corner; like Tetris.
$x = 0; $y = 0; $css = array('n' => '', 'y' => '', 'x' => '');
$css['n'] = '/* '. $prefix .'.png */'."\n";
foreach (array_keys((array) $imgs['n']) as $k) {
  $img =& $imgs['n'][$k];
  $x += (int) $img['padding_left'];
  $img['x'] = $x; $x += $img['width'] + (int) $img['padding_right'];
  $img['y'] = 0; $y = max($y, $img['height']);
  $css['n'] .= '.'. str_replace('.', '-', $k) .'  {'."\n".
  '  background:url('. $prefix .'.png) no-repeat '. px($img['x']*-1) .' '. px($img['y']*-1) .'; width:'. px($img['width']) .'; height:'. px($img['height']) .';'."\n".
  '}'."\n";
}
image_save($imgs['n'], $path, $prefix .'.png', $x, $y, 'n', $matte); // save sprites

$x = 0; $y = 0;
$css['y'] = "\n\n".'/* '. $prefix .'-y.png */'."\n";
foreach (array_keys((array) $imgs['y']) as $k) {
  $img =& $imgs['y'][$k];
  $x += (int) $img['padding_left'];
  $img['x'] = $x; $x += $img['width'] + (int) $img['padding_right'];
  $img['y'] = 0; $y = max($y, $img['height']);
  $css['y'] .= '.'. str_replace('.', '-', $k) .'  {'."\n".
  '  background:url('. $prefix .'-y.png) repeat-y '. px($img['x']*-1) .' '. px($img['y']*-1) .'; width:'. px($img['width']) .';'."\n".
  '}'."\n";
}
image_save($imgs['y'], $path, $prefix .'-y.png', $x, $y, 'y', $matte); // save sprites

$x = 0; $y = 0;
$css['x'] = "\n\n".'/* '. $prefix .'-x.png */'."\n";
foreach (array_keys((array) $imgs['x']) as $k) {
  $img =& $imgs['x'][$k];
  $img['x'] = 0; $x = max($x, $img['width']);
  $img['y'] = $y; $y += $img['height'];
  $css['x'] .= '.'. str_replace('.', '-', $k) .'  {'."\n".
  '  background:url('. $prefix .'-x.png) repeat-x '. px($img['x']*-1) .' '. px($img['y']*-1) .'; height:'. px($img['height']) .';'."\n".
  '}'."\n";
}
image_save($imgs['x'], $path, $prefix .'-x.png', $x, $y, 'x', $matte); // save sprites

file_put_contents($path .'/'. $prefix .'.css', implode('', $css)); // generate CSS and save to sprites.css

die("Done."); // end

function px($n) { return $n? ((int) $n) .'px' : 0; }

/**
 * Save a sprite image.
 *
 * @param Array $imgs
 *   Image list.
 * @param String $filename
 *   Sprite file name.
 */
function image_save($imgs, $path, $filename, $x, $y, $type, $matte = 'transparent') {
  if (!$x || !$y) return; // abort if image dimensions are invalid

  // create new [blank] image
  $im = imagecreatetruecolor($x, $y)
    or die("Cannot Initialize new GD image stream");

  if ($matte == 'transparent') {
    // apply PNG 24-bit transparency to background
    $transparency = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagealphablending($im, FALSE);
    imagefilledrectangle($im, 0, 0, $x, $y, $transparency);
    imagealphablending($im, TRUE);
    imagesavealpha($im, TRUE);
  } else {
    // apply solid color background
    list($r, $g, $b) = array_pad(explode(',', $matte), 3, 255);
    $color = imagecolorallocate($im, $r, $g, $b);
    imagefilledrectangle($im, 0, 0, $x, $y, $color);
  }

  // overlay all source image onto single destination sprite image
  foreach ($imgs as $file => $img) {
    if (isset($img['extension'], $img['x'], $img['y'], $img['width'], $img['height'])) {
      image_overlay(
        $im,
        $path.'/'.$file,
        $img['extension'],
        $type=='x'? 0 : $img['x'], // dst_x
        $type=='t'? 0 : $img['y'], // dst_y
        0, // src_x
        0, // src_y
        $type=='x'? $x : $img['width'], // dst_w
        $type=='y'? $y : $img['height'], // dst_h
        $img['width'], // src_w
        $img['height'] // src_h
      );
    }
  }

  // save sprite image prefix as PNG
  image_gd_close($im, $path.'/'.$filename, 'png');
  imagedestroy($im); // free memory
}

/**
 * Overlay a source image on a destination image at a given location.
 */
function image_overlay(&$dst_im, $src_path, $src_ext, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) {
  $src_im = image_gd_open($src_path, $src_ext); // load source image
  imagecopyresampled($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h); // overlay source image on destination image
  imagedestroy($src_im); // free memory
}

/**
 * Sanitize a string to be used as a CSS class.
 *
 * @param String $s
 *   Class name.
 * @return String
 *   CSS-safe class name.
 */
function css_safe_class($s) {
  return trim(strtolower(preg_replace('/[^a-z]+/i', '-', $s)), '-');
}

/**
 * Get details about an image.
 *
 * @return array containing information about the image
 *      'width': image's width in pixels
 *      'height': image's height in pixels
 *      'extension': commonly used extension for the image
 *      'mime_type': image's MIME type ('image/jpeg', 'image/gif', etc.)
 *      'file_size': image's physical size (in bytes)
 */
function image_get_info($file) {
  if (!is_file($file)) {
    return FALSE;
  }

  $details = FALSE;
  $data = @getimagesize($file);
  $file_size = @filesize($file);

  if (isset($data) && is_array($data)) {
    $extensions = array('1' => 'gif', '2' => 'jpg', '3' => 'png');
    $extension = array_key_exists($data[2], $extensions) ?  $extensions[$data[2]] : '';
    $details = array('width'     => $data[0],
                     'height'    => $data[1],
                     'extension' => $extension,
                     'file_size' => $file_size,
                     'mime_type' => $data['mime']);
  }

  return $details;
}

/**
 * GD helper function to create an image resource from a file.
 */
function image_gd_open($file, $extension) {
  $extension = str_replace('jpg', 'jpeg', $extension);
  $open_func = 'imageCreateFrom'. $extension;
  if (!function_exists($open_func)) {
    return FALSE;
  }
  return $open_func($file);
}


/**
 * GD helper to write an image resource to a destination file.
 */
function image_gd_close($res, $destination, $extension) {
  $extension = str_replace('jpg', 'jpeg', $extension);
  $close_func = 'image'. $extension;
  if (!function_exists($close_func)) {
    return FALSE;
  }
  if ($extension == 'jpeg') {
    return $close_func($res, $destination, 100);
  }
  else {
    return $close_func($res, $destination);
  }
}