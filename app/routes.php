<?php

use \IlluminateResponse as IlluminateResponse;
use \Helpers\Helpers as Helpers;

// Defines the routes for the xAPI.
Route::get('data/xAPI/about', function() {
  return IlluminateResponse::json([
    'X-Experience-API-Version' => '1.0.1',
    'version' => ['1.0.1']
  ], 200, Helpers::getCORSHeaders());
});

Route::group(['prefix'=>'data/xAPI'], function () {
  Route::any('statements', 'Controllers\XAPI\StatementController@selectMethod');
  Route::any('activities/state', 'Controllers\XAPI\Document\StateController@selectMethod');
  Route::any('activities/profile', 'Controllers\XAPI\Document\ActivityProfileController@selectMethod');
  Route::get('activities', 'Controllers\XAPI\Document\ActivityProfileController@full');
  Route::any('agents/profile', 'Controllers\XAPI\Document\AgentProfileController@selectMethod');
  Route::get('agents', 'Controllers\XAPI\Document\AgentProfileController@search');
});

// Defines the routes for the Learning Locker API.
Route::group(['prefix'=>'api/v1'], function () {
  Route::get('statements/aggregate', 'Controllers\API\StatementController@aggregate');
  Route::resource('authority', 'Controllers\API\AuthorityController');
});
