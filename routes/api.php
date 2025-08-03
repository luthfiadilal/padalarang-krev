<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MidtransController;

Route::post('/midtrans/notification', [MidtransController::class, 'handleNotification']);

