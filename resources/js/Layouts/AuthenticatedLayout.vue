<template>
    <div class="flex min-h-screen bg-[#f4f7fc]">
        <!-- Sidebar -->
        <aside class="main-side-header text-white flex flex-col w-64 shrink-0" id="sidebar">
            <Sidebar :role="role" :session="session"/>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top Navigation -->
            <Header :role="role" :session="session" />

            <!-- Optional Page Header -->
            <header class="bg-white shadow" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-4 flex-1">
                <slot />
            </main>
        </div>
    </div>
</template>

<script>
import Sidebar from './Sidebar.vue';
import Header from './Header.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export default {
    props: {
        header: {
            type: String,
            required: false,
        },
    },
    components: { Sidebar, Header },
    setup() {
        const { props } = usePage();
        const role = computed(() => props.auth?.user?.role || null);
        const session = computed(() => props.session || null);

        return { role, session  };
    },
};
</script>