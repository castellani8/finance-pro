<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'landing.index')->name('landing');

Route::view('/privacidade', 'legal.privacidade')->name('legal.privacidade');
