<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Only locales listed here can ever be activated. The key is the locale
    | code and the value is the label used by the header language switch.
    |
    */

    'supported' => [
        'en' => 'EN',
        'bn' => 'বাংলা',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest Locale Cookie
    |--------------------------------------------------------------------------
    |
    | The cookie keeps a guest's language choice across a full session
    | invalidation (for example after signing out). It contains no
    | authentication information.
    |
    */

    'cookie' => 'hpms_locale',

];
