<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Engineering Handlers
    |--------------------------------------------------------------------------
    |
    | The canonical engineering squad used for task handler, project manager
    | and team attendee pickers. Team members added through the workspace
    | are merged into this list automatically.
    |
    */

    'handlers' => [
        'Kong Rathana',
        'Ti Tong',
        'Phal Kristar',
        'Phoeun Chantha',
        'Maco Buntheoun',
        'Sopha Eot',
        'Chan Darong',
        'Lon Sreyheng',
        'Ing Vaniy',
        'Thoeun Sreypov',
    ],

    /*
    |--------------------------------------------------------------------------
    | 1-Click Session Switcher
    |--------------------------------------------------------------------------
    |
    | Team members featured in the login modal's instant role switcher.
    |
    */

    'quick_switch_emails' => [
        'rathana@eocambo.dev',
        'cto@eocambo.dev',
        'titong@eocambo.dev',
        'maco@eocambo.dev',
    ],

    /*
    |--------------------------------------------------------------------------
    | Issue Note Categories
    |--------------------------------------------------------------------------
    */

    'issue_categories' => [
        'Bug / Blocker',
        'API / DB Issue',
        'UI / State',
        'Edge Case',
        'SoD / Edge Case',
    ],

];
