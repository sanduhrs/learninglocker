<?php

ClassLoader::addDirectories(array(
	app_path().'/controllers',
	app_path().'/models',
));

Log::useFiles(storage_path().'/logs/laravel.log');

App::error(function (Exception $exception, $code) {
	Log::error($exception);
});

App::down(function () {
	return Response::make("Be right back!", 503);
});
