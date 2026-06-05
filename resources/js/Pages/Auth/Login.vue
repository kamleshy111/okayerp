<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>





<!-- <template>
    <div class="min-h-screen flex items-center justify-center px-4 py-10 bg-[#f4f7fc]">
      <div class="bg-white rounded-3xl shadow-2xl p-10 w-full max-w-sm animate__animated animate__fadeInUp animate__faster">
        <h2 class="text-3xl font-bold text-[#292688] text-center mb-4 pb-6 border-b-2 border-[#292688]">Login</h2>
  
        <form  @submit.prevent="submit" class="space-y-6 mt-8">
          <div>
            <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
            <InputError class="mt-2" :message="form.errors.email" />
          </div>
  
          <div>
   
            <InputLabel for="password" value="Password" />
   
            <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />

            <InputError class="mt-2" :message="form.errors.password" />
          </div>
          <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600"
                        >Remember me</span
                    >
                </label>
            </div>
  
          <div class="text-right">
            <Link
                v-if="canResetPassword"
                :href="route('password.request')"
                class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                    Forgot your password?
            </Link>
          </div>
  
          <div>
            <button
              type="submit"
              :disabled="loading"
              class="w-full py-3 rounded-xl custom-btn font-semibold text-lg shadow-md transition duration-300 flex items-center justify-center"
            >
              <span>Login</span>
            
            </button>
          </div>
  
          <div class="text-center">
            <p class="text-sm text-gray-500">
              Don't have an account?
              <a href="#" class="text-[#292688] hover:underline">Sign Up</a>
            </p>
          </div>
        </form>
      </div>
    </div>
  </template> -->
  

<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-10 bg-[#f4f7fc]">
      <div class="bg-white rounded-3xl shadow-2xl p-10 w-full max-w-sm">
        <h2 class="text-3xl font-bold text-[#292688] text-center mb-4 pb-6 border-b-2 border-[#292688]">
            Login
        </h2>
  
        <form @submit.prevent="submit" class="space-y-6 mt-8">
          <div>
            <label class="block text-black font-medium mb-2">Email</label>
            <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
            <InputError class="mt-2" :message="form.errors.email" />
          </div>
  
          <div>
            <label class="block text-black font-medium mb-2">Password</label>
            <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />
            <InputError class="mt-2" :message="form.errors.password" />
          </div>
          <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600"
                        >Remember me</span
                    >
                </label>
            </div>
          <div class="text-right">
            <Link
                v-if="canResetPassword"
                :href="route('password.request')"
                class="rounded-md text-sm text-[#292688] underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                    Forgot your password?
            </Link>
          </div>
  
          <div>
            <button
              type="submit"
              class="w-full py-3 rounded-xl custom-btn font-semibold text-lg shadow-md transition duration-300"
            >
              Login
            </button>
          </div>
  
          <!-- <div class="text-center">
            <p class="text-sm text-gray-500">
              Don't have an account?
              <a :href="route('register')" class="text-[#292688] hover:underline">Sign Up</a>
            </p>
          </div> -->
        </form>
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
  