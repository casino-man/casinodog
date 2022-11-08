<?php

namespace Wainwright\CasinoDog;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wainwright\CasinoDog\Commands\ControlCasinoDog;
use Wainwright\CasinoDog\Commands\MigrateCasinoDog;
use Wainwright\CasinoDog\Commands\AutoConfigCasinoDog;
use Illuminate\Support\ServiceProvider;
use Wainwright\CasinoDog\Commands\AddOperatorAccessKey;
use Wainwright\CasinoDog\Commands\StoreDefaultGameslist;
use Illuminate\Support\Facades\Request;
use Wainwright\CasinoDog\Commands\CreateGameProvider;
use Wainwright\CasinoDog\Commands\RetrieveDefaultGameslist;
use Wainwright\CasinoDog\Commands\TestUnzip;
use Wainwright\CasinoDog\Commands\DatabaseObserve;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\URL;
use Wainwright\CasinoDog\Middleware\SecureHeaders;
class CasinoDogServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('casino-dog')
            ->hasConfigFile('casino-dog')
            ->hasRoutes(['web', 'api', 'games'])
            ->hasViews('wainwright')
            ->hasMigrations(['create_freebets_table', 'create_gamerespin_template_table', 'create_games_thumbnails', 'create_bgaming_bonusgames_table', 'modify_users_table', 'create_crawlerdata_table', 'create_game_importer_job', 'create_datalogger_table', 'create_gameslist_table', 'create_metadata_table', 'create_parent_sessions', 'create_rawgameslist_table', 'create_operatoraccess_table'])
            ->hasCommands(TestUnzip::class, DatabaseObserve::class, RetrieveDefaultGameslist::class, StoreDefaultGameslist::class, CreateGameProvider::class, AddOperatorAccessKey::class, AutoConfigCasinoDog::class, ControlCasinoDog::class, MigrateCasinoDog::class);

            $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
            $kernel->pushMiddleware(\Wainwright\CasinoDog\Middleware\SecureHeaders::class);

            //Register the proxy
            $this->app->bind('ProxyHelper', function($app) {
                return new ProxyHelper();
            });

            Request::macro('DogGetIP', function () {
                foreach (array('HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $key){
                    if (array_key_exists($key, $_SERVER) === true){
                        foreach (explode(',', $_SERVER[$key]) as $ip){
                            $ip = trim($ip); // just to be safe
                            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                                return $ip;
                            }
                        }
                    }
                }
                return request()->ip(); // it will return server ip when no client ip found
            });
            $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('casino-dog:database-observe')->everyMinute();
            });
            if ($this->app->environment('production')) {
                URL::forceScheme('https');
            }
            $this->registerPanelCommands();

    }

    public function registerPanelCommands()
    {
         $this->commands([
            \Laravel\Nova\Console\InstallCommand::class,
            \Laravel\Nova\Console\PublishCommand::class,
            \Laravel\Nova\Console\DashboardCommand::class,
            \Laravel\Nova\Console\ResourceCommand::class,
            \Laravel\Nova\Console\BaseResourceCommand::class,
            \Laravel\Nova\Console\TableCommand::class,
            \Laravel\Nova\Console\TranslateCommand::class,
            \Laravel\Nova\Console\ToolCommand::class,
            \Laravel\Nova\Console\TrendCommand::class,
            \Laravel\Nova\Console\UserCommand::class,
            \Laravel\Nova\Console\StubPublishCommand::class
        ]);
    }

}
