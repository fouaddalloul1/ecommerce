<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/metrics/redis', function () {
    return [
        'cpu' => Redis::get('metrics:cpu'),
        'ram' => Redis::get('metrics:ram'),
        'state' => Redis::get('metrics:state'),
    ];
});