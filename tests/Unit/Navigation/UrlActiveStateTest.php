<?php

test('current url active state rules are exported for exact prefix and excluded subtree matching', function () {
    $root = dirname(__DIR__, 3);
    $source = file_get_contents($root.'/resources/js/composables/useCurrentUrl.ts');

    expect($source)->toContain('export function normalizePath')
        ->and($source)->toContain('export function isUrlActive')
        ->and($source)->toContain('excludePrefixes.some')
        ->and($source)->toContain('startsWith ? urlToCompare.startsWith(path)');
});
