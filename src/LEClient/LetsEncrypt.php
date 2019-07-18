<?php

namespace Hafael\LaravelSSLClient\LEClient;

use Hafael\LaravelSSLClient\LEClient\LEClient;

class LetsEncrypt
{
    public $client;
    public $account;
    private $email = null;
    private $staging = LEClient::LE_PRODUCTION;
    private $debug = LEClient::LOG_OFF;
    private $certificateKeys = "certs";
    private $accountKeys = "accounts";

    public function __construct(string $email, $accountKeys = null, $certificateKeys = null, bool $staging = null, int $debug = 0)
    {
        $this->setEmail($email);
        $this->setAccountKeys($accountKeys);
        $this->setCertificateKeys($certificateKeys);
        $this->setStaging($staging);
        $this->setDebug($debug);
        $this->setClient();
        return $this;
    }

    public function setClient()
    {
        $this->client = new LEClient(
            $this->getEmail(),
            $this->getStaging(),
            $this->getDebug(),
            $this->getCertificateKeys(),
            $this->getAccountKeys()
        );
        $this->account = $this->client->getAccount();
    }

    public function getClient()
    {
        return $this->client;
    }

    public function freshClient()
    {
        $this->client = new LEClient(
            $this->getEmail(),
            $this->getStaging(),
            $this->getDebug(),
            $this->getCertificateKeys(),
            $this->getAccountKeys()
        );
    }

    public function setEmail($email = null)
    {
        if(is_array($email)) {
            $this->email = $email;
        }else if(is_string($email)) {
            $this->email = [$email];
        }else {
            $this->error("Email error");
        }
        
        return $this;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setStaging(bool $staging)
    {
        if($staging) {
            $this->staging = LEClient::LE_STAGING;
        }
        return $this;
    }

    public function getStaging()
    {
        return $this->staging;
    }

    public function setDebug(int $debug = 0)
    {
        $this->debug = $debug;
        return $this;
    }

    public function getDebug()
    {
        return $this->debug;
    }

    public function setAccountKeys($keys = null)
    {
        if(!empty($keys))
        {
            $this->accountKeys = $keys;
        }
        
        return $this;
    }

    public function getAccountKeys()
    {
        return $this->accountKeys;
    }

    public function setCertificateKeys($keys = null)
    {
        if(!empty($keys))
        {
            $this->certificateKeys = $keys;
        }
        return $this;
    }

    public function getCertificateKeys()
    {
        return $this->certificateKeys;
    }

    public function getAccount()
    {
        return $this->account;
    }

    public function updateAccount(string $email)
    {
        $this->setEmail($email);

        $acc = $this->getAccount();
        $acc->updateAccount($email);

        return $this;
    }

    public function changeAccountKeys()
    {
        $acc = $this->getAccount();
        $acc->changeAccountKeys();

        return $this;
    }

    public function deactivateAccount()
    {
        $acc = $this->getAccount();
        $acc->deactivateAccount();

        return $this;
    }

    public function createOrder(string $apex, array $domains, string $keyType = "rsa-4096", string $notBefore = "", string $notAfter = "")
    {
        $order = $this->client->getOrCreateOrder($apex, $domains, $keyType, $notBefore, $notAfter);
        return $order;
    }

    public function getOrder(string $apex, array $domains = array(), string $keyType = "rsa-4096", string $notBefore = "", string $notAfter = "")
    {
        return $this->createOrder($apex, $domains, $keyType, $notBefore, $notAfter);
    }

    public function getValidAuthorizations(string $apex, array $domains)
    {
        $order = $this->getOrder($apex, $domains);
        return $order->allAuthorizationsValid();
    }

    public function getPendingAuthorizations(string $apex, array $domains, mixed $type)
    {
        $order = $this->getOrder($apex, $domains);
        return $order->getPendingAuthorizations($type);
    }

    public function executeChallenge(string $apex, array $domains, $type)
    {
        $order = $this->getOrder($apex, $domains);

        $authorizations = $order->getPendingAuthorizations($type);

        if(!empty($authorizations)) {
            foreach($authorizations as $challenge) {
                $order->verifyPendingOrderAuthorization($challenge['identifier'], $type);
            }
        }
        
        return $order->getPendingAuthorizations($type);
    }

    public function deactivateChallenge(string $apex, string $domain)
    {
        $order = $this->getOrder($apex);
        return $order->deactivateOrderAuthorization($domain);
    }

    public function finalizeOrder(string $apex, string $csr = null)
    {
        $order = $this->getOrder($apex);
        return $order->finalizeOrder($csr);
    }

    public function isFinalized(string $apex)
    {
        $order = $this->getOrder($apex);
        return $order->isFinalized();
    }

    public function getCertificate(string $apex)
    {
        $order = $this->getOrder($apex);
        return $order->getCertificate();
    }

    public function revokeCertificate(string $apex, int $reason = null)
    {
        $order = $this->getOrder($apex);
        return $order->revokeCertificate($reason);
    }



}