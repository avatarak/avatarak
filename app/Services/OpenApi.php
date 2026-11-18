<?php

namespace App\Services;

/**
 * The OpenAPI 3.1 description of the public API. Built in code (not a hand-edited JSON file) so the origin, the limits and
 * the field list always come from the running application; tests compare it with the real routes and resources.
 */
class OpenApi
{
    public const PROFILE_FIELDS = ['hash', 'username', 'profile_url', 'name_fa', 'name_en', 'headline', 'location', 'bio', 'website', 'socials', 'links', 'verified_accounts', 'avatar_url'];

    public function build(): array
    {
        $hash = ['type' => 'string', 'pattern' => '^[A-Fa-f0-9]{64}$', 'description' => 'SHA-256 of the trimmed, lower-cased email address (64 hex characters).', 'example' => hash('sha256', 'ali@example.com')];
        $err = fn (string $example) => ['content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error'], 'example' => ['message' => $example]]]];
        $secured = [['bearerAuth' => []], ['apiKeyHeader' => []]];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => (config('brand.name_en') ?: config('brand.name')).' API',
                'version' => '1.0.0',
                'description' => "Turn an email address into an avatar and a public profile.\n\nProfile lookups need an API key (create one under *Developers* in the dashboard). Avatar images and the public profile export need no key. The email address is never sent: only its SHA-256 hash.",
                'license' => ['name' => 'MIT'],
            ],
            'servers' => [['url' => rtrim((string) config('app.url'), '/')]],
            'tags' => [['name' => 'Profiles', 'description' => 'Needs an API key.'], ['name' => 'Public', 'description' => 'No key needed.']],
            'paths' => [
                '/api/v1/profiles/{hash}' => ['get' => [
                    'tags' => ['Profiles'], 'operationId' => 'getProfile', 'summary' => 'Get one profile', 'security' => $secured,
                    'description' => 'Returns the public profile for an email hash. The answer is `404` for unknown, unverified, private and hidden profiles alike, so it cannot be used to find out whether an address has an account.',
                    'parameters' => [['name' => 'hash', 'in' => 'path', 'required' => true, 'schema' => $hash]],
                    'responses' => [
                        '200' => ['description' => 'The profile.', 'headers' => ['X-RateLimit-Limit' => ['schema' => ['type' => 'integer']], 'X-RateLimit-Remaining' => ['schema' => ['type' => 'integer']]],
                            'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['$ref' => '#/components/schemas/Profile']]]]]],
                        '401' => ['description' => 'Missing or invalid API key.'] + $err('Invalid or missing API key.'),
                        '403' => ['description' => 'The key does not have the profiles:read scope.'] + $err('This key does not have the "profiles:read" scope.'),
                        '404' => ['description' => 'No public profile for this hash.'] + $err('Profile not found.'),
                        '429' => ['description' => 'Rate limit exceeded; see the Retry-After header.', 'headers' => ['Retry-After' => ['schema' => ['type' => 'integer']]]] + $err('Rate limit exceeded.'),
                    ],
                ]],
                '/api/v1/profiles/lookup' => ['post' => [
                    'tags' => ['Profiles'], 'operationId' => 'lookupProfiles', 'summary' => 'Look up up to 100 profiles at once', 'security' => $secured,
                    'description' => 'Send 1 to 100 hashes (duplicates are merged, upper case is accepted). The result is an object keyed by hash; a hash without a public profile is `null`.',
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['hashes'], 'properties' => ['hashes' => ['type' => 'array', 'minItems' => 1, 'maxItems' => ApiLimits::MAX_BATCH, 'items' => $hash]]], 'example' => ['hashes' => [hash('sha256', 'ali@example.com'), hash('sha256', 'nobody@example.com')]]]]],
                    'responses' => [
                        '200' => ['description' => 'Profiles keyed by hash.', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object', 'additionalProperties' => ['oneOf' => [['$ref' => '#/components/schemas/Profile'], ['type' => 'null']]]]]]]]],
                        '401' => ['description' => 'Missing or invalid API key.'] + $err('Invalid or missing API key.'),
                        '403' => ['description' => 'The key does not have the profiles:lookup scope.'] + $err('This key does not have the "profiles:lookup" scope.'),
                        '422' => ['description' => 'Empty list, more than 100 hashes, or a value that is not 64 hex characters.'] + $err('The hashes field must not have more than 100 items.'),
                        '429' => ['description' => 'Rate limit exceeded.', 'headers' => ['Retry-After' => ['schema' => ['type' => 'integer']]]] + $err('Rate limit exceeded.'),
                    ],
                ]],
                '/avatar/{hash}' => ['get' => [
                    'tags' => ['Public'], 'operationId' => 'getAvatar', 'summary' => 'The avatar image', 'security' => [],
                    'description' => 'A square image: JPEG, or WebP when `Accept` contains `image/webp`. When there is no public photo the response is a default image, identical for unknown, unverified and private addresses.',
                    'parameters' => [
                        ['name' => 'hash', 'in' => 'path', 'required' => true, 'schema' => $hash],
                        ['name' => 's', 'in' => 'query', 'description' => 'Size in pixels, 16 to 512 (alias: `size`).', 'schema' => ['type' => 'integer', 'minimum' => 16, 'maximum' => 512, 'default' => 80]],
                        ['name' => 'd', 'in' => 'query', 'description' => 'What to return when there is no public photo (alias: `default`).', 'schema' => ['type' => 'string', 'enum' => ['mp', 'identicon', 'blank', '404'], 'default' => 'mp']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'The image.', 'headers' => ['ETag' => ['schema' => ['type' => 'string']], 'Cache-Control' => ['schema' => ['type' => 'string']]],
                            'content' => ['image/jpeg' => ['schema' => ['type' => 'string', 'format' => 'binary']], 'image/webp' => ['schema' => ['type' => 'string', 'format' => 'binary']], 'image/svg+xml' => ['schema' => ['type' => 'string']], 'image/png' => ['schema' => ['type' => 'string', 'format' => 'binary']]]],
                        '304' => ['description' => 'Not modified (send If-None-Match).'],
                        '404' => ['description' => 'Only with `d=404` and no public photo.'],
                    ],
                ]],
                '/{username}.json' => ['get' => [
                    'tags' => ['Public'], 'operationId' => 'getPublicProfile', 'summary' => 'Public profile data', 'security' => [],
                    'description' => 'Everything the public profile page shows, as JSON. Also available as `.md`, `.vcf` and `.card`. Social handles are full URLs here.',
                    'parameters' => [['name' => 'username', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'pattern' => '^[A-Za-z][A-Za-z0-9_]{2,31}$', 'example' => 'javid']]],
                    'responses' => ['200' => ['description' => 'The profile.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/PublicProfile']]]], '404' => ['description' => 'No such public profile.']],
                ]],
            ],
            'webhooks' => collect(\App\Models\Webhook::EVENTS)->mapWithKeys(fn ($e) => [$e => ['post' => [
                'summary' => "Delivered when: {$e}",
                'description' => 'Signed with `Avatarak-Signature: t=<unix>,v1=<HMAC-SHA256(secret, "<t>.<raw body>")>`. Answer 2xx within 8 seconds; failures are retried.',
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WebhookEvent']]]],
                'responses' => ['200' => ['description' => 'Acknowledged']],
            ]]])->all(),
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'avk_…', 'description' => 'Your API key: `Authorization: Bearer avk_…`'],
                    'apiKeyHeader' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key', 'description' => 'The same key in an alternative header.'],
                ],
                'schemas' => [
                    'Error' => ['type' => 'object', 'required' => ['message'], 'properties' => ['message' => ['type' => 'string'], 'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]]]],
                    'VerifiedAccount' => ['type' => 'object', 'required' => ['network', 'identifier', 'url'], 'properties' => [
                        'network' => ['type' => 'string', 'enum' => ['website', 'github', 'linkedin', 'telegram']], 'identifier' => ['type' => 'string'],
                        'url' => ['type' => ['string', 'null'], 'description' => 'null for LinkedIn, which has no public address.'],
                    ]],
                    'WebhookEvent' => ['type' => 'object', 'required' => ['id', 'type', 'created_at', 'data'], 'properties' => [
                        'id' => ['type' => 'string', 'format' => 'uuid'], 'type' => ['type' => 'string', 'enum' => [...\App\Models\Webhook::EVENTS, 'ping']], 'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        'data' => ['type' => 'object', 'properties' => [
                            'username' => ['type' => ['string', 'null']], 'profile_url' => ['type' => ['string', 'null'], 'format' => 'uri'],
                            'email_hashes' => ['type' => 'array', 'items' => ['type' => 'string']], 'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'network' => ['type' => 'string'], 'identifier' => ['type' => 'string'],
                        ]],
                    ]],
                    'Link' => ['type' => 'object', 'required' => ['label', 'url'], 'properties' => ['label' => ['type' => 'string'], 'url' => ['type' => 'string', 'format' => 'uri'], 'description' => ['type' => 'string'], 'image' => ['type' => 'string', 'format' => 'uri']]],
                    'Profile' => ['type' => 'object', 'required' => self::PROFILE_FIELDS, 'properties' => [
                        'hash' => $hash, 'username' => ['type' => 'string'], 'profile_url' => ['type' => 'string', 'format' => 'uri'],
                        'name_fa' => ['type' => ['string', 'null']], 'name_en' => ['type' => ['string', 'null']], 'headline' => ['type' => ['string', 'null']], 'location' => ['type' => ['string', 'null']],
                        'bio' => ['type' => ['string', 'null']], 'website' => ['type' => ['string', 'null'], 'format' => 'uri'],
                        'socials' => ['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'description' => 'network → handle, e.g. {"github": "javid"}'],
                        'links' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Link']],
                        'verified_accounts' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/VerifiedAccount']],
                        'avatar_url' => ['type' => ['string', 'null'], 'format' => 'uri'],
                    ]],
                    'PublicProfile' => ['type' => 'object', 'description' => 'The public export; see the docs for every field.', 'properties' => [
                        'username' => ['type' => 'string'], 'url' => ['type' => 'string', 'format' => 'uri'], 'display_name' => ['type' => 'string'], 'name_fa' => ['type' => ['string', 'null']], 'name_en' => ['type' => ['string', 'null']],
                        'headline' => ['type' => ['string', 'null']], 'location' => ['type' => ['string', 'null']], 'bio' => ['type' => ['string', 'null']], 'website' => ['type' => ['string', 'null']],
                        'avatar_url' => ['type' => ['string', 'null']], 'verified' => ['type' => 'boolean'], 'socials' => ['type' => 'object', 'additionalProperties' => ['type' => 'string', 'format' => 'uri']],
                        'links' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Link']], 'verified_accounts' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/VerifiedAccount']],
                        'gallery' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string', 'format' => 'uri'], 'thumb_url' => ['type' => 'string', 'format' => 'uri'], 'caption' => ['type' => ['string', 'null']], 'width' => ['type' => 'integer'], 'height' => ['type' => 'integer']]]],
                        'sections' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'body' => ['type' => 'string']]]],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'], 'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                    ]],
                ],
            ],
        ];
    }
}
