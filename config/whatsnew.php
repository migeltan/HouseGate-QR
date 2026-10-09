<?php

/*
 * "What's new" overlay shown once to each signed-in user (resources/views/partials/whats-new.blade.php).
 *
 *  - To announce something new: change `id` and the items. Everybody sees it once more, even those who
 *    dismissed the previous one. Keep the same `id` while only fixing typos.
 *  - To turn the popup off: set `id` to null.
 */
return [
    'id' => '2026-10-directory-sidebar',
    'eyebrow' => "What's new",
    'title' => 'Updates to HouseGate',
    'version_label' => 'October 2026',
    'items' => [
        [
            'icon' => 'fa-address-book',
            'title' => 'Faster Directory on a weak connection',
            'text' => 'The roster is kept on this device, so search and filters respond instantly and keep working if the connection drops. It refreshes in the background when you are online.',
        ],
        [
            'icon' => 'fa-table-columns',
            'title' => 'Easier sidebar',
            'text' => 'Hover the collapsed sidebar for a hint, then click anywhere on it to open it. The arrow button still works.',
        ],
        [
            'icon' => 'fa-key',
            'title' => 'Remember me now works',
            'text' => 'Tick it when you sign in to stay signed in on this computer. Building personnel are asked for their building again after a long break.',
        ],
    ],
];