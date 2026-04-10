<?php

use Illuminate\Support\Facades\Route;
use Seat\ManualPap\Http\Controllers\ManualPapController;

Route::group([
    'namespace' => 'Seat\ManualPap\Http\Controllers',
    'middleware' => ['web', 'auth', 'locale'],
    'prefix' => 'manual-pap',
], function (): void {

    Route::get('/', [
        'as' => 'manualpap.index',
        'uses' => 'ManualPapController@index',
        'middleware' => 'can:manualpap.view',
    ]);

    Route::post('/', [
        'as' => 'manualpap.store',
        'uses' => 'ManualPapController@store',
        'middleware' => 'can:manualpap.use',
    ]);

    Route::get('/bulk', [
        'as' => 'manualpap.bulk',
        'uses' => 'ManualPapController@bulk',
        'middleware' => 'can:manualpap.use',
    ]);

    Route::post('/bulk', [
        'as' => 'manualpap.bulkStore',
        'uses' => 'ManualPapController@bulkStore',
        'middleware' => 'can:manualpap.use',
    ]);

    Route::get('/report', [
        'as' => 'manualpap.report',
        'uses' => 'ManualPapController@report',
        'middleware' => 'can:manualpap.view',
    ]);

});
