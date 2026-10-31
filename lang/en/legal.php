<?php

return [
    'consents_required' => 'The site rules have changed. Accept the new terms to publish articles and comments.',
    'required_consent' => 'You cannot sign up without this consent.',
    'saved' => 'Consent settings saved.',
    'not_revocable' => 'This consent can be withdrawn only by deleting the account.',
    'types' => [
        'personal_data' => 'Consent to personal data processing',
        'site_rules' => 'Site rules',
        'age' => 'Age confirmation',
        'mailing' => 'Consent to receive newsletters',
        'analytics_cookies' => 'Analytics cookies',
        'marketing_cookies' => 'Marketing cookies',
    ],
    'regions' => [
        'all' => 'All regions',
        'ru' => 'Russia',
        'eu' => 'European Union',
        'us' => 'United States',
    ],
    'admin' => [
        'title' => 'Legal documents',
        'menu' => 'Legal documents',
        'intro' => 'Each save creates a new version. Previous versions are kept while user consents refer to them. '
            . 'After a new version of a mandatory document is published, users cannot publish until they accept it.',
        'current_region' => 'Active region: :region.',
        'change_region' => 'Change region',
        'document' => 'Document',
        'region' => 'Region',
        'current' => 'Current version',
        'version' => 'Version :version of :date',
        'missing' => 'Not published',
        'edit' => 'Edit document',
        'edit_named' => 'Edit ":document" for ":region"',
        'publish' => 'Publish a new version',
        'published' => 'Version :version published.',
        'version_hint' => 'Version :version will be published. If the text has not changed, no new version is created.',
        'fields' => ['title' => 'Title', 'text' => 'Text'],
    ],
];
