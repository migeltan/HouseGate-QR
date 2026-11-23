<?php

/*
 * "What's new" overlay shown to each signed-in user on every sign-in (resources/views/partials/whats-new.blade.php).
 *
 *  - "Got it" / X only closes it for the current sign-in. The "Don't show this again" checkbox records the
 *    current `id` on the user, so it stays hidden until `id` changes.
 *  - To announce something new: change `id` and the items. Everybody sees it again, even those who ticked
 *    "Don't show this again" before. Keep the same `id` while only fixing typos or adding to the same release.
 *  - To turn the popup off: set `id` to null.
 */
return [
    'id' => '2026-10-away-cards',
    'eyebrow' => "What's new",
    'title' => 'Updates to HouseGate',
    'version_label' => 'October 2026',
    'items' => [
        [
            'icon' => 'fa-right-left',
            'title' => 'Away cards, smoother',
            'text' => 'Handing a card or ID back now asks for confirmation in the same style as the rest of the app, shows a notification, and updates the list without reloading the page.',
        ],
        [
            'icon' => 'fa-id-card',
            'title' => 'Transfers keep track of cards and IDs',
            'text' => 'When a visitor moves to another building, the old card is held at that desk and the ID stays at the original one. Away cards shows what still has to be handed back.',
        ],
        [
            'icon' => 'fa-calendar-days',
            'title' => 'Long-term passes: up to 7 days',
            'text' => 'Long-term passes can now be set for a maximum of 7 calendar days from today.',
        ],
    ],
];