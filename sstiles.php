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

        var $maxTiles = Array(
            '0' => 1,
            '1' => 2,
            '2' => 4,
            '3' => 8,
            '4' => 16,
            '5' => 32,
            '6' => 64,
            '7' => 128,
            '8' => 256,
            '9' => 512,
            '10' => 1024,
            '11' => 2048,
            '12' => 4096,
            '13' => 8192,
            '14' => 16384,
            '15' => 32768,
            '16' => 65536,
            '17' => 131072,
            '18' => 262144,
            '19' => 524288,
        );

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
    }

    /**
     * Send a single tile png to the browser
     */
    function sendTile(){
        if(!file_exists($this->mapfile)){
            error_log("Source file for tiles not found");
            header("HTTP/1.0 404 Not Found");
        }

        $this->prepCache();

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
            @header ("Connection: close");
            return TRUE;
        }

        @header("HTTP/1.0 404 Not Found");
    }

    /**
     * Set the cacheFile and fileCacheDir variables
     *
     * Create the cachedir if possible
     */
    protected function prepCache(){
        if($this->cachedir === FALSE){
            $this->cacheFile = FALSE;
        }else{
            $this->cacheFile = implode('/',Array(
                $this->cachedir,
                preg_replace('/[^a-zA-Z0-9\.]+/','-',trim($this->mapfile,"./\\")),
                $this->zoom,
                $this->x,
                $this->y
            ));
        }

        // This cachedir is for the specific tile, not for caching in general
        if($this->cachedir !== FALSE){
            $this->fileCacheDir = dirname($this->cacheFile);
            @mkdir($this->fileCacheDir,0777,TRUE);
        }
    }

    /**
     * Print http headers that will induce caching
     */
    protected function printHeaders(){
        if(headers_sent()){
            // too late!
            return;
        }

        $expires = 1209600; // 60*60*24*14 -- Two weeks caching!

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
     * Find the pixels to use for the square!
     */
    protected function findMapSquare($width,$height){

        if(isset($this->maxTiles[$this->zoom])){
            $maxTiles = $this->maxTiles[$this->zoom];
        }else{
            $maxTiles = pow(2,$this->zoom);
        }

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
            $width = $height = ($width > $height ? $width : $height);
            $tileHeight = $tileWidth = $width / $maxTiles;
        }

        return Array(
            'sx' => (int)($width / $maxTiles * $this->x),  // starting x
            'sy' => (int)($height / $maxTiles * $this->y), // starting y
            'tw' => (int)($tileWidth),
            'th' => (int)($tileHeight)
        );
    }

    /**
     * Make a cache tile and send it to the user
     *
     * @return FALSE on failure, 501 error if no supported resize method detected, sends image and exits on success
     */
    protected function makeCache(){
        /*
         * 1) Identify and get image shape
         * 2) Calculate crop area
         * 3) Crop area
         * 4) Scale cropped area to 256x256
         * 5) @write file
         * 6) Send created image
         */


        if(extension_loaded('gmagick')){
            return $this->makeCacheGM();
            exit();
        }else if(extension_loaded("magickwand")) {
            return $this->makeCacheMagickWand();
            exit();
        }else if(extension_loaded("imagick")) {
            return $this->makeCacheIM();
            exit();
        }else if(`which convert` != ''){
            return $this->makeCacheIMExec();
            exit();
        }else if(extension_loaded('gd')){
            return $this->makeCacheGD();
            exit();
        }else{
            header("HTTP/1.0 501 Not Implemented");
            error_log("No supported image resize method detected!");
            exit();
        }
    }

    /**
     * Make tiles with imagemagick
     *
     * http://php.net/manual/en/book.imagick.php
     *
     * You might run into this: http://www.euperia.com/development/php/php-imagick-imagickexception-no-decode-delegate-for-postscript-or-pdf-files/1051
     *
     * You can try removing imagick and doing
     * pecl install imagick-beta
     */
    protected function makeCacheIM(){
        // Read in the image with ImageMagick
        $image = new Imagick($this->mapfile);
        // If your image has an offset you might want to uncomment this
        // Better yet, fix your image.
        // Symptoms: An edge of your image is clipped from the map
        // $image->setImagePage(0,0,0,0);

        // Determine the dimensions
        $ident = $image->getImageGeometry();

        // Determine the start pixel and dimensions of our tile
        $cropDim = $this->findMapSquare($ident['width'],$ident['height']);

        // Handle tiles which are off the map
        if(
            ($cropDim['sx'] + $cropDim['tw']) > $ident['width'] ||
            ($cropDim['sy'] + $cropDim['th']) > $ident['height']
        ){

            // We're going to make a transparent tile, 
            // then crop out the part of the source image we want
            // then resize the cropped piece
            // then compose the map piece over the transparent tile

            // Transparent tile
            $pad = new Imagick();
            $pad->newImage(256,256,'none','png');

            if(
                $cropDim['sx'] > $ident['width'] || 
                $cropDim['sy'] > $ident['height']
            ){
                // we're completely off the map. Just use the transparent tile
                $image = $pad;
            }else{

                // Crop it 
                $chunkWidth = (($ident['width'] - $cropDim['sx']) < $cropDim['tw'] ? ($ident['width'] - $cropDim['sx']) : $cropDim['tw']);
                $chunkHeight = (($ident['height'] - $cropDim['sy']) < $cropDim['th'] ? ($ident['height'] - $cropDim['sy']) : $cropDim['th']);
                $image->cropImage($chunkWidth,$chunkHeight,$cropDim['sx'],$cropDim['sy']);
                $image->setImagePage(0,0,0,0);

                // Stretch it
                $resizeWidth = (int)($chunkWidth / $cropDim['tw'] * 256);
                $resizeHeight = (int)($chunkHeight / $cropDim['th'] * 256);
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
            $image->resizeImage(256,256,imagick::FILTER_POINT,0.5,FALSE);
        }

        // Should we cache it? 
        $image->setImageFormat('png');
        if($this->cacheFile !== FALSE){
            try {
                @$image->writeImage($this->cacheFile);
                $this->printHeaders();
                readfile($this->cacheFile);
                return TRUE;
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
     * Make tiles with graphicsmagick 
     *
     * http://php.net/manual/en/book.gmagick.php
     */
    protected function makeCacheGM(){
        // Read in the image with ImageMagick
        $image = new Gmagick($this->mapfile);

        // Determine the dimensions
        $srcwidth = $image->getimagewidth();
        $srcheight = $image->getimageheight();

        // Determine the start pixel and dimensions of our tile
        $cropDim = $this->findMapSquare($srcwidth,$srcheight);

        // Handle tiles which are off the map
        if(
            ($cropDim['sx'] + $cropDim['tw']) > $srcwidth ||
            ($cropDim['sy'] + $cropDim['th']) > $srcheight
        ){

            // We're going to make a transparent tile, 
            // then crop out the part of the source image we want
            // then resize the cropped piece
            // then compose the map piece over the transparent tile

            // Transparent tile
            $pad = new Gmagick();
            $pad->newimage(256,256,'none','png');

            if(
                $cropDim['sx'] > $srcwidth || 
                $cropDim['sy'] > $srcheight
            ){
                // we're completely off the map. Just use the transparent tile
                $image = $pad;
            }else{

                // Crop it 
                $chunkWidth = (($ident['width'] - $cropDim['sx']) < $cropDim['tw'] ? ($ident['width'] - $cropDim['sx']) : $cropDim['tw']);
                $chunkHeight = (($ident['height'] - $cropDim['sy']) < $cropDim['th'] ? ($ident['height'] - $cropDim['sy']) : $cropDim['th']);
                $image->cropimage($chunkWidth,$chunkHeight,$cropDim['sx'],$cropDim['sy']);
                $image->setimageformat('png');

                // Stretch it
                $resizeWidth = (int)($chunkWidth / $cropDim['tw'] * 256);
                $resizeHeight = (int)($chunkHeight / $cropDim['th'] * 256);
                $image->resizeimage($resizeWidth,$resizeHeight,Gmagick::FILTER_POINT,0.5,FALSE);

                // Compose it
                $pad->compositeimage($image,Gmagick::COMPOSITE_DEFAULT, 0, 0);

                // Replace it
                $image = $pad;
            }
        } else {
            // Standard on-map tiles
            $image->cropimage($cropDim['tw'],$cropDim['th'],$cropDim['sx'],$cropDim['sy']);
            $image->setimageformat('png');
            $image->resizeimage(256,256,Gmagick::FILTER_POINT,0.5,FALSE);
        }

        // Should we cache it? 
        if($this->cacheFile !== FALSE){
            try {
                @$image->writeimage($this->cacheFile);
                $this->printHeaders();
                readfile($this->cacheFile);
                return TRUE;
            }catch (Exception $e){
                error_log($e->getMessage());
            }
        }

        // Send the image from our variable instead of reading the file we just (maybe) wrote
        $this->printHeaders();
        print $image->getimageblob();
        return TRUE;
    }

    /**
     * Make tiles with the convert binary (ImageMagick binary)
     *
     * http://www.imagemagick.org/script/convert.php
     */
    protected function makeCacheIMExec(){
        // Array($width,$height)
        // If you've got convert, but not identify, that's going to be a problem
        $identcmd = "identify -format '%w,%h' -ping " . escapeshellarg($this->mapfile);
        $ident = explode(',',`$identcmd`);
        $cropDim = $this->findMapSquare($ident[0],$ident[1]);

        // For the command line ImageMagick we construct the command, except for
        // the output. If caching is enabled we write to a file, otherwise we 
        // have to just send it to the browser

        // Handle tiles which are off the map
        if(
            ($cropDim['sx'] + $cropDim['tw']) > $ident[0] ||
            ($cropDim['sy'] + $cropDim['th']) > $ident[1]
        ){
            if(
                $cropDim['sx'] > $ident[0] || 
                $cropDim['sy'] > $ident[1]
            ){
                // we're completely off the map. Just use the transparent tile
                $tilecmd = "convert -size 256x256 xc:#12312300 png:-";
            }else{
                $chunkWidth = (($ident['width'] - $cropDim['sx']) < $cropDim['tw'] ? ($ident['width'] - $cropDim['sx']) : $cropDim['tw']);
                $chunkHeight = (($ident['height'] - $cropDim['sy']) < $cropDim['th'] ? ($ident['height'] - $cropDim['sy']) : $cropDim['th']);
                $resizeWidth = (int)($chunkWidth / $cropDim['tw'] * 256);
                $resizeHeight = (int)($chunkHeight / $cropDim['th'] * 256);

                // crop, repage, resize, pad
                $tilecmd = "convert " . escapeshellarg($this->mapfile) . " -crop {$chunkWidth}x{$chunkHeight}+{$cropDim['sx']}+{$cropDim['sy']} +repage -resize {$resizeWidth}x{$resizeHeight} -background '#12121200' -gravity northwest -extent 256x256 png:-";
            }
        }else{
            // crop, scale
            $tilecmd = "convert " . escapeshellarg($this->mapfile) . " -crop {$cropDim['tw']}x{$cropDim['th']}+{$cropDim['sx']}+{$cropDim['sy']} +repage -resize 256x256! -filter Point png:-";
        }

        // Should we cache it? 
        if($this->cacheFile !== FALSE){

            // using the command line we're going to do a little trick. 
            // If we need to write the file, we'll write it with the shell command
            // but we'll use tee so that we also have the image returned. 
            // Since we'll use the passthru function instead of shell_exec
            // we won't actually have to load the image into php's memory
            $tilecmd .=  " | tee {$this->cacheFile}";
        }

        $this->printHeaders();
        try {
            @passthru($tilecmd);
        }catch (Exception $e){
            error_log($e->getMessage());
        }

        return TRUE;
    }

    /**
     * Make tiles with GD
     *
     * http://php.net/manual/en/book.image.php
     */
    protected function makeCacheGD(){
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
            header("HTTP/1.0 501 Not Implemented");
            error_log("GD doesn't support {$ident['mime']} format. Please install imagemagick!");
            exit();
        }

        // Determine the start pixel and dimensions of our tile
        $cropDim = $this->findMapSquare($ident[0],$ident[1]);

        // Transparent tile. This seems like a lot of code for a transparent square.
        $pad = imagecreatetruecolor(256,256);
        imagealphablending($pad, false);
        $col = imagecolorallocatealpha($image,255,255,255,127);
        imagefilledrectangle($pad,0,0,256,256,$col);
        imagealphablending($pad,true);
        imagesavealpha($pad,true);

        // Handle tiles which are off the map
        if(
            ($cropDim['sx'] + $cropDim['tw']) > $ident[0] ||
            ($cropDim['sy'] + $cropDim['th']) > $ident[1]
        ){

            // We're going to make a transparent tile, 
            // then crop out the part of the source image we want
            // then resize the cropped piece
            // then compose the map piece over the transparent tile
            if(
                $cropDim['sx'] > $ident[0] || 
                $cropDim['sy'] > $ident[1]
            ){
                // we're completely off the map. Just use the transparent tile
                $image = $pad;
            }else{
                $chunkWidth = (($ident['width'] - $cropDim['sx']) < $cropDim['tw'] ? ($ident['width'] - $cropDim['sx']) : $cropDim['tw']);
                $chunkHeight = (($ident['height'] - $cropDim['sy']) < $cropDim['th'] ? ($ident['height'] - $cropDim['sy']) : $cropDim['th']);
                $resizeWidth = (int)($chunkWidth / $cropDim['tw'] * 256);
                $resizeHeight = (int)($chunkHeight / $cropDim['th'] * 256);

                // Crop, stretch and compose all at once
                imagecopyresized($pad,$image,0,0,$cropDim['sx'],$cropDim['sy'],$resizeWidth,$resizeHeight,$chunkWidth,$chunkHeight);
                $image = $pad;
            }
        }else{
            imagecopyresized($pad,$image,0,0,$cropDim['sx'],$cropDim['sy'],256,256,$cropDim['tw'],$cropDim['th']);
            $image = $pad;
        }

        if($this->cacheFile !== FALSE){
            try {
                // imagepng is super slow. We're going to print the image here 
                // with readfile instead of running imagepng twice
                @imagepng($image,$this->cacheFile);
                $this->printHeaders();
                readfile($this->cacheFile);
                return TRUE;
            }catch (Exception $e){
                error_log($e->getMessage());
            }
        }

        $this->printHeaders();
        imagepng($image);
        return TRUE;
    }

    /**
     * Make tiles with ImageMagick, using the magickwand php extension
     *
     * http://www.imagemagick.org/api/magick-image.php#MagickResizeImage
     */
    protected function makeCacheMagickWand(){

        $image = NewMagickWand();
        MagickReadImage($image,$this->mapfile);

        $srcwidth = MagickGetImageWidth($image);
        $srcheight = MagickGetImageHeight($image);

        $cropDim = $this->findMapSquare($srcwidth,$srcheight);

        if(
            ($cropDim['sx'] + $cropDim['tw']) > $srcwidth ||
            ($cropDim['sy'] + $cropDim['th']) > $srcheight
        ){

            // We're going to make a transparent tile, 
            // then crop out the part of the source image we want
            // then resize the cropped piece
            // then compose the map piece over the transparent tile

            // Transparent tile
            $pad = NewMagickWand();
            MagickNewImage($pad,256,256,'none');

            if(
                $cropDim['sx'] > $srcwidth || 
                $cropDim['sy'] > $srcheight
            ){
                // we're completely off the map. Just use the transparent tile
                $image = $pad;
            }else{

                // Crop it 
                $chunkWidth = (($ident['width'] - $cropDim['sx']) < $cropDim['tw'] ? ($ident['width'] - $cropDim['sx']) : $cropDim['tw']);
                $chunkHeight = (($ident['height'] - $cropDim['sy']) < $cropDim['th'] ? ($ident['height'] - $cropDim['sy']) : $cropDim['th']);
                MagickCropImage($image,$chunkWidth,$chunkHeight,$cropDim['sx'],$cropDim['sy']);

                // Stretch it
                $resizeWidth = (int)($chunkWidth / $cropDim['tw'] * 256);
                $resizeHeight = (int)($chunkHeight / $cropDim['th'] * 256);
                MagickResizeImage($image,$resizeWidth,$resizeHeight,MW_PointFilter,0.5);

                // Compose it
                MagickCompositeImage($pad,$image,MW_OverCompositeOp,0,0);

                // Replace it
                $image = $pad;
            }
        } else {
            // Standard on-map tiles
            MagickCropImage($image,$cropDim['tw'],$cropDim['th'],$cropDim['sx'],$cropDim['sy']);
            MagickResizeImage($image,256,256,MW_PointFilter,0.5);
        }


        if($this->cacheFile !== FALSE){
            try {
                @MagickWriteImage($image,$this->cacheFile);
                $this->printHeaders();
                readfile($this->cacheFile);
                return TRUE;
            }catch (Exception $e){
                error_log($e->getMessage());
            }
        }

        $this->printHeaders();
        print MagickEchoImageBlob($image);
        return TRUE;
    }
}
