<script setup>
import { ref, computed } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    role: {
        type: String,
        required: true,
    },
    session: {
        type: Object,
        required: false,
        default: () => ({}),
    },
});

// Emits
const emit = defineEmits(['toggle-sidebar']);

// session safe compute
const session = computed(() => props.session || {});
</script>

<template>
    <nav class="sticky top-0 z-30 border-b border-gray-100 bg-white shadow-sm">
        <div class="mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 justify-between items-center">
                <!-- Sidebar Toggle Button for Mobile -->
                <button
                    @click="emit('toggle-sidebar')"
                    type="button"
                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-600 focus:bg-gray-100 focus:text-gray-600 focus:outline-none md:hidden"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="flex items-center justify-end flex-1">
                    <div class="sm:ms-6 sm:flex sm:items-center">

                    <!-- Settings Dropdown -->
                    <div class="relative ms-3 w-full sm:w-auto">

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <span class="inline-flex rounded-md">

                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                    >
                                        {{ page.props.auth.user.name }}


                                        <svg
                                            class="-me-0.5 ms-2 h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </span>
                            </template>

                            <template #content>
                                <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                <ResponsiveNavLink  v-if="session.orig_user !== null && session.orig_user !== undefined" :href="route('switch.stop')">Back to Admin</ResponsiveNavLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </nav>
</template>
