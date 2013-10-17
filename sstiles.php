<?php
/**
 * Stupid Simple Tile Maker
 *
 * Generate tiles on demand using PHP's GD or ImageMagick libraries
 *
 * Tries to cache the tiles if possible, but will still work if it can't
 *
 * NOTES: 
 *
 * 1) This class has to load the entire source image for each tile
 * it creates. This means A) You will need to have enough memory
 * to serve it and B) You should enable caching.
 *
 * 2) Caching can take up a lot of room very quickly! If you 
 * have disk space or file count quotas, keep a close eye on it!
 * 
 *
 * Features: 
 *      Generates slippy-map tiles from a single top-level image. 
 *      Lets you use any image as a slippy map!
 *      Stretches the image if it's not square!
 *          OR
 *      Pads the image if it's not square!
 *      Caches generated tiles!
 *      Auto-updates cache when source file is updated!
 *      Sends HTTP caching headers! 
 */
class sstiles {

    /**
     * Get a single tile, creating it if needed
     *
     * @param $mapfile (required) The image file to use as the source
     * @param $zoom (required) The zoom level for the tile. Any zoom level is accepted
     * @param $x (required) The x location of the tile
     * @param $y (required) The y location of the tile
     * @param $padOrScale (optional, default is scale) If the image is not square, should we pad it, or scale it?
     * @param $cachedir (optional, default is ./cache) Where do we build the tile cache? If set to FALSE no cache will be used or created
     */
    function __construct($mapfile,$zoom,$x,$y,$padOrScale = 'scale', $cachedir = './cache'){
        $this->mapfile = $mapfile;
        $this->zoom = $zoom;
        $this->x = $x;
        $this->y = preg_replace('/([0-9]+).*/',"$1",$y);
        $this->cachedir = $cachedir;
        $this->padOrScale = $padOrScale;

        if($this->cachedir === FALSE){
            $this->cacheFile = FALSE;
        }else{
            $this->cacheFile = implode('/',Array(
                $this->cachedir,
                preg_replace('/[^a-zA-Z0-9\.]/','-',trim($mapfile,"./\\")),
                $zoom,
                $x,
                $y
            ));
        }
    }

    /**
     * Determine the ideal zoom (least lossy) for a given image
     */
    function idealZoom(){
        if(class_exists("Imagick")){
            $image = new Imagick($this->mapfile);
            $ident = $image->identifyImage();
            $min = min($ident['geometry']['width'],$ident['geometry']['height']);

            // I have litterally never needed a log function until now.
            return round(log($min/256,2));
        }
    }

    /**
     * Send a single tile png to the browser
     */
    function sendTile(){
        if(!file_exists($this->mapfile)){
            error_log("Source file for tiles not found");
            header("HTTP/1.0 404 Not Found");
        }

        if(
            $this->cachedir !== FALSE &&                            // Cache is enabled
            file_exists($this->cacheFile) &&                        // Cache exists
            filemtime($this->mapfile) < filemtime($this->cacheFile) // Cache is invalidated
        ){
            $this->printHeaders();
            readfile($this->cacheFile);
            exit();
        }

        // This cachedir is for the specific tile, not for caching in general
        if($this->cachedir !== FALSE){
            $this->fileCacheDir = dirname($this->cacheFile);
            @mkdir($this->fileCacheDir,0777,TRUE);
        }

        if($this->makeCache()){
            return TRUE;
        }

        header("HTTP/1.0 404 Not Found");
    }

    /**
     * Print http headers that will induce caching
     */
    private function printHeaders(){
        $expires = 60*60*24*14; // Two weeks caching!

        if($this->cacheFile === FALSE){
            header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
            header('Pragma: no-cache'); // HTTP 1.0.
            header('Expires: 0'); // Proxies.
        }else {
            if(file_exists($this->cacheFile)){
                header('Content-Length: ' . filesize($this->cacheFile));
                header("Etag: " . md5_file($this->cacheFile));
                header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime($this->cacheFile))." GMT");
            }else{
                header("Last-Modified: ".gmdate("D, d M Y H:i:s", time())." GMT");
            }

