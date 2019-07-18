<?php

namespace Hafael\LaravelSSLClient;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Hafael\LaravelSSLClient\Events\AccountCreated;
use Hafael\LaravelSSLClient\Events\AccountUpdated;

class Account extends Model
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
        $this->table = config('ssl.accounts_table');
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
        'email',
        'keys',
        'verified',
        'user_id',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'keys' => 'array',
    ];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => AccountCreated::class,
        'updated' => AccountUpdated::class,
    ];

    /**
     * Returns the user account.
     */
    public function user()
    {
        return $this->belongsTo(config('ssl.user_class'));
    }

    /**
     * Returns the domains.
     */
    public function domains()
    {
        return $this->hasMany('\Hafael\LaravelSSLClient\Domain', 'account_id');
    }

}