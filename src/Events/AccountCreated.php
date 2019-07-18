<?php

namespace Hafael\LaravelSSLClient\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Hafael\LaravelSSLClient\Account;

class AccountCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $account;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Account $account)
    {
        $this->account = $account;
    }
}
