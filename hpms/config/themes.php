<?php

/*
|--------------------------------------------------------------------------
| Dashboard themes
|--------------------------------------------------------------------------
|
| Each theme is a design language adapted from the analyses in /themes.
| Theme colours, radii, and type treatments live in resources/css/app.css
| under the matching [data-theme="..."] selector; the metadata here drives
| the Themes gallery and the validation of the saved preference.
|
*/

return [
    'apple' => [
        'name' => 'Apple',
        'tone' => 'Studio neutral',
        'description' => 'Quiet parchment neutrals, a single blue action, and soft pill buttons.',
        'mode' => 'Light',
        'canvas' => '#ffffff',
        'dots' => ['#0066cc', '#f5f5f7', '#1d1d1f'],
    ],
    'cal' => [
        'name' => 'Cal.com',
        'tone' => 'Friendly SaaS',
        'description' => 'Crisp white canvas, ink-black actions, and generous calendar-soft spacing.',
        'mode' => 'Light',
        'canvas' => '#ffffff',
        'dots' => ['#111111', '#ffffff', '#3b82f6'],
    ],
    'intercom' => [
        'name' => 'Intercom',
        'tone' => 'Warm editorial',
        'description' => 'Warm cream paper, white cards, and a confident orange signal colour.',
        'mode' => 'Light',
        'canvas' => '#f5f1ec',
        'dots' => ['#ff5600', '#f5f1ec', '#111111'],
    ],
    'linear' => [
        'name' => 'Linear',
        'tone' => 'Craft dark',
        'description' => 'Near-black surfaces, hairline borders, and a restrained lavender accent.',
        'mode' => 'Dark',
        'canvas' => '#010102',
        'dots' => ['#5e6ad2', '#0f1011', '#f7f8f8'],
    ],
    'mintlify' => [
        'name' => 'Mintlify',
        'tone' => 'Developer green',
        'description' => 'Clean documentation white with a fresh mint accent and mono details.',
        'mode' => 'Light',
        'canvas' => '#ffffff',
        'dots' => ['#00d4a4', '#ffffff', '#0a0a0a'],
    ],
    'notion' => [
        'name' => 'Notion',
        'tone' => 'Workspace',
        'description' => 'Paper-white workspace surfaces, deep navy moments, and a purple action.',
        'mode' => 'Light',
        'canvas' => '#ffffff',
        'dots' => ['#5645d4', '#0a1530', '#d6b6f6'],
    ],
    'resend' => [
        'name' => 'Resend',
        'tone' => 'Cinematic dark',
        'description' => 'Black canvas, luminous white type, and precise neon accents.',
        'mode' => 'Dark',
        'canvas' => '#000000',
        'dots' => ['#fcfdff', '#000000', '#3b9eff'],
    ],
    'sap' => [
        'name' => 'SAP',
        'tone' => 'Enterprise',
        'description' => 'Structured white chrome, SAP blue actions, and disciplined four-pixel corners.',
        'mode' => 'Light',
        'canvas' => '#ffffff',
        'dots' => ['#0057d2', '#ffffff', '#022879'],
    ],
    'zapier' => [
        'name' => 'Zapier',
        'tone' => 'Warm automation',
        'description' => 'Cream-paper canvas, coffee ink, and one saturated orange call to action.',
        'mode' => 'Light',
        'canvas' => '#fffefb',
        'dots' => ['#ff4f00', '#fffefb', '#201515'],
    ],
    'starbucks' => [
        'name' => 'Starbucks',
        'tone' => 'Warm café',
        'description' => 'Café cream, deep House Green, and bright green actions with gold kept in reserve.',
        'mode' => 'Light',
        'canvas' => '#f2f0eb',
        'dots' => ['#00754a', '#f2f0eb', '#1e3932'],
    ],
];
