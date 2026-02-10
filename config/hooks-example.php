<?php

// Optional hooks file. Copy to config/hooks.php to enable.
// Define only the hooks you need.

function bunny_purge(): void
{
    $accessKey = 'ADD-KEY-HERE';
    $pullZoneId = 'ADD-ZONE-ID-HERE';
    if ($accessKey === '' || $pullZoneId === '') {
        return;
    }

    $endpoint = "https://api.bunny.net/pullzone/{$pullZoneId}/purgeCache";
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["AccessKey: {$accessKey}"],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function on_post_published(string $slug): void
{
    // Example: purge CDN for the post URL.
    bunny_purge();
}

function on_post_updated(string $slug): void
{
    // Example: purge CDN for updated post or homepage.
    bunny_purge();
}

function on_post_deleted(string $slug): void
{
    // Example: purge CDN for deleted post + homepage.
    bunny_purge();
}

function on_page_published(string $slug): void
{
    // Example: purge CDN for the page URL.
    bunny_purge();
}

function on_page_updated(string $slug): void
{
    // Example: purge CDN for updated page.
    bunny_purge();
}

function on_page_deleted(string $slug): void
{
    // Example: purge CDN for deleted page.
    bunny_purge();
}

/**
 * Optional: add custom admin action buttons (shown in top admin nav).
 *
 * @return array<int,array<string,string>>
 */
function admin_action_buttons(): array
{
    return [
        [
            'id' => 'purge_cache',
            'label' => 'Purge cache',
            'class' => 'delete',
            'confirm' => 'Purge CDN cache now?',
            'icon' => 'circle-x',
        ],
    ];
}

/**
 * Optional: handle custom admin actions.
 *
 * @return array{ok:bool,message:string}
 */
function on_admin_action(string $actionId): array
{
    if ($actionId !== 'purge_cache') {
        return ['ok' => false, 'message' => 'Unknown action.'];
    }

    bunny_purge();
    return ['ok' => true, 'message' => 'CDN cache purge requested.'];
}
