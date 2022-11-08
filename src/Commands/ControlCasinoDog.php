<?php

namespace Wainwright\CasinoDog\Commands;

use Illuminate\Support\Facades\Http;
use Wainwright\CasinoDog\Models\Gameslist;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Process;
use Wainwright\CasinoDog\Commands\InstallNovaPanel;
use Wainwright\CasinoDog\Controllers\Game\Wainwright\WainwrightCustomSlots;
use App\Models\User;
use Wainwright\CasinoDog\Models\Settings;
use DB;

class ControlCasinoDog extends Command
{
    use InstallNovaPanel;

    protected $signature = 'casino-dog:install {auto-task?}
                                               {--composer=global : Absolute path to the Composer binary which should be used to install packages}';

    public $description = 'Install casino-dog panel.';

    public function handle()
    {
        if ($this->argument('auto-task')) {
            $task = $this->argument('auto-task');

            if($task === 'panel') {
              $this->requireComposerPackages('wainwright/panel:^4.17');
              $this->installPanel('silent');
            }

            if($task === 'migrate-fresh') {
                \Artisan::call('migrate:fresh');
            }

            if($task === 'migrate') {
                \Artisan::call('migrate');
            }

            if($task === 'create-admin') {
                return $this->createAdmin();
            }

            if(str_contains($task, 'update-env:')) {
                //syntax is update-env:{Key=value}
                $between = $this->in_between('{', '}', $task);
                $key = explode('=', $between)[0];
                $value = explode('=', $between)[1];
                $env = $this->putPermanentEnv($key, $value);
                $this->info($key.' set to '.$env);
                return $env;
            }

            if($task === 'set-global-api-limit') {
                $this->replaceInBetweenInFile("perMinute\(", "\)", '500', base_path('app/Providers/RouteServiceProvider.php'));
                $this->replaceInFile('$request->ip()', '$request->DogGetIP()', base_path('app/Providers/RouteServiceProvider.php'));
            }


        } else {

        if($this->confirm('Do you want to install admin panel?')) {
            $this->requireComposerPackages('wainwright/panel:^4.17');
            $this->installPanel();
        }  else {
            $this->info('.. Skipped installing panel stubs');
        }
        if($this->confirm('Do you want to run database migrations?')) {
            \Artisan::call('vendor:publish --tag="casino-dog-migrations"');
            $this->info('> Running..  "vendor:publish --tag="casino-dog-migrations"');
            \Artisan::call('migrate');
            $this->info('> Running..  "artisan migrate"');
        }  else {
            $this->info('.. Skipped database migrations');
        }

        /* Publish config file*/
        if($this->confirm('Do you want to publish config?')) {
            \Artisan::call('vendor:publish --tag="casino-dog-config"');
            $this->info('> Running..  "vendor:publish --tag="casino-dog-config"');
            $this->info('> Config published in config/casino-dog.php');
        }  else {
            $this->info('.. Skipped publishing config');
        }

        if($this->confirm('Do you want to set API limit in RouteServiceProvider.php to 500?')) {
            $this->replaceInBetweenInFile("perMinute\(", "\)", '500', base_path('app/Providers/RouteServiceProvider.php'));
            $this->replaceInFile('$request->ip()', '$request->DogGetIP()', base_path('app/Providers/RouteServiceProvider.php'));

            $this->info('> Running..  "api limit"');
        }  else {
            $this->info('.. Skipped database migrations');
        }

	    if($this->confirm('Do you want to install Ably?')) {
            $this->requireComposerPackages('ably/ably-php-laravel:^1.0');
            if($this->confirm('Do you want to publish ably config?')) {
            \Artisan::call('vendor:publish --tag="Ably\Laravel\AblyServiceProvider"');
            }
            $this->info('Seems all went fine. You need to now manually add Ably\'s serviceprovider in config/app.php by adding to provider array: ');
            $this->info('"Ably\Laravel\AblyServiceProvider::class,"');       
            $this->info('Without this your application will error as a whole when trying to send socketted messages.');
        }
        if($this->confirm('Do you want to set new Ably apikey?')) {
            $current_key = config('ably.key');
            $this->info('> Current ablykey: '.$current_key);
            $new_key = $this->ask('What is your Ably api key?');
            $this->replaceInFile($current_key, $new_key, base_path('config/ably.php'));
         }

         if($this->confirm('Do you want to import wainwright custom slot?')) {
            $wainwright_custom = new WainwrightCustomSlots;

            $query = collect($wainwright_custom->gameslist());
            foreach($query as $game) {
                $data_insert = array(
                    'gid' => $game["gid"],
                    'gid_extra' => $game["gid_extra"],
                    "batch" => "1",
                    'slug' => $game["slug"],
                    'name' => $game["name"],
                    'type' => $game["type"],
                    'typeRating' => $game["popularity"],
                    'provider' => $game["provider"],
                    'method' => $game["method"],
                    'source' => $game["source"],
                    'popularity' => $game["popularity"],
                    'demolink' => "",
                    'demoplay' => $game["demoplay"],
                    'source_schema' => $game["source_schema"],
                    'origin_demolink' => "not_needed",
                    "realmoney" => "[]",
                    'popularity' => $game["popularity"],
                    'enabled' => $game["enabled"],
                );
                Gameslist::insert($data_insert);
                $this->info(json_encode($data_insert));
            }
	    }
                
	}
        return self::SUCCESS;
    }
    protected function replaceInFile($search, $replace, $path)
    {
        file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
    }
    public function replaceInBetweenInFile($a, $b, $replace, $path)
    {
        $file_get_contents = file_get_contents($path);
        $in_between = $this->in_between($a, $b, $file_get_contents);
        if($in_between) {
            $search_string = stripcslashes($a.$in_between.$b);
            $replace_string = stripcslashes($a.$replace.$b);
            file_put_contents($path, str_replace($search_string, $replace_string, file_get_contents($path)));
            return self::SUCCESS;
        }
        return self::SUCCESS;
    }

