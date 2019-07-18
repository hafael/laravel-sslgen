<?php

namespace Hafael\LaravelSSLClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Hafael\LaravelSSLClient\LEClient\LetsEncrypt;
use Hafael\LaravelSSLClient\LEClient\LEOrder;
use Hafael\LaravelSSLClient\Account;
use Hafael\LaravelSSLClient\Events\CertificateRequested;

class IssueLetsEncryptCertificates extends Command
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
     * The absolute path of account certificates
     *
     * @var string
     */
    protected $account_path;

    /**
     * The apex domain (site directory)
     *
     * @var string
     */
    protected $apex;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sslgen:issue {domain : Domain (Apex)}
                                              {--alias=* : Alias and Wilcard}
                                              {--email= : Domain admin email}
                                              {--staging : Development Mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provide or renew a new set of certificates for the domain.';

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
        $this->apex = $this->argument('domain');
        $email = $this->option('email');

        $this->setEnvironment($this->option('staging'));
        $this->setClient($email);

        $this->createNewOrder($email);
    }

    public function setEnvironment($staging)
    {
        $this->certs_path = config('ssl.certs_path');
        $this->account_path = config('ssl.certs_path');

        if($staging || 'production' !== config('app.env'))
        {
            $this->warn("In staging mode.");
            $this->line("");
            $this->staging = true;
            $this->certs_path = "{$this->certs_path}/staging";
            $this->account_path = "{$this->account_path}/staging";
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
            $this->certs_path = "{$this->certs_path}/{$this->account_identifier}/{$this->apex}";
            $this->account_path = "{$this->account_path}/{$this->account_identifier}";
        }

        //check keys
        $this->prepareKeys($email);

        $path = Storage::disk(config('ssl.local_storage_disk'))->path($this->certs_path);
        $this->client = new LetsEncrypt($email, "../account", $path, $this->staging);
    }


    public function prepareKeys($email)
    {
        $local = $this->checkIfUserHasLocalKeys();

        if(!$local)
        {
            $this->warn("Account Keys does not recognized.");
            $this->call('sslgen:register', [
                'email' => $email, '--staging' => $this->staging
            ]);
        }
    }

    public function checkIfUserHasLocalKeys()
    {
        return Storage::disk(config('ssl.local_storage_disk'))
                            ->has("{$this->account_path}/account/private.pem");
    }

    public function createNewOrder($email) {

        $basename = $this->argument('domain');

        $this->line("Drafting new Certificate for {$basename} <{$email}>");

        $alias = $this->option('alias');

        $order = $this->client->createOrder($basename, array_merge($alias, [$basename]));

        $this->info("Certificate order successfully generated for {$basename}.");
        $this->line("\n");
        $this->line("Change your dns entries before run sslgen:check command.");
        $this->line("");
        $headers = ['Domain', 'Name', 'Type', 'Value', 'Status'];

        $data = [];

        $pending = $order->getPendingAuthorizations(LEOrder::CHALLENGE_TYPE_DNS);

        foreach($order->authorizations as $authorization)
        {
            $option = collect($pending)->first(function($item) use ($authorization){
                return $item["identifier"] === $authorization->identifier["value"];
            });
            
            $data[] = [
                "domain" => $authorization->identifier["value"],
                "name" => "_acme-challenge.".$authorization->identifier["value"].".",
                "type" => "TXT",
                "value" => !empty($option) ? $option["DNSDigest"] : "OK",
                "status" => $authorization->status,
            ];
        }

        $this->table($headers, $data);

        if(config('ssl.cloud_backup'))
        {
            $this->uploadOrder();
        }

        if(config('ssl.database'))
        {
            $this->persistOnDatabase($email, $basename, $order);
        }

        event(new CertificateRequested($order));

    }

    public function uploadOrder()
    {
        $this->info("Backuping keys...");

        if(!Storage::disk(config('ssl.local_storage_disk'))->exists("{$this->certs_path}/order"))
        {
            $i = 0;
            while(!Storage::disk(config('ssl.local_storage_disk'))->exists("{$this->certs_path}/order") || $i <= 5)
            {
                sleep(1);
                $i++;
                $this->info("...");
            }
        }

        if(!Storage::disk(config('ssl.local_storage_disk'))->exists("{$this->certs_path}/order"))
        {
            $this->error("Unreachable order.");
            die;
        }

        $files = Storage::disk(config('ssl.local_storage_disk'))->allFiles("{$this->certs_path}");

        foreach($files as $file)
        {
            Storage::disk(config('ssl.cloud_storage_disk'))->put($file, Storage::disk(config('ssl.local_storage_disk'))->get($file));
        }
        $this->info("Keys backup completed.");
    }

    public function persistOnDatabase($email, $basename, $order)
    {
        $this->info("Saving on databse...");

        $challenges = array_map(function($item){
            return [
                'identifier' => $item->identifier,
                'challenges' => $item->challenges,
            ];
        }, $order->authorizations);

        $keys = Storage::disk(config('ssl.local_storage_disk'))->allFiles("{$this->certs_path}");

        $data = [
            'webmaster_email' => $email,
            'apex' => $basename,
            'aliases' => json_encode(array_map(function($item){ return $item['value']; }, $order->identifiers)),
            'expiration' => date("Y-m-d H:i:s", strtotime(date($order->expires))),
            'auto_renewal' => true,
            'verified' => ($order->status === 'valid' || $order->status === 'ready') ? true : false,
            'order_expiration' => date("Y-m-d H:i:s", strtotime(date($order->expires))),
            'order' => $order->getOrderURL(),
            'status' => $order->status,
            'keys' => json_encode($keys),
            'challenges' => json_encode($challenges),
        ];

        if('tenant' === config('ssl.mode'))
        {
            $acc = Account::where('user_id', $this->user->id)->first();
        }else {
            $acc = Account::where('email', $email)->first();
        }

        if(!empty($acc)){

            if($acc->domains()->where('apex', $basename)->exists())
            {
                $acc->domains()->where('apex', $basename)->update(array_only($data, [
                    'aliases',
                    'expiration',
                    'verified',
                    'order_expiration',
                    'status',
                    'challenges',
                ]));
            }else {
                $acc->domains()->create($data);
            }
        }

    }
}
