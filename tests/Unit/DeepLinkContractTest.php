<?php

use App\Services\NotificationDeepLinkResolver;

test('notification deep link resolver matches the recorded state contract', function () {
    $root = dirname(__DIR__, 2);
    $state = json_decode(file_get_contents($root.'/ai/state/notification-deep-links.json'), true);

    $expected = collect($state['subject_map'])
        ->mapWithKeys(fn (array $item): array => [
            $item['subject_type'] => [
                'panel' => $item['panel'],
                'anchor' => $item['anchor_template'],
            ],
        ])
        ->all();

    expect(NotificationDeepLinkResolver::SUBJECT_MAP)->toBe($expected);
});
