<?php

namespace Hafael\LaravelSSLClient;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Hafael\LaravelSSLClient\Events\DomainCreated;
use Hafael\LaravelSSLClient\Events\DomainUpdated;

class Domain extends Model
{
    use SoftDeletes;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('ssl.domains_table');
    }

    /**
     * Indicates if the IDs are auto-incrementing.
     * @var bool
     */
    public $incrementing = false;

    public $primaryKey = 'id';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'apex',
        'aliases',
        'expiration',
        'auto_renewal',
        'verified',
        'order_expiration',
        'order',
        'status',
        'keys',
        'challenges',
        'webmaster_email',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'expiration',
        'order_expiration',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'auto_renewal' => 'boolean',
        'aliases' => 'array',
        'order' => 'array',
        'keys' => 'array',
        'challenges' => 'array',
    ];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => DomainCreated::class,
        'updated' => DomainUpdated::class,
    ];

    public function getDomainURLAttribute() {
        return "https://{$this->domain}";
    }

    /**
     * Returns the account.
     */
    public function account()
    {
        return $this->belongsTo('\Hafael\LaravelSSLClient\Account', 'account_id', 'id');
    }

}