<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel SSL Client Config (letsencrypt)
    |--------------------------------------------------------------------------
    | 
    |
    */
    
    'mode' => 'tenant', // "tenant" for client domain ssl or "webmaster" for app admin owned domains

    /**
     * Database
     */
    'database' => true,
    'accounts_table' => 'ssl_accounts',
    'domains_table' => 'ssl_domains',

    /**
     * Certificates Filesystem
     */
    //Local Storage (mandatory)
    'certs_path' => 'ssl',
    'local_storage_disk' => 'local',
    
    //Cloud Storage
    'cloud_backup' => false,
    'cloud_storage_disk' => 'cloudssl',

    /**
     * Tenant Mode
     * Required when mode is "tenant"
     */
    'user_class' => 'App\\User',
    'user_email_attr' => 'email',
    'account_identifier' => 'id',

    /**
     * Webmaster Mode
     * Required when mode is "webmaster"
     */
    'email' => env('SSL_WEBMASTER_EMAIL', 'webmaster@domain.com'), //required when mode = "admin"

];
