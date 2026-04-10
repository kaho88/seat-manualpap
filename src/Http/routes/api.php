<?php

use Illuminate\Support\Facades\Route;
use Seat\ManualPap\Http\Controllers\Api\ManualPapApiController;

Route::group([
    'namespace' => 'Seat\ManualPap\Http\Controllers\Api',
    'middleware' => ['api'],
    'prefix' => 'api/manual-pap',
], function (): void {

    Route::post('/', [
        'as' => 'manualpap.api.store',
        'uses' => 'ManualPapApiController@store',
    ]);

    Route::get('/report/{year}/{month}', [
        'as' => 'manualpap.api.report',
        'uses' => 'ManualPapApiController@report',
    ])->where(['year' => '[0-9]{4}', 'month' => '[0-9]{1,2}']);

    Route::get('/inactive', [
        'as' => 'manualpap.api.inactive',
        'uses' => 'ManualPapApiController@inactive',
    ]);

});
