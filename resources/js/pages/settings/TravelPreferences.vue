<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    preference: {
        home_timezone: string;
        default_currency: string;
        traveler_profiles: string[] | null;
        packing_templates: string[] | null;
    };
}>();

const form = useForm({
    home_timezone: props.preference.home_timezone,
    default_currency: props.preference.default_currency,
    traveler_profiles_text: (props.preference.traveler_profiles ?? []).join('\n'),
    packing_templates_text: (props.preference.packing_templates ?? []).join('\n'),
});

const submit = () => {
    form.patch('/settings/travel', { preserveScroll: true });
};
</script>

<template>
    <Head title="Travel settings" />

    <div class="space-y-6">
        <Heading
            title="Travel preferences"
            description="Set defaults used when planning new trips, exports, reminders, and packing templates."
        />

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="home_timezone">Home timezone</Label>
                <Input id="home_timezone" v-model="form.home_timezone" placeholder="America/Denver" />
                <InputError :message="form.errors.home_timezone" />
            </div>

            <div class="grid gap-2">
                <Label for="default_currency">Default currency</Label>
                <Input id="default_currency" v-model="form.default_currency" maxlength="3" placeholder="USD" />
                <InputError :message="form.errors.default_currency" />
            </div>

            <div class="grid gap-2">
                <Label for="traveler_profiles">Traveler profiles</Label>
                <textarea id="traveler_profiles" v-model="form.traveler_profiles_text" class="min-h-28 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="One traveler per line" />
            </div>

            <div class="grid gap-2">
                <Label for="packing_templates">Packing template items</Label>
                <textarea id="packing_templates" v-model="form.packing_templates_text" class="min-h-36 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" placeholder="One packing item per line" />
            </div>

            <Button class="bg-[#1b6b6f] hover:bg-[#155356]" :disabled="form.processing">
                Save travel preferences
            </Button>
        </form>
    </div>
</template>
