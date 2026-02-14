<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel Zero application
| instance, which serves as the "glue" for all the components of
| the application.
|
*/

$app = LaravelZero\Framework\Application::configure(basePath: dirname(__DIR__))->create();

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming input using
| the application's HTTP kernel and send the associated response back
| to the client's browser, allowing them to enjoy the creative
| and wonderful application we have prepared for them.
|
*/

return $app;
