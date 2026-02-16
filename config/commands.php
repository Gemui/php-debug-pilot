<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Command
    |--------------------------------------------------------------------------
    |
    | Laravel Zero will always run the "default" command if no command
    | name is specified when running the application.
    |
    */

    'default' => App\Commands\SetupCommand::class,

    /*
    |--------------------------------------------------------------------------
    | Commands Paths
    |--------------------------------------------------------------------------
    |
    | This value determines the "paths" that should be loaded by the
    | console's kernel. Foreach "path" present in the array provided
    | the kernel will extract all "Illuminate\Console\Command" based
    | classes and register them with the console application.
    |
    */

    'paths' => [app_path('Commands')],

    /*
    |--------------------------------------------------------------------------
    | Added Commands
    |--------------------------------------------------------------------------
    |
    | You may want to include a single command class without requiring
    | an entire directory. Here you can specify extra commands to load.
    |
    */

    'add' => [
        // App\Commands\ExampleCommand::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hidden Commands
    |--------------------------------------------------------------------------
    |
    | Your application commands will be visible as options when running
    | your application from terminal. You may keep any commands hidden
    | by adding them to the list below.
    |
    */

    'hidden' => [
        NunoMaduro\LaravelConsoleSummary\SummaryCommand::class,
        Symfony\Component\Console\Command\DumpCompletionCommand::class,
        Illuminate\Console\Scheduling\ScheduleRunCommand::class,
        Illuminate\Console\Scheduling\ScheduleListCommand::class,
        Illuminate\Console\Scheduling\ScheduleFinishCommand::class,
        Illuminate\Foundation\Console\VendorPublishCommand::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Removed Commands
    |--------------------------------------------------------------------------
    |
    | Do you have a service provider that loads a list of commands that
    | you do not need? You can list them here.
    |
    */

    'remove' => [
        // App\Commands\UnwantedCommand::class,
    ],

    'updater' => [
        'strategy' => \LaravelZero\Framework\Components\Updater\Strategy\GithubReleasesStrategy::class,
        'repository' => 'Gemui/php-debug-pilot',
    ],
];
