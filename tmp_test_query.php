<?php
require '/var/www/pusen01/vendor/autoload.php';
$app = require '/var/www/pusen01/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['tblStudent', 'tblFund_Type', 'tblStudent_Status'] as $t) {
    $r = DB::connection('pusen')->selectOne("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$t]);
    echo "$t: " . $r->TABLE_COLLATION . "\n";
    foreach (DB::connection('pusen')->select("SHOW COLUMNS FROM `$t`") as $c) {
        if (in_array($c->Field, ['Fund_Type_Code', 'Faculty', 'Department', 'Student_Status'])) {
            echo "  {$c->Field}: {$c->Type} " . ($c->Collation ?? '') . "\n";
        }
    }
}
