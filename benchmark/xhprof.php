<?php

xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);

require_once('benchmark-cli.php');

$xhprof_data = xhprof_disable();

$XHPROF_ROOT = 'xhprof/';
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php";
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_runs.php";


$xhprof_runs = new XHProfRuns_Default();
$run_id = $xhprof_runs->save_run($xhprof_data, "xhprof_testing");

error_log("Web path relative to current directory is:");
error_log("/xhprof/xhprof_html/index.php?run={$run_id}&source=xhprof_testing");
