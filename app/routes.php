<?php


Route::group(['prefix'=>'data/xAPI'], function () {
  Route::any('statements', [
    'uses' => 'Controllers\XAPI\StatementController@selectMethod'
  ]);
});

Route::group(['prefix'=>'api/v1'], function () {
  Route::get('statements/aggregate', [
    'uses' => 'Controllers\API\StatementController@aggregate'
  ]);
});