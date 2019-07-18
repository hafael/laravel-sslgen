<?php

namespace Hafael\LaravelSSLClient\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Hafael\LaravelSSLClient\LEClient\LEOrder;

class CertificateIssued
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(LEOrder $order)
    {
        $this->order = $order;
    }
}
