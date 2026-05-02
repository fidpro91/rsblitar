<?php

namespace App\Jobs;

use App\Http\Controllers\Api\SignTteController;
use App\Services\TteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SendTteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($request)
    {
        $this->data = $request->all();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(TteService $tteService)
    {   
        $counterKey = 'tte_counter';
        $cooldownKey = 'tte_cooldown';
        if (Cache::has($cooldownKey)) {
            return $this->release(3);
        }
        $count = Cache::increment($counterKey);
        Cache::put($counterKey, $count, 300);
        if ($count >= 20) {
            Cache::forget($counterKey);
            Cache::put($cooldownKey, true, 30);
            return $this->release(30);
        }
        $tteService->signPdf($this->data);
    }
}
