<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
    phone: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
<div class="min-h-screen flex items-center justify-center px-4 py-10 bg-[#f4f7fc]">
    <div class="bg-white rounded-3xl shadow-2xl p-10 w-full max-w-3xl animate__animated animate__fadeInUp animate__faster">

    <h2 class="text-3xl font-bold text-[#292688] text-center mb-4 pb-6 border-b-2 border-[#292688]">Registration</h2>

    <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
    <div>
    <label class="block text-black font-medium mb-2">Full Name</label>

        <TextInput
                id="name"
                type="text"
                class="mt-1 block w-full"
                v-model="form.name"
                required
                autofocus
                autocomplete="name"
            />
    </div>
    <div>
    <label class="block text-black font-medium mb-2">Email</label>

        <TextInput
                id="email"
                type="email"
                class="mt-1 block w-full"
                v-model="form.email"
                required
                autocomplete="username"
            />
    </div>
    <div>
    <label class="block text-black font-medium mb-2">Password</label>

        <TextInput
                id="password"
                type="password"
                class="mt-1 block w-full"
                v-model="form.password"
                required
                autocomplete="new-password"
        />

        <InputError class="mt-2" :message="form.errors.password" />
    </div>
    <div>
    <label class="block text-black font-medium mb-2">Confirm Password</label>

            <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
    </div>
    <div>
    <label class="block text-black font-medium mb-2">Phone Number</label>

        <TextInput
            id="phone"
            type="number"
            class="mt-1 block w-full"
            v-model="form.phone"
            required
        />

        <InputError class="mt-2" :message="form.errors.phone" />
    </div>
  
    <div class="md:col-span-2 mt-4">
    <button type="submit" class="w-full py-3 rounded-xl custom-btn font-semibold text-lg shadow-md transition duration-300">
        Register Now
    </button>
    </div>
    </form>
    <div class="text-end mt-6">
            <p class="text-[16px] text-gray-500">
              Don't have an account?
              <a :href="route('login')" class="text-[#292688] hover:underline">Login</a>
            </p>
          </div>    
    </div>
</div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');



.custom-btn {
  background-color: #292688;
  color: white;
}
.custom-btn:hover {
  background-color: #292688e6;
}
</style>