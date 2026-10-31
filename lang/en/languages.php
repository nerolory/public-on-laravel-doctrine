<?php

return [
    'title' => 'Languages',
    'menu' => 'Languages',
    'intro' => 'Interface languages of the public site. Translations live in lang/ folders: a new folder with '
        . 'translation files appears in the file list right away.',
    'mode_link' => 'Multilingual mode',
    'create' => 'Add a language',
    'edit' => 'Edit language',
    'delete' => 'Delete language',
    'edit_named' => 'Edit language :name',
    'delete_named' => 'Delete language :name',
    'actions' => 'Actions',
    'confirm_delete' => 'Delete the language ":name"?',
    'created' => 'Language added.',
    'updated' => 'Language updated.',
    'deleted' => 'Language deleted.',
    'code_taken' => 'A language with this code already exists.',
    'cannot_delete_default' => 'The default language cannot be deleted. Make another language the default first.',
    'fields' => [
        'code' => 'Code',
        'name' => 'Name',
        'file' => 'Language file',
        'enabled' => 'Enabled',
        'is_default' => 'Default language',
        'status' => 'Status',
    ],
    'hints' => [
        'code' => 'Two Latin letters, for example en. Used in the address: /en/ or en.example.com. Cannot be changed '
            . 'later.',
        'name' => 'The name in the language itself, for example English. Visitors see it in the switcher.',
        'file' => 'A translations folder from lang/.',
    ],
    'status' => [
        'default' => 'Default',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
    ],
];
