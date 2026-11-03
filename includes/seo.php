<?php
/**
 * Structured data builders (schema.org / JSON-LD).
 */

declare(strict_types=1);

function owner_name(): string
{
    return (string) config('owner.name', 'Apatira Tijani Adewale');
}

/** @return array<string, mixed> */
function person_schema(): array
{
    $owner = config('owner');

    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'Person',
        '@id'         => site_url('') . '#person',
        'name'        => $owner['name'],
        'givenName'   => $owner['first_name'],
        'jobTitle'    => $owner['role'],
        'description' => $owner['tagline'],
        'url'         => site_url(''),
        'email'       => 'mailto:' . $owner['email'],
        'image'       => site_url('assets/img/og-default.png'),
        'address'     => [
            '@type'           => 'PostalAddress',
            'addressLocality' => 'Lagos',
            'addressCountry'  => 'NG',
        ],
        'sameAs' => array_values(array_filter([
            $owner['github'],
            $owner['linkedin'],
        ])),
        'knowsAbout' => [
            'Web development', 'Web applications', 'Backend development', 'Python', 'Django',
            'PHP', 'Laravel', 'JavaScript', 'E-commerce', 'API integration', 'Business automation',
        ],
    ];
}

/** @return array<string, mixed> */
function website_schema(): array
{
    return [
        '@context'  => 'https://schema.org',
        '@type'     => 'WebSite',
        '@id'       => site_url('') . '#website',
        'name'      => owner_name() . ' — ' . config('owner.role'),
        'url'       => site_url(''),
        'inLanguage' => 'en',
        'publisher' => ['@id' => site_url('') . '#person'],
        'description' => config('owner.tagline'),
    ];
}

/**
 * @param list<array<string, mixed>> $projects
 * @return array<string, mixed>
 */
function projects_collection_schema(array $projects, string $url, string $title, string $description): array
{
    $items = [];
    $position = 1;
    foreach ($projects as $project) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'url'      => site_url(project_url($project)),
            'name'     => (string) $project['title'],
        ];
    }

    return [
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        '@id'         => $url . '#collection',
        'name'        => $title,
        'description' => $description,
        'url'         => $url,
        'isPartOf'    => ['@id' => site_url('') . '#website'],
        'mainEntity'  => [
            '@type'           => 'ItemList',
            'numberOfItems'   => count($items),
            'itemListElement' => $items,
        ],
    ];
}

/** @return array<string, mixed> */
function project_schema(array $project, string $canonical): array
{
    $cover = trim((string) ($project['cover_image'] ?? ''));
    $schema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'CreativeWork',
        '@id'         => $canonical . '#project',
        'name'        => (string) $project['title'],
        'description' => (string) ($project['seo_description'] ?: $project['short_description']),
        'url'         => $canonical,
        'creator'     => ['@id' => site_url('') . '#person'],
        'inLanguage'  => 'en',
        'keywords'    => implode(', ', array_map(
            static fn (array $tech): string => (string) $tech['name'],
            $project['technologies'] ?? []
        )),
    ];

    if ($cover !== '') {
        $schema['image'] = upload_url($cover);
    }
    if (!empty($project['completion_date'])) {
        $schema['dateCreated'] = (string) $project['completion_date'];
    }
    if (is_safe_url($project['live_url'] ?? '')) {
        $schema['sameAs'] = (string) $project['live_url'];
    }

    return $schema;
}

/**
 * @param list<array{name:string, url:string}> $items
 * @return array<string, mixed>
 */
function breadcrumb_schema(array $items): array
{
    $elements = [];
    $position = 1;
    foreach ($items as $item) {
        $elements[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $item['name'],
            'item'     => $item['url'],
        ];
    }

    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $elements,
    ];
}
