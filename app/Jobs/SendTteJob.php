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
         $tteService->signPdf($this->data);
    }
}
