<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;
use Laravel\Nova\Menu\MenuItem;
use Illuminate\Support\Facades\Cache;

class DefaultGamelistTable extends Table
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    { 
        $providers = config('casino-dog.games');
        $count = count($providers);
        $output = [];

        foreach($providers as $index => $option) {
            $build_row = new \App\Nova\Helper\DefaultGameHelper;
            $output[$index] = $build_row->handle($index);
        }


        return $output;
    }


    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }
}
