<?php
require_once('../ssutil.php');

function errhandler($errno,$errstr,$errfile,$errline,$errcontext){
    if (error_reporting() == 0) {
        return;
    }
    print_r(func_get_args()); 
}
set_error_handler('errhandler');

$t = new ssutil('../maps/black_and_white.png',8,0,0,'scale','../cache');
$benchmark = $t->benchmark(10);
print_r($benchmark);
