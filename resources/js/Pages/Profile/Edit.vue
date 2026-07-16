<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdateNotificationSettingsForm from './Partials/UpdateNotificationSettingsForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-full bg-[#292688] text-white shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold leading-tight text-gray-800">
                    My Profile
                </h2>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-6xl space-y-8 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-md border border-gray-100 rounded-xl sm:p-8">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="w-full"
                    />
                </div>

                <div v-if="$page.props.auth.user.role === 'store'" class="bg-white p-6 shadow-md border border-gray-100 rounded-xl sm:p-8">
                    <UpdateNotificationSettingsForm class="w-full" />
                </div>

                <div class="bg-white p-6 shadow-md border border-gray-100 rounded-xl sm:p-8">
                    <UpdatePasswordForm class="w-full" />
                </div>

                <!-- <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <DeleteUserForm class="max-w-xl" />
                </div> -->
            </div>
        </div>
    </AuthenticatedLayout>
</template>
