<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import ThemePicker from '@/components/ThemePicker.vue';
import { Separator } from '@/components/ui/separator';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Appearance settings"
            description="Update your account's appearance settings"
        />
        <ThemePicker />
        <Separator />
        <section class="space-y-3" aria-labelledby="appearance-mode-heading">
            <div class="flex flex-col gap-1">
                <h2 id="appearance-mode-heading" class="text-sm font-medium">
                    Brightness
                </h2>
                <p class="text-sm text-muted-foreground">
                    Pick light, dark, or your device setting.
                </p>
            </div>
            <AppearanceTabs />
        </section>
    </div>
</template>
