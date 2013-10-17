Stupid Simple Tile Maker (SSTiles)
=======

Stupid Simple Tile Maker is a simple way to generate tiles for webmaps that use 
[XYZ slippy tiles](http://wiki.openstreetmap.org/wiki/Slippy_map_tilenames). Such 
maps include [Leaflet](http://leafletjs.com/) and [OpenLayers](http://openlayers.org/).

You can also use SSTiles and said mapping software to display very large images. 

Features
--------

 * Generates slippy-map tiles from a single top-level image!
 * Lets you use any image as a slippy map!
 * Stretches the image if it's not square!
    * *OR* Pads the image if it's not square!
 * Caches generated tiles!
 * Auto-updates cache when source file is updated!
 * Sends HTTP caching headers! 


Source Maps and Tile Types
--------------------------

For best accuracy, your source map should be a propperly world map in the 
EPSG:3785 (Web Mercator) projection. The resulting map is square, and SSTiles will 
never distort a square source map.

![A square map will never be stretched](https://raw.github.com/stuporglue/sstiles/master/img/square.png)

If you use a non-square source map, and use the tiles.php to generate tiles, the
source map will be stretched to be square. This means that each square will be 
distorted when compared to your source map. Making a source map this way will 
work, but is not geographically accurate. If you place markers or polygons on the 
map at specific coordinates they will likely not line up with the base map.

Still, if this is what you want SSTiles is here for you. 
![For normal tile making, a non-square map will be stretched so that its tiles are square](https://raw.github.com/stuporglue/sstiles/master/img/nonsquare.png)


If you have a non-square source image which you want to present without distorting, 
you can use the pad.php script to generate tiles. It will pad the source image 
without distorting it. The source image will be anchored at the top left of the 
map and padded on the right and bottom as needed.
![For padded tile making, a non-square map will be padded so that the tiles are square](https://raw.github.com/stuporglue/sstiles/master/img/padded.png)


Instructions
------------

Place sstiles.php, tile.php (or pad.php) in a directory on your web server with the image you wish to use as a basemap.

Let's suppose that the URL to tile.php is *http://example.com/mysite/sstile/tile.php* and that the image is named *basemap.jpg*.

You would add an XYZ layer to your map with the URL: 

    http://example.com/mysite/sstile/tile.php/basemap.jpg/{z}/{x}/{y}.png


### Notes

    * Yes, you just keep adding slashes after the tile.php. Your server must support PATH_INFO
    * Yes, the file extention should be .png at the end. All generated tiles are saved as pngs


More Details
------------

For accurate maps you will want to use or project your base map into the EPSG:3785 
projection (AKA 900913). The resulting image should be a square with a map of the world.

See [Slippy Map Tilenames](http://wiki.openstreetmap.org/wiki/Slippy_map_tilenames) for more information.
