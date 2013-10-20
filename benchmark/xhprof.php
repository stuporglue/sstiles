<?php

xhprof_enable(XHPROF_FLAGS_CPU | XHPROF_FLAGS_MEMORY);

ob_start();
require_once('benchmark-cli.php');
ob_end_clean();

$xhprof_data = xhprof_disable();

$XHPROF_ROOT = 'xhprof/';
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_lib.php";
include_once $XHPROF_ROOT . "/xhprof_lib/utils/xhprof_runs.php";


$xhprof_runs = new XHProfRuns_Default();
$run_id = $xhprof_runs->save_run($xhprof_data, "xhprof_testing");

echo "Web path relative to current directory is:\n";
echo "/xhprof/xhprof_html/index.php?run={$run_id}&source=xhprof_testing\n";