            header("Cache-Control: maxage=".$expires);
            header("Pragma: public");
            header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
        }

        header('Content-Type: image/png');
    }

    /**
     * Make a cache tile and send it to the user
     *
     * @return FALSE on failure, 501 error if no supported resize method detected, sends image and exits on success
     */
    private function makeCache(){
        /*
         * 1) Identify and get image shape
         * 2) Calculate crop area
         * 3) Crop area
         * 4) Scale cropped area to 256x256
         * 5) @write file
         * 6) Send created image
         */


        // GD seems to be faster, so that's the default
        // If GD is not installed, then we'll try ImageMagick
        //
        // NOTE: We also fall through to ImageMagick if the 
        // source map is a format that GD doesn't support
        if(extension_loaded('gd')){
            return $this->makeCacheGD();
            exit();
        }else if(class_exists("Imagick")) {
            return $this->makeCacheIM();
            exit();
        }else{
            header("HTTP/1.0 501 Not Implemented");
            error_log("No supported image resize method detected!");
            exit();
        }
    }


    /**
     * Find the pixels to use for the square!
     */
    private function findMapSquare($width,$height){
        $maxTiles = pow(2,$this->zoom);

        // Tiles are 0-indexed, so we can't have maxTiles or more tiles
        if($this->x >= $maxTiles || $this->y >= $maxTiles){
            error_log("Zoom level {$this->zoom} doesn't have tile {$this->x},{$this->y}");
            header("HTTP/1.0 404 Not Found");
            exit();
        }

        if($this->padOrScale == 'scale'){
            $tileWidth = $width / $maxTiles;
            $tileHeight = $height / $maxTiles;
        }else{
            // Max because we want to pretend it's as big as the biggest side
            $tileHeight = $tileWidth = max($width,$height) / $maxTiles;
            $width = $height = max($width,$height);
        }

        return Array(
            'sx' => floor($width / $maxTiles * $this->x),  // starting x
            'sy' => floor($height / $maxTiles * $this->y), // starting y
            'tw' => floor($tileWidth),
            'th' => floor($tileHeight)
        );
    }

    /**
     * Make tiles with imagemagick
     */
    private function makeCacheIM(){
        // Read in the image with ImageMagick
        $image = new Imagick($this->mapfile);
        // If your image has an offset you might want to uncomment this
        // Better yet, fix your image.
        // Symptoms: An edge of your image is clipped from the map
        // $image->setImagePage(0,0,0,0);

        // Determine the dimensions
        $ident = $image->identifyImage();

        // Determine the start pixel and dimensions of our tile
        $cropDim = $this->findMapSquare($ident['geometry']['width'],$ident['geometry']['height']);

        // Handle tiles which are off the map
        if(
            ($cropDim['sx'] + $cropDim['tw']) > $ident['geometry']['width'] ||
            ($cropDim['sy'] + $cropDim['th']) > $ident['geometry']['height']
        ){

            // We're going to make a transparent tile, 
            // then crop out the part of the source image we want
            // then resize the cropped piece
            // then compose the map piece over the transparent tile

            // Transparent tile
            $pad = new Imagick();
            $pad->newImage(256,256,'none','png');

            if(
                $cropDim['sx'] > $ident['geometry']['width'] || 
                $cropDim['sy'] > $ident['geometry']['height']
            ){
                // we're completely off the map. Just use the transparent tile
                $image = $pad;
            }else{

                // Crop it 
                $chunkWidth = min($ident['geometry']['width'] - $cropDim['sx'],$cropDim['tw']);
                $chunkHeight = min($ident['geometry']['height'] - $cropDim['sy'],$cropDim['th']);
                $image->cropImage($chunkWidth,$chunkHeight,$cropDim['sx'],$cropDim['sy']);
                $image->setImagePage(0,0,0,0);
                $image->setImageFormat('png');

                // Stretch it
                $resizeWidth = floor($chunkWidth / $cropDim['tw'] * 256);
                $resizeHeight = floor($chunkHeight / $cropDim['th'] * 256);
                $image->resizeImage($resizeWidth,$resizeHeight,imagick::FILTER_POINT,0.5,FALSE);

                // Compose it
                $pad->compositeImage($image,Imagick::COMPOSITE_DEFAULT, 0, 0);

                // Replace it
                $image = $pad;
            }
        } else {
            // Standard on-map tiles
            $image->cropImage($cropDim['tw'],$cropDim['th'],$cropDim['sx'],$cropDim['sy']);
            $image->setImagePage(0,0,0,0);
            $image->setImageFormat('png');
            $image->resizeImage(256,256,imagick::FILTER_POINT,0.5,FALSE);
        }

        // Should we cache it? 
        if($this->cacheFile !== FALSE){
            try {
                @$image->writeImage(__DIR__ . '/' . $this->cacheFile);
            }catch (Exception $e){
                error_log($e->getMessage());
            }
        }

        // Send the image from our variable instead of reading the file we just (maybe) wrote
        $this->printHeaders();
        print $image->getImageBlob();
        return TRUE;
    }

    /**
     * Make tiles with GD
     */
    private function makeCacheGD(){
        // Get image type and size
        // Width is $ident[0], Height is $ident[1]
        $ident = getimagesize($this->mapfile);

        // Read in the file
        switch($ident['mime']){
            case 'image/png':
                $image = imagecreatefrompng($this->mapfile);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($this->mapfile);
                break;
            case 'image/jpeg':
                $image = imagecreatefromjpeg($this->mapfile);
                break;
            default:
                if(class_exists('Imagick')){
                    return $this->makeCacheIM();
                }
        }

        // Determine the start pixel and dimensions of our tile
        $cropDim = $this->findMapSquare($ident[0],$ident[1]);



        // Handle tiles which are off the map
        if(
            ($cropDim['sx'] + $cropDim['tw']) > $ident['geometry']['width'] ||
            ($cropDim['sy'] + $cropDim['th']) > $ident['geometry']['height']
        ){

            // We're going to make a transparent tile, 
            // then crop out the part of the source image we want
            // then resize the cropped piece
            // then compose the map piece over the transparent tile

            // Transparent tile. This seems like a lot of code for a transparent square.
            $pad = imagecreatetruecolor(256,256);
            imagealphablending($pad, false);
            $col = imagecolorallocatealpha($image,255,255,255,127);
            imagefilledrectangle($pad,0,0,256,256,$col);
            imagealphablending($pad,true);
            imagesavealpha($pad,true);

            if(
                $cropDim['sx'] > $ident[0] || 
                $cropDim['sy'] > $ident[1]
            ){
                // we're completely off the map. Just use the transparent tile
                $image = $pad;
            }else{
                $chunkWidth = min($ident[0] - $cropDim['sx'],$cropDim['tw']);
                $chunkHeight = min($ident[1] - $cropDim['sy'],$cropDim['th']);
                $resizeWidth = floor($chunkWidth / $cropDim['tw'] * 256);
                $resizeHeight = floor($chunkHeight / $cropDim['th'] * 256);

                // Crop, stretch and compose all at once
                imagecopyresized($pad,$image,0,0,$cropDim['sx'],$cropDim['sy'],$resizeWidth,$resizeHeight,$chunkWidth,$chunkHeight);
                $image = $pad;
            }
        }else{
            imagecrop($image,Array($cropDim['sx'],$cropDim['sy'],$cropDim['tw'],$cropDim['th']));
            imagescale($image,256,256);
        }

        if($this->cacheFile !== FALSE){
            try {
                imagepng($image,__DIR__ . '/' . $this->cacheFile);
            }catch (Exception $e){
                error_log($e->getMessage());
            }
        }

        $this->printHeaders();
        imagepng($image);
        return TRUE;
    }
}
