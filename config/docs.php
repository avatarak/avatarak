<?php

/*
 | The documentation lives in resources/docs/{fa,en}/{slug}.md (Markdown with a small front-matter block).
 | This file only decides the ORDER and GROUPING of pages; titles come from each file's front matter.
 | To add a page: create both language files and list the slug here.
 */
return [
    'repo' => 'https://github.com/avatarak/avatarak',
    'branch' => 'main',

    'groups' => [
        ['title' => ['fa' => 'شروع', 'en' => 'Get started'], 'slugs' => ['getting-started', 'hash']],
        ['title' => ['fa' => 'آواتار', 'en' => 'Avatars'], 'slugs' => ['avatars']],
        ['title' => ['fa' => 'REST API', 'en' => 'REST API'], 'slugs' => ['authentication', 'api-reference', 'sdks']],
        ['title' => ['fa' => 'داده‌ی عمومی', 'en' => 'Public data'], 'slugs' => ['exports', 'verified-accounts']],
        ['title' => ['fa' => 'اصول', 'en' => 'Policies'], 'slugs' => ['privacy-and-limits']],
        ['title' => ['fa' => 'میزبانی شخصی', 'en' => 'Self-hosting'], 'slugs' => ['administration']],
    ],
];
