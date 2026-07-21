<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/app');
});

Route::view('/privacidade', 'legal.privacidade')->name('legal.privacidade');
