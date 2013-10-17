Stupid Simple Tile Maker (SSTiles)
=======

Stupid Simple Tile Maker is a simple way to generate tiles for webmaps that use 
[XYZ slippy tiles](http://wiki.openstreetmap.org/wiki/Slippy_map_tilenames). Such 
maps include [Leaflet](http://leafletjs.com/) and [OpenLayers](http://openlayers.org/).

You can also use SSTiles and said mapping software to display very large images. 

Instructions
------------

Place sstiles.php, tile.php (or pad.php) in a directory on your web server with 
the image you wish to use as a basemap.

 * sstiles.php
  - The tiling library itself
 * tile.php 
  - Makes tiles, stretching the image if it is not square
 * pad.php
  - Makes tiles, padding the image if it is not square


Add an XYZ layer to your map with the following URL, replacing $Z, $X and $Y 
with the syntax for your map software.

    http://example.com/mysite/sstile/tile.php/basemap.jpg/$Z/$X/$Y.png


A Leaflet example:

    L.tileLayer('http://example.com/mysite/sstile/tile.php/basemap.jpg/{z}/{x}/{y}.png').addTo(map);


An OpenLayers example: 

    var basemap = new OpenLayers.Layer.XYZ("Basemap", "http://example.com/mysite/sstile/tile.php/basemap.jpg/${z}/${x}/${y}.png");
    map.addLayer(basemap);


Notes
-----
 * Initial map loading will be SLOW because it's generating tiles
  - Subsequent loads will be faster since the tiles are already made
 * Yes, you just keep adding slashes after the tile.php. Your server must support PATH_INFO
 * Yes, the file extention should be .png at the end. All generated tiles are saved as pngs
 * Tiles can take up a LOT of room. Limit your map zoom level to limit how many tile levels get created
 * SSTiles is as dump as a bag of hammers. It has to load the image for each tile it creates. 
  - In the normal case this is OK, since each request is for a single tile. If you want to generate tiles ahead of time there are better tools


Features
--------

 * Lets you use any image as a slippy map!
 * Generates slippy-map tiles from a single top-level image!
 * Stretches the image if it's not square!
    * *OR* Pads the image if it's not square!
 * Caches generated tiles!
 * Auto-updates cache when source file is updated!
 * Sends HTTP caching headers! 
 * Supports GD and ImageMagick!


Source Maps and Tile Types
--------------------------

For best accuracy, your source map should be a propperly world map in the 
EPSG:3785 (Web Mercator) projection. The resulting map is square, and SSTiles will 
never distort a square source map.

[DEMO](http://moorespatial.com/sstiles/demo/square.html)

![A square map will never be stretched](https://raw.github.com/stuporglue/sstiles/master/img/square.png)

If you use a non-square source map, and use the tiles.php to generate tiles, the
source map will be stretched to be square. This means that each square will be 
distorted when compared to your source map. Making a source map this way will 
work, but is not geographically accurate. If you place markers or polygons on the 
map at specific coordinates they will likely not line up with features on the base map.

Still, if this is what you want SSTiles is here for you. 

[DEMO](http://moorespatial.com/sstiles/demo/nonsquare.html)

![For normal tile making, a non-square map will be stretched so that its tiles are square](https://raw.github.com/stuporglue/sstiles/master/img/nonsquare.png)


If you have a non-square source image which you want to present without distorting, 
you can use the pad.php script to generate tiles. It will pad the source image 
without distorting it. The source image will be anchored at the top left of the 
map and padded on the right and bottom as needed.

[DEMO](http://moorespatial.com/sstiles/demo/document.html)

![For padded tile making, a non-square map will be padded so that the tiles are square](https://raw.github.com/stuporglue/sstiles/master/img/padded.png)

More Details
------------

For accurate maps you will want to use or project your base map into the EPSG:3785 
projection (AKA 900913). The resulting image should be a square with a map of the world.

See [Slippy Map Tilenames](http://wiki.openstreetmap.org/wiki/Slippy_map_tilenames) for more information.

Demo
----
An online demo can bee found here: http://moorespatial.com/sstiles/demo/

Initial visitors will find it very slow as it's cahe isn't built up yet, and it's on shared hosting.

Subsequent visitors should find it loading at acceptable speeds.

TODO
----

 * Implement convert support
 * Implement MagickWand support
