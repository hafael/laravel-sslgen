<?php

namespace Hafael\LaravelSSLClient\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Hafael\LaravelSSLClient\Domain;

class DomainCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $domain;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Domain $domain)
    {
        $this->domain = $domain;
    }
}
