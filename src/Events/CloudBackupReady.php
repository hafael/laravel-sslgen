<?php

namespace Hafael\LaravelSSLClient\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class CloudBackupReady
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $files;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(array $files)
    {
        $this->files = $files;
    }
}
