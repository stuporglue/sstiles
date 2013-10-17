<?php
/*
 * This script assumes that the input image is supposed to be square. 
 *
 * If it is not square, the tiles are stretched as though it had been square.
 *
 * Note that this is not the same as projection and points placed on a map at 
 * correct long/lat may not appear to be placed correctly on such a base map.
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

$t = new sstiles($path,$zoom,$x,$y,'scale','./cache');
$t->sendTile();
