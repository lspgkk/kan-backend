<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cols = DB::select('SHOW COLUMNS FROM jabatan_config_a');
foreach($cols as $c) echo $c->Field . ' | ' . $c->Type . PHP_EOL;
