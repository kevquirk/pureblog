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
