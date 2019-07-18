<?php

namespace Hafael\LaravelSSLClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Hafael\LaravelSSLClient\Events\CloudBackupReady;

class FetchSSLCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sslgen:prepare';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register the local account KeyPair in the Certificate Authority.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("Downloading SSL Certificates from Cloud Storage to Local Storage...");

        $files = Storage::disk(config('ssl.cloud_storage_disk'))->allFiles(config('ssl.certs_path'));

        foreach($files as $file)
        {
            Storage::disk(config('ssl.local_storage_disk'))->put($file, Storage::disk(config('ssl.cloud_storage_disk'))->get($file));
        }

        event(new CloudBackupReady($files));
    }


}
