<?php

use Illuminate\Support\Facades\Route;

// TEMP DEBUG
Route::post('/admin/data-import/_debug', function (\Illuminate\Http\Request $r) {
    return response()->json([
        'isJson'       => $r->isJson(),
        'wantsJson'    => $r->wantsJson(),
        'expectsJson'  => $r->expectsJson(),
        'all'          => $r->all(),
        'input_file'   => $r->input('file'),
        'header_ct'    => $r->header('Content-Type'),
        'header_accept'=> $r->header('Accept'),
    ]);
})->middleware('auth');
