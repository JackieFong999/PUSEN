<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$conn = DB::connection('pusen');
foreach (['tblSubject', 'tblSubject_Type', 'tblAcademic_Year_Semester', 'tblStaff'] as $t) {
    echo "=== $t ===" . PHP_EOL;
    try {
        $cols = $conn->select("SHOW COLUMNS FROM $t");
        foreach ($cols as $c) {
            echo '  ' . str_pad($c->Field, 32) . ' ' . $c->Type . ($c->Null === 'NO' ? ' NOT NULL' : '') . PHP_EOL;
        }
    } catch (Throwable $e) {
        echo '  ERR: ' . $e->getMessage() . PHP_EOL;
    }
}
echo '=== tblSubject sample rows ===' . PHP_EOL;
$rows = $conn->table('tblSubject')->limit(5)->get();
foreach ($rows as $r) { echo '  ' . json_encode($r) . PHP_EOL; }
echo '=== tblSubject_Type values ===' . PHP_EOL;
foreach ($conn->table('tblSubject_Type')->pluck('Subject_Type') as $v) { echo '  ' . $v . PHP_EOL; }
