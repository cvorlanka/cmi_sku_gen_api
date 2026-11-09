<?php

use App\Http\Controllers\SKUGenController;
use Illuminate\Support\Facades\Route;

Route::post('/skugen', [SKUGenController::class, 'skuGen']);
