<?php
require_once('../ssutil.php');

function errhandler($errno,$errstr,$errfile,$errline,$errcontext){
    if (error_reporting() == 0) {
        return;
    }
    print_r(func_get_args()); 
}
set_error_handler('errhandler');

$t = new ssutil('../maps/document.png',8,0,0,'stretch','../cache');
$benchmark = $t->benchmark(1000);
error_log(print_r($benchmark,TRUE));