    public function in_between($a, $b, $data)
    {
        preg_match('/'.$a.'(.*?)'.$b.'/s', $data, $match);
        if(!isset($match[1])) {
            return false;
        }
        return $match[1];
    }

    public function putPermanentEnv($key, $value)
    {
        $path = app()->environmentFilePath();

        if(env($key) === NULL) {
              $fp = fopen($path, "r");
              $content = fread($fp, filesize($path));
                file_put_contents($path, $content. "\n". $key .'=' . $value);

        } else {

            $escaped = preg_quote('='.env($key), '/');

            file_put_contents($path, preg_replace(
                "/^{$key}{$escaped}/m",
                "{$key}={$value}",
                file_get_contents($path)
            ));
        }

        return env($key);
    }

    public function createAdmin()
    {
        if(env('WAINWRIGHT_CASINODOG_ADMIN_PASSWORD') !== NULL) {
            $password = env('WAINWRIGHT_CASINODOG_ADMIN_PASSWORD');
        } else {
            $password = md5(env('APP_KEY').config('casino-dog.securitysalt'));
        }

        $select_current = User::where('email', 'admin@casinoman.app')->first();
        if($select_current) {
            DB::table('users')->where('email', 'admin@casinoman.app')->update([
                'password' => bcrypt($password),
                'is_admin' => 1
            ]);
            $this->info('Admin login \'admin@casinoman.app\' already exist, changed password to:'.$password);
        } else {

        $userData = [
            'name' => 'admin',
            'email' => 'admin@casinoman.app',
            'password' => bcrypt($password),
            'is_admin' => 1
        ];
        DB::table('users')->insert($userData);
        $this->info('Login: admin@casinoman.app');
        $this->info('Password: '.$password);
        }
    }

    protected function requireComposerPackages($packages)
    {
        $composer = $this->option('composer');

        if ($composer !== 'global') {
            $command = ['php', $composer, 'require'];
        }

        $command = array_merge(
            $command ?? ['composer', 'require'],
            is_array($packages) ? $packages : func_get_args()
        );

        (new Process($command, base_path(), ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output) {
                $this->output->write($output);
            });
    }

}
