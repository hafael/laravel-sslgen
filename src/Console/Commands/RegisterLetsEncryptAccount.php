<?php

namespace Hafael\LaravelSSLClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Hafael\LaravelSSLClient\LEClient\LetsEncrypt;
use Hafael\LaravelSSLClient\Account;

class RegisterLetsEncryptAccount extends Command
{
    /**
     * Let`s Encrypt LEClient implementation
     *
     * @var LetsEncrypt
     */
    protected $client;

    /**
     * Staging mode
     *
     * @var boolean
     */
    protected $staging = false;

    /**
     * User class object
     *
     * @var mixed
     */
    protected $user;

    /**
     * User class unique account identifier
     *
     * @var string
     */
    protected $account_identifier;

    /**
     * The absolute path of certificates
     *
     * @var string
     */
    protected $certs_path;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sslgen:register {email} {--staging}';

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
        $email = $this->argument('email');
        $this->setEnvironment($this->option('staging'));
        $this->setClient($email);
    }

    public function setEnvironment($staging)
    {
        $this->certs_path = config('ssl.certs_path');

        if($staging || 'production' !== config('app.env'))
        {
            $this->warn("In staging mode.");
            $this->line("");
            $this->staging = true;
            $this->certs_path = "{$this->certs_path}/staging";
        }
    }

    public function setClient($email)
    {
        if('tenant' === config('ssl.mode'))
        {
            $userClass = config('ssl.user_class');
            $user = new $userClass();
            $emailField = config('ssl.user_email_attr');
            $this->user = $user->where($emailField, $email)->first();
            if(empty($this->user)) {
                $this->error("User Account does not exist.");
                die;
            }
            $this->account_identifier = $this->user->{config('ssl.account_identifier')};

            $this->certs_path = "{$this->certs_path}/{$this->account_identifier}";
        }

        $this->prepareKeys();
        $path = Storage::disk(config('ssl.local_storage_disk'))->path($this->certs_path);
        $this->client = new LetsEncrypt($email, "account", $path, $this->staging);
        $this->registerAccount();
    }

    public function prepareKeys()
    {
        $cloud = config('ssl.cloud_backup') ? $this->checkIfUserHasCloudKeys() : false;
        $local = $this->checkIfUserHasLocalKeys();

        if(!$local && $cloud)
        {
            //download keys
            $this->downloadKeys();
        }

        if(($local && !$cloud) && config('ssl.cloud_backup'))
        {
            //upload keys
            $this->uploadKeys();
        }

        if($local && $cloud)
        {
            $this->warn("Account already exists.");
            $this->warn("Do you want to renew your keys?");
            $this->line("");
            $this->warn("php artisan sslgen:renew {email}");
            die;
        }
    }

    public function downloadKeys()
    {
        $this->info("Downloading keys...");

        $files = Storage::disk(config('ssl.cloud_storage_disk'))->allFiles("{$this->certs_path}/account");

        $files = array_filter($files, function($item){
            return contains($item, "account");
        });

        foreach($files as $file)
        {
            Storage::disk(config('ssl.local_storage_disk'))->put($file, Storage::disk(config('ssl.cloud_storage_disk'))->get($file));
        }
    }

    public function uploadKeys()
    {
        $this->info("Backuping keys...");

        $files = Storage::disk(config('ssl.local_storage_disk'))->allFiles("{$this->certs_path}/account");

        $files = array_filter($files, function($item){
            return contains($item, "account");
        });

        foreach($files as $file)
        {
            Storage::disk(config('ssl.cloud_storage_disk'))->put($file, Storage::disk(config('ssl.local_storage_disk'))->get($file));
        }
        $this->info("Keys backup completed.");
    }

    public function checkIfUserHasLocalKeys()
    {
        return Storage::disk(config('ssl.local_storage_disk'))
                            ->has("{$this->certs_path}/account/private.pem");
    }

    public function checkIfUserHasCloudKeys()
    {
        return Storage::disk(config('ssl.cloud_storage_disk'))
                            ->has("{$this->certs_path}/account/private.pem");
    }

    public function registerAccount() 
    {
        $this->info("Creating a new account...");

        $email = $this->argument('email');

        $acc = $this->client->getAccount();

        $this->info("New account created for {$email}!");

        if(config('ssl.cloud_backup'))
        {
            $this->uploadKeys();
        }

        if(config('ssl.database'))
        {
            $this->persistOnDatabase($email, $acc);
        }

    }

    public function persistOnDatabase($email, $acc)
    {
        $this->info("Saving on databse...");

        $keys = Storage::disk(config('ssl.local_storage_disk'))->allFiles("{$this->certs_path}/account");

        $keys = array_filter($keys, function($item){
            return contains($item, "account");
        });

        $data = [
            'email' => $email,
            'keys' => json_encode($keys),
        ];

        if('tenant' === config('ssl.mode'))
        {
            $data['user_id'] = $this->user->id;
        }

        $has = Account::where('email', $email)->exists();

        if($has) {
            Account::where('email', $email)->update(array_except($data, ['email']));
        }else {
            Account::create($data);
        }
    }

}
