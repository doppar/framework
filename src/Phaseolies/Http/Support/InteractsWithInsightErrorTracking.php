<?php

namespace Phaseolies\Http\Support;

use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Request;

trait InteractsWithInsightErrorTracking
{
    /**
     * Forward HTTP exceptions to Insight when the package is available
     *
     * @param Request $request
     * @param HttpException $exception
     * @return void
     */
    protected function recordInsightException(Request $request, HttpException $exception): void
    {
        $recorderClass = 'Doppar\\Insight\\Support\\ErrorHistoryRecorder';

        if (! class_exists($recorderClass)) {
            return;
        }

        try {
            $recorder = app($recorderClass);

            if (is_object($recorder) && method_exists($recorder, 'record')) {
                $recorder->record($exception, $request);
            }
        } catch (\Throwable) {
            // Insight tracking must never block the normal error flow.
        }
    }
}
