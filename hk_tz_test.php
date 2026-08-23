<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('pusen');

echo "app timezone: ".config('app.timezone')."\n";
echo "php default tz: ".date_default_timezone_get()."\n";
echo "now(): ".now()."\n";

$run = $conn->table('tblHK_Student_Log')->latest('Id')->first();
echo "stored Delete_At: ".$run->Delete_At."\n";
echo "class of value: ".gettype($run->Delete_At)."\n";

$parsed = \Carbon\Carbon::parse($run->Delete_At, 'UTC');
echo "parsed: ".$parsed." (tz=".$parsed->timezoneName.")\n";
echo "converted HK: ".$parsed->setTimezone('Asia/Hong_Kong')->format('Y-m-d H:i:s')."\n";
