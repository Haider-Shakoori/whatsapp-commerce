<?php

return [
    // Only a trusted server operator may set this. Never accept arbitrary user-supplied URLs.
    'messaging_driver' => env('COMMERCE_MESSAGING_DRIVER', 'disabled'),
    'relaywa_base_url' => env('RELAYWA_API_BASE_URL'),
    'relaywa_connect_timeout' => 5,
    'relaywa_request_timeout' => 20,
    'webhook_timestamp_tolerance' => 300,
    // AI is strictly staff-triggered draft-only, never an automatic sender.
    'ai_api_key' => env('COMMERCE_AI_API_KEY'),
    'ai_model' => env('COMMERCE_AI_MODEL', 'gpt-4.1-mini'),
];
