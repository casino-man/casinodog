<?php

namespace App\Nova\Helper;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;
use Laravel\Nova\Menu\MenuItem;
use Illuminate\Support\Facades\Cache;
use Wainwright\CasinoDog\Models\Gameslist;

class DefaultGameHelper extends Table
{

    public function handle($pid) {
            $this->mainGate = $pid;
            $check_state = Cache::get('defaultgameimport::'.$this->mainGate);
            if($check_state) {
            return
            MetricTableRow::make()
                ->title('Importing: '.$pid)
                ->subtitle('You have recently dispatched a job for this game provider. Please retry in few minutes,');
            } else {
            return
            MetricTableRow::make()
                ->title('Config id:' . $pid)
                ->subtitle('Gamelist count: ' . $this->retrieve($pid))
                ->actions(function () {
                    if (strpos($this->retrieve($this->mainGate), '0') === 0) {
                        return [];
                    } else {
                        return [
                            MenuItem::externalLink('Upsert '.$this->mainGate.' from internal storage', '/default_game_import?pid='.$this->mainGate),
                            MenuItem::externalLink('Upsert all providers from internal storage', '/default_game_import?pid='.$this->mainGate),
                            MenuItem::externalLink('Import from external source', '/allseeingdavid/resources/game-importer-jobs'),
                        ];

                    }
                });
            }


    }


    public function retrieve($gameprovider)
    {
        $check_count_cache = Cache::get('panel::dashboard::defaultgamescount::'.$gameprovider);
        if($check_count_cache) {
            return $check_count_cache;
        }

        $gamelist_data = $this->parse($gameprovider);

        if($gamelist_data === false) {
            $count = '0';
        } else {
            $gamelist_collect = collect(json_decode($gamelist_data, true));
            $count = $gamelist_collect->count();
        }
        Cache::put('panel::dashboard::defaultgamescount::'.$gameprovider, $count.' cached at '.now(), now()->addMinutes(15));
        return $count;
    }

    public function parse($gameprovider) {
      try {
        // select gameprovider's main controller, from config
        $gameslist = Gameslist::all()->where('provider', $gameprovider);
        $game_controller = config('casino-dog.games.'.$gameprovider.'.controller');
        $game_controller_kernel = new $game_controller;
        
        $store_payload = $game_controller_kernel->default_gamelist("retrieve");

        isset($store_payload['message']) ? $payload = $store_payload['message'] : $payload = $store_payload['error'];

        return $payload;
        } catch(\Exception $e) {
        return false;
        }
    }

}
