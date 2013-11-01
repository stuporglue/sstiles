<?php

require_once('sstiles.php');

class ssutil extends sstiles{

    /**
     * Determine the ideal zoom (least lossy) for a given image
     */
    function idealZoom(){
        $min = FALSe;

        if(extension_loaded('gmagick')){
            $image = new Gmagick($this->mapfile);
            $min = min($image->getimagewidth,$image->getimageheight);
        }else if(extension_loaded("magickwand")) {
            $image = NewMagickWand();
            MagickReadImage($image,$this->mapfile);
            $min = min(MagickGetImageWidth($image),MagickGetImageHeight($image));
        }else if(extension_loaded("imagick")) {
            $image = new Imagick($this->mapfile);
            $ident = $image->identifyImage();
            $min = min($ident['geometry']['width'],$ident['geometry']['height']);
        }else if(`which convert` != ''){
            $identcmd = "convert " . escapeshellarg($this->mapfile) . " -format '%w,%h' info:";
            $ident = explode(',',`$identcmd`);
            $min = min($ident);
        }else if(extension_loaded('gd')){
            $min = min(getimagesize($this->mapfile));
        }


        // I have litterally never needed a log function until now.
        return round(log($min/256,2));
    }

    /**
     * benchmark each available method
     *
     * Make $this->benchmarkCount tiles with each available method. 
     *
     * Might be better to run this on the command line to avoid PHP timeouts
     *
     * Returns a hash with the method as the key and the time it took to run in seconds.
     */
    function benchmark($rounds = 1,$outputCSV = TRUE){
        $benchmarkstart = microtime(TRUE);

        // Try to remove the time limit
        @set_time_limit(0);

        // Figure out which methods we can test
        $benchmarkMethods = Array(
            'makeCacheMagickWand' => extension_loaded("magickwand"),
            'makeCacheGD' => extension_loaded('gd'),
            'makeCacheGM' => extension_loaded('gmagick'),
            'makeCacheIM' => extension_loaded("imagick"),
            'makeCacheIMExec' => (strlen(`which convert`) > 0),
        );

        // capture headers which have already been set
        $headers = headers_list();
        error_log("Starting tile benchmark with $rounds rounds for each enabled method");
        foreach($benchmarkMethods as $method => $results){

            if($results === FALSE){
                $benchmarkMethods[$method] = "Not available to test";
                continue;
            }

            $benchmarkMethods[$method] = $this->benchmarkMethod($method,$rounds,$outputCSV);
        }

        header_remove();
        foreach($headers as $header){
            header($header);
        }

        $benchmarkMethods['TOTAL TIME'] = (microtime(TRUE) - $benchmarkstart);
        return $benchmarkMethods;
    }

    function benchmarkMethod($method,$rounds,$outputCSV = TRUE){


        error_log("Testing $method");

        $tilesAtZoom = pow(2,$this->zoom);
        $tilesFound = 0;
        $starttime = microtime(TRUE);
        $maxtime = 0;
        $mintime = 999999999999999;

        while($tilesFound < $rounds){
            for($x = 0;$x<$tilesAtZoom;$x++){
                for($y = 0;$y<$tilesAtZoom;$y++){
                    $roundstart = microtime(TRUE);

                    if($tilesFound == $rounds){
                        break(3);
                    }

                    $this->x = $x;     
                    $this->y = $y;

                    $this->prepCache();

                    try {
                        $this->$method();
                    }catch (Exception $e){
                        error_log("Benchmark error caught: " . $e->getMessage());
                        break(3);
                    }

                    // discard image printed to browser

                    $tilesFound++;
                    $roundstop = microtime(TRUE);

                    $roundtime = $roundstop - $roundstart;

                    if($outputCSV){
                        error_log("$method,$roundtime");
                    }

                    if($roundtime > $maxtime){
                        $maxtime = $roundtime;
                    }

                    if($roundtime < $mintime){
                        $mintime = $roundtime;
                    }
                }
            }
        }

        $stoptime = microtime(TRUE);
        $runtime = $stoptime - $starttime;
        return Array(
            'iterations' => $tilesFound,
            'totaltime' => $runtime,
            'avgtime' => ($runtime / $tilesFound),
            'mintime' => $mintime,
            'maxtime' => $maxtime,
            'variation' => ($maxtime - $mintime),
        );
    }
}
