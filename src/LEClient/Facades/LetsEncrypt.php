<?php

namespace Hafael\LaravelSSLClient\LEClient\Facades;

use Illuminate\Support\Facades\Facade;

class LetsEncrypt extends Facade
{
    /**
   * Get the registered name of the component.
   *
   * @return string
   */
  protected static function getFacadeAccessor()
  {
      return 'LetsEncrypt';
  }
}