<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = DB::connection('pusen');

$runs = $conn->table('tblHK_Student_Log')->orderByDesc('Id')->limit(20)->get();
$senCounts = $conn->table('tblHK_SEN_Log')->selectRaw('HK_Run_Id, COUNT(*) AS sen_count')->groupBy('HK_Run_Id')->pluck('sen_count', 'HK_Run_Id');
$docCounts = $conn->table('tblHK_SEN_Doc_Log')->selectRaw('HK_Run_Id, COUNT(*) AS doc_count')->groupBy('HK_Run_Id')->pluck('doc_count', 'HK_Run_Id');

$html = view('admin.housekeeping', compact('runs', 'senCounts', 'docCounts'))->render();

// extract the rows of the recent-runs table body
preg_match_all('/<tr>\s*<td class="mono">#(\d+)<\/td>(.*?)<\/tr>/s', $html, $m, PREG_SET_ORDER);
foreach ($m as $row) {
    $cells = preg_split('/<\/td>\s*<td/', $row[2]);
    echo 'Run #'.$row[1]."\n";
    foreach ($cells as $i => $c) {
        echo '  cell '.$i.': '.trim(strip_tags($c))."\n";
    }
    echo "---\n";
}
