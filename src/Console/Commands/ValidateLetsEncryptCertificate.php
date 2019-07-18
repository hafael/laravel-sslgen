<?php

namespace Hafael\LaravelSSLClient\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Hafael\LaravelSSLClient\LEClient\LetsEncrypt;
use Illuminate\Filesystem\Filesystem;
use Hafael\LaravelSSLClient\LEClient\LEOrder;
use Hafael\LaravelSSLClient\Account;
use Carbon\Carbon;
use Hafael\LaravelSSLClient\Events\CertificateIssued;
use Hafael\LaravelSSLClient\Events\CertificateValidationFailed;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ValidateLetsEncryptCertificate extends Command
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
    protected $signature = 'sslgen:check {domain : Domain (Apex)}
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
     * @var Filesystem
     */
    public $files;

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
    public function handle(Filesystem $files)
    {
        $this->files = $files;
        $this->apex = $this->argument('domain');
        $email = $this->option('email');

        $this->setEnvironment($this->option('staging'));
        $this->setClient($email);

        $this->validateDomain($email);
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

    public function validateDomain($email) {

        $basename = $this->argument('domain');

        $this->line("Validate certificates for {$basename}");

        $alias = $this->option('alias');
        
        $this->client->executeChallenge($basename, array_merge($alias, [$basename]), LEOrder::CHALLENGE_TYPE_DNS);
        
        $order = $this->client->getOrder($basename, array_merge($alias, [$basename]));

        //$challenges = $order->getPendingAuthorizations(LEOrder::CHALLENGE_TYPE_DNS);

        $this->info("Pending authorizations for {$basename}.");
        $this->line("\n");
        $this->line("Change your dns entries before reload this command.");
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

        if($order->allAuthorizationsValid())
        {
            $this->finalizaOrder($email, $basename, $order);
        }else {
            $this->uploadCertificates($basename);
        }
    }

    public function finalizaOrder($email, $basename, $order)
    {
        if(!$order->isFinalized())
        {
            $this->line("Order Completed. Preparing .CSR files");
            $order->status = "ready";
            $order->finalizeOrder();
        }

        if($order->isFinalized()) {
            $order->getCertificate();
            $this->generateApacheConf($basename, $order);
            if(config('ssl.cloud_backup'))
            {
                $this->uploadCertificates($basename);
                if(config('ssl.database'))
                {
                    $this->persistOnDatabase($email, $basename, $order);
                }
            }
            event(new CertificateIssued($order));
            $this->reloadApache();
        }else {
            event(new CertificateValidationFailed($order));
        }

        if(config('ssl.database'))
        {
            $this->persistOnDatabase($email, $basename, $order);
        }

        $this->line("Done!");
    }



    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/../../stubs/ssl.conf.stub';
    }

    public function generateApacheConf($domain, $order)
    {
        $this->info("Generating Apache SSL Virtual Host Conf...");

        $domains = collect($order->identifiers)->map(function($item){
            return $item["value"];
        })->filter(function($item) use ($domain){
            return $item !== $domain;
        })->toArray();

        $stub = $this->files->get($this->getStub());

        $sslbasepath = Storage::disk(config('ssl.local_storage_disk'))->path(config('ssl.certs_path'));
        
        $tags = [
            'DummyServerName' => $domain,
            'DummySSLCertificateFile' => "{$sslbasepath}/{$this->account_identifier}/{$this->apex}/fullchain.crt",
            'DummySSLCertificateKeyFile' => "{$sslbasepath}/{$this->account_identifier}/{$this->apex}/private.pem",
            'DummyServerAlias' => !empty($domains) ? "ServerAlias ".join(" ",$domains) : "",
        ];
        foreach($tags as $key => $tag)
        {
            $stub =  str_replace($key, $tag, $stub);
        }

        $filepath = config('ssl.certs_path')."/{$domain}.conf";

        Storage::disk(config('ssl.local_storage_disk'))->put($filepath, $stub);

        $this->info("Apache conf generated at {$filepath}");
        sleep(1);
    }

    public function uploadCertificates($domain)
    {
        $this->info("Backuping keys...");

        $files = Storage::disk(config('ssl.local_storage_disk'))->allFiles("{$this->certs_path}");
        $apacheConf = config('ssl.certs_path')."/{$domain}.conf";

        $files = array_filter($files, function($item){
            return !contains($item, "account");
        });

        if(Storage::disk(config('ssl.local_storage_disk'))->exists($apacheConf))
        {
            $files = array_merge($files, [$apacheConf]);
        }

        foreach($files as $file)
        {
            Storage::disk(config('ssl.cloud_storage_disk'))->put($file, Storage::disk(config('ssl.local_storage_disk'))->get($file));
        }
        $this->info("Keys backup completed.");
    }

    public function persistOnDatabase($email, $basename, $order)
    {
        $this->info("Saving on databse...");

        $expiration = collect($order->authorizations)->min(function($item){
            return $item->expires;
        });

        $data = [
            'expiration' => Carbon::parse($expiration)->toDateTimeString(),
            'verified' => ($order->status === 'valid' || $order->status === 'ready') ? true : false,
            'order_expiration' => Carbon::parse($order->expires)->toDateTimeString(),
            'status' => $order->status,
        ];

        if('tenant' === config('ssl.mode'))
        {
            $acc = Account::where('user_id', $this->user->id)->first();
        }else {
            $acc = Account::where('email', $email)->first();
        }

        if(!empty($acc)){
            $acc->domains()->where('apex', $basename)->update($data);
        }

    }

    public function reloadApache()
    {
        $process = new Process('sudo httpd -k restart');
		$process->run();
		if (!$process->isSuccessful()) {
	        throw new ProcessFailedException($process);
		}
    }

}
