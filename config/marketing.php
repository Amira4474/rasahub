<?php

/*
|--------------------------------------------------------------------------
| Public website content
|--------------------------------------------------------------------------
|
| Everything the marketing site at "/" displays that is likely to change:
| features, prices, testimonials and questions. Edit the copy here rather
| than in the Blade views.
|
*/

return [

    // Where the "Sign in" links on the website point.
    'sign_in_url' => '/admin/login',

    'currency' => 'RM',

    // Shown next to the yearly billing toggle.
    'yearly_saving' => 'Save 20%',

    /*
    | "live" marks what the system does today. Everything else is shown as
    | "Coming soon". Flip the flag as each module ships.
    */
    'features' => [
        [
            'icon' => 'heroicon-o-bolt',
            'title' => 'A POS built for the rush',
            'body' => 'Take an order, fire it to the kitchen and settle the bill in a few taps. Designed for tablets and touch screens, not adapted from a back-office form.',
            'live' => false,
        ],
        [
            'icon' => 'heroicon-o-building-storefront',
            'title' => 'Every branch, one hub',
            'body' => 'Run one outlet or twenty from a single account, each with its own terminals, staff and settings.',
            'live' => true,
        ],
        [
            'icon' => 'heroicon-o-shield-check',
            'title' => 'Roles that match your floor',
            'body' => 'Owner, manager, supervisor, cashier, waiter, kitchen and inventory. Each sees only what they need, in only the branches they work in.',
            'live' => true,
        ],
        [
            'icon' => 'heroicon-o-fire',
            'title' => 'Kitchen display',
            'body' => 'Tickets route to the right station the moment they are confirmed, and every item carries its own status from prep to pass.',
            'live' => false,
        ],
        [
            'icon' => 'heroicon-o-squares-plus',
            'title' => 'Menus and modifiers',
            'body' => 'Sizes, add-ons and "no onions" as reusable modifier groups, with prices you can override per branch.',
            'live' => false,
        ],
        [
            'icon' => 'heroicon-o-qr-code',
            'title' => 'QR ordering',
            'body' => 'Guests scan, order and reorder from the table. Their order lands in the same queue as the cashier\'s.',
            'live' => false,
        ],
        [
            'icon' => 'heroicon-o-computer-desktop',
            'title' => 'Terminals with an identity',
            'body' => 'Every device is registered to a branch, so every order and payment can be traced to where it was made.',
            'live' => true,
        ],
        [
            'icon' => 'heroicon-o-signal-slash',
            'title' => 'Keeps selling offline',
            'body' => 'When the connection drops, terminals queue orders locally and sync them once it returns, without duplicates.',
            'live' => false,
        ],
    ],

    'flow' => [
        ['title' => 'Branch', 'body' => 'An outlet with its own menu prices, staff and hours.'],
        ['title' => 'Terminal', 'body' => 'A registered counter, tablet or handheld.'],
        ['title' => 'Order', 'body' => 'From the cashier, a waiter or a QR code.'],
        ['title' => 'Kitchen', 'body' => 'Items routed to the hot line, bar or pass.'],
        ['title' => 'Payment', 'body' => 'Tracked separately, so split and partial bills work.'],
        ['title' => 'Receipt', 'body' => 'Printed or sent, from any printer you assign.'],
    ],

    'details' => [
        [
            'icon' => 'heroicon-o-banknotes',
            'title' => 'Money counted to the sen',
            'body' => 'Amounts are stored as exact decimals, never as rounded floating-point numbers. Your end-of-day total matches your till.',
        ],
        [
            'icon' => 'heroicon-o-arrows-right-left',
            'title' => 'Order status and payment status are separate',
            'body' => 'A table can be eating and unpaid, or paid and still waiting. RasaHub tracks both, so neither hides the other.',
        ],
        [
            'icon' => 'heroicon-o-lock-closed',
            'title' => 'Your data stays yours',
            'body' => 'Each business sees only its own branches, staff and sales. That rule is enforced on every query and covered by automated tests.',
        ],
        [
            'icon' => 'heroicon-o-printer',
            'title' => 'Not tied to one printer',
            'body' => 'Kitchen, bar and receipt printing go through one printing layer, so changing hardware does not mean changing how you work.',
        ],
    ],

    /*
    | Prices are per branch, per month. "yearly" is the monthly price when
    | billed yearly. A null price renders as "Custom".
    */
    'plans' => [
        [
            'name' => 'Starter',
            'tagline' => 'For a single stall, kiosk or café.',
            'monthly' => 99,
            'yearly' => 79,
            'featured' => false,
            'cta' => 'Start with Starter',
            'includes' => [
                '1 branch, 2 terminals',
                'Up to 5 staff accounts',
                'POS for dine-in and takeaway',
                'Menus, categories and modifiers',
                'Daily sales summary',
                'Email support',
            ],
        ],
        [
            'name' => 'Growth',
            'tagline' => 'For busy restaurants and small groups.',
            'monthly' => 199,
            'yearly' => 159,
            'featured' => true,
            'cta' => 'Choose Growth',
            'includes' => [
                'Up to 5 branches, unlimited terminals',
                'Unlimited staff and custom roles',
                'Kitchen display and table management',
                'QR ordering',
                'Per-branch pricing and reports',
                'Priority support',
            ],
        ],
        [
            'name' => 'Enterprise',
            'tagline' => 'For chains and franchises.',
            'monthly' => null,
            'yearly' => null,
            'featured' => false,
            'cta' => 'Talk to us',
            'includes' => [
                'Unlimited branches',
                'Inventory across outlets',
                'Consolidated group reporting',
                'Offline mode on every terminal',
                'Onboarding and menu migration',
                'A dedicated account manager',
            ],
        ],
    ],

    /*
    | These quotes are illustrative, not from real customers. While
    | "testimonials_are_samples" is true the site says so under the quotes.
    | Replace them with real ones, then set the flag to false.
    */
    'testimonials_are_samples' => true,

    'testimonials' => [
        [
            'quote' => 'Friday dinner used to mean shouting orders across the pass. Now the kitchen sees every ticket the second it is keyed in.',
            'name' => 'Head chef',
            'role' => 'Casual dining restaurant, 80 covers',
        ],
        [
            'quote' => 'I opened my third outlet without opening a third spreadsheet. I check all three from my phone before I have had coffee.',
            'name' => 'Owner',
            'role' => 'Café group, 3 outlets',
        ],
        [
            'quote' => 'New cashiers are taking orders on their first shift. Nobody asks me where the button is any more.',
            'name' => 'Operations manager',
            'role' => 'Quick-service kiosk chain',
        ],
    ],

    'faqs' => [
        [
            'question' => 'What hardware do I need?',
            'answer' => 'Any modern tablet, laptop or touch-screen PC with a browser. RasaHub runs on the web, so there is nothing to install on each device.',
        ],
        [
            'question' => 'Can I run more than one outlet?',
            'answer' => 'Yes. Branches, terminals and staff assignments are built in from the start. A manager at one branch does not see another unless you allow it.',
        ],
        [
            'question' => 'What happens if the internet goes down?',
            'answer' => 'Offline selling is being built into the POS. Terminals will keep taking orders and sync them when the connection returns.',
        ],
        [
            'question' => 'Is everything on this page available today?',
            'answer' => 'Not yet. Features marked "Live" work today. Those marked "Coming soon" are in active development and will arrive module by module.',
        ],
    ],

];
