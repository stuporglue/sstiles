<?php
/*
 * This script assumes that the input image is already in its correct proportions 
 * and pads with transparency to make it square.
 *
 * The image expands from the top left corner (0,0).
 *
 * This can be used to easily use a slippy map to serve up non map imagery such 
 * as high resolution photos or scanned documents.
 *
 * Use the URL like this:
 * http://example.com/tiles.php/path/to/image_name.png/{zoom}/{x}/{y}.png
 */

if(!isset($_SERVER['PATH_INFO'])){
    header("HTTP/1.0 404 Not Found");
    print "Please specify a tile path";
    exit();
}

require_once('sstiles.php');

$parts = explode('/',$_SERVER['PATH_INFO']);

$y = array_pop($parts);
$x = array_pop($parts);
$zoom = array_pop($parts);
$path = '.' . implode('/',$parts);

$t = new sstiles($path,$zoom,$x,$y,'pad','./cache');
$t->sendTile();
