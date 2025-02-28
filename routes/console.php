<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Schedule::call(function () {
    Artisan::call('attendance:mark-absent');
})->everyMinute();
