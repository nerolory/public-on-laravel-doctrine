<?php

return [
    'not_found' => 'Record not found.',
    'auth' => [
        'registered' => 'Registration complete.',
        'logged_in' => 'Signed in.',
        'logged_out' => 'Signed out.',
        'failed' => 'Wrong email or password.',
        'banned' => 'The account is banned.',
        'email_taken' => 'This email is already in use.',
    ],
    'account' => [
        'owner_cannot_delete' => 'The site owner cannot delete their own account. Transfer the owner role to another '
            . 'user first.',
        'updated' => 'Details updated.',
        'deleted' => 'Account deleted.',
    ],
    'articles' => [
        'submitted' => 'The article was created and awaits publication.',
        'too_many_images' => 'An article can contain at most :max images.',
        'cover_invalid' => 'The cover was not found or was uploaded by another user.',
        'revised' => 'The article has been sent for moderation again.',
        'archived' => 'The article has been moved to the archive.',
        'version_conflict' => 'The article was changed in another window. Reload the page and make your edit again.',
        'unknown_section' => 'One of the selected sections does not exist.',
    ],
    'seo' => [
        'section_title' => 'Section “:name” — :site',
        'section_description' => 'Articles in the “:name” section.',
    ],
    'complaints' => [
        'own' => 'You cannot report your own publication or yourself.',
        'duplicate' => 'You have already reported this.',
        'sent' => 'The report was sent to the moderators.',
        'hidden_reason' => 'Hidden after reader complaints.',
    ],
    'reactions' => [
        'own' => 'You cannot react to your own publication.',
    ],
    'comments' => [
        'deleted' => 'The comment was deleted.',
        'reply_unavailable' => 'The comment you are replying to is unavailable.',
    ],
    'albums' => [
        'submitted' => 'The album was sent for moderation.',
        'deleted' => 'The album was deleted.',
        'image_invalid' => 'The photo was not found or was uploaded by another user.',
    ],
    'polls' => [
        'options_locked' => 'Options cannot be changed: people have already voted for them.',
        'invalid_choice' => 'Choose an allowed number of options.',
        'already_voted' => 'You have already voted.',
        'revote_disabled' => 'This poll does not allow changing your vote.',
        'closed' => 'Voting has ended.',
    ],
    'charts' => [
        'pie_negative' => 'Pie chart values cannot be negative.',
    ],
    'sections' => [
        'name_taken' => 'A section with this name already exists.',
        'slug_taken' => 'A section with this key already exists.',
        'slug_format' => 'The key may contain only lowercase Latin letters, digits and hyphens between them.',
        'parent_missing' => 'The selected parent section does not exist.',
        'parent_cycle' => 'A section cannot be moved into itself or its own subsection.',
    ],
];
