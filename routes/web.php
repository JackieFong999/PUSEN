<?php

use Illuminate\Support\Facades\Route;

// Root → dashboard
Route::get('/', fn () => redirect()->route('dashboard'));

// Dashboard (screen layout step 1)
Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
