<?php

test('navigation contract matches the recorded state file', function () {
    $root = dirname(__DIR__, 3);
    $state = json_decode(file_get_contents($root.'/ai/state/navigation-uplift.json'), true);
    $navigation = file_get_contents($root.'/resources/js/lib/navigation.ts');

    foreach ($state['proposed_global_nav'] as $item) {
        expect($navigation)->toContain("title: '{$item['title']}'")
            ->and($navigation)->toContain("matchMode: '{$item['match_mode']}'");

        foreach ($item['exclude_prefixes'] ?? [] as $prefix) {
            if ($prefix === '/trips/search') {
                expect($navigation)->toContain('tripsSearch.url()');

                continue;
            }

            expect($navigation)->toContain($prefix);
        }
    }
});
