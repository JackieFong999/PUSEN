<?php
require '/var/www/pusen01/vendor/autoload.php';
$app = require '/var/www/pusen01/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$conn = DB::connection('pusen');

echo "== tblSEN_Doc columns ==\n";
foreach ($conn->select('SHOW COLUMNS FROM tblSEN_Doc') as $c) {
    echo "  {$c->Field} ({$c->Type}) null=" . var_export($c->Null, true) . " default=" . var_export($c->Default, true) . "\n";
}
echo "\n== tblSEN_Doc indexes ==\n";
foreach ($conn->select('SHOW INDEX FROM tblSEN_Doc') as $i) {
    $kind = $i->Non_unique ? 'non-unique' : 'UNIQUE';
    echo "  {$i->Key_name}: {$i->Column_name} ($kind)\n";
}
echo "count: " . $conn->table('tblSEN_Doc')->count() . "\n";
echo "sample:\n";
foreach ($conn->table('tblSEN_Doc')->limit(3)->get() as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n== existing storage dirs? ==\n";
foreach (['/var/www/pusen01/storage/app/public', '/var/www/pusen01/storage/app', '/var/www/pusen01/public/storage'] as $d) {
    echo "  $d: " . (is_dir($d) ? 'exists' : 'missing') . "\n";
}
