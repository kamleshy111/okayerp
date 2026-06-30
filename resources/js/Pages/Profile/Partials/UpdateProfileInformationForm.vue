<script setup>
import { ref, computed, watch } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import vSelect from 'vue3-select';
import 'vue3-select/dist/vue3-select.css';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = page.props.auth.user;

const form = useForm({
    _method: 'patch',
    name: user.name,
    phone: user.phone,
    address: user.address,
    city: user.city || '',
    district: user.district || '',
    state: user.state || '',
    country: user.country || '',
    pin_code: user.pin_code || '',
    email: user.email,
    profile_photo: null,
    ledger_pin: '',
    bank_name: user.bank_name || '',
    account_number: user.account_number || '',
    ifsc_code: user.ifsc_code || '',
    branch_name: user.branch_name || '',
    gstin: user.gstin || '',
});

const availableDistricts = computed(() => {
    if (!form.state) return [];
    const stateName = form.state;
    const statesData = page.props.state_cities || {};
    const lookupKey = Object.keys(statesData).find(
        key => key.toLowerCase().replace(/[^a-z0-9]/g, '') === stateName.toLowerCase().replace(/[^a-z0-9]/g, '')
    );
    return lookupKey ? statesData[lookupKey] : [];
});

watch(() => form.state, (newVal, oldVal) => {
    if (oldVal !== undefined) {
        form.district = "";
        form.city = "";
    }
});

// preview image
const preview = ref(user.profile_photo ? `/storage/${user.profile_photo}` : null);

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    form.profile_photo = file;

    if (file) {
        preview.value = URL.createObjectURL(file);
    }
};

</script>

<template>
    <section class="max-w-none mx-auto p-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Profile Information
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Update your account's profile information and email address.
            </p>
        </header>

        <form
            @submit.prevent="form.post(route('profile.update'))"
            class="mt-6 space-y-6"
        >


            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <InputLabel for="name" value="Name" required />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>
                
                <div>
                    <InputLabel for="email" value="Email" required />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                        autocomplete="username"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
                
                <div>
                    <InputLabel for="phone" value="Phone" />
                    <TextInput
                        id="phone"
                        type="number"
                        class="mt-1 block w-full"
                        v-model="form.phone"
                        autofocus
                        autocomplete="phone"
                    />
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>
                
                <div>
                    <InputLabel for="address" value="Address" />
                    <TextInput
                        id="address"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.address"
                        autofocus
                        autocomplete="address"
                    />
                    <InputError class="mt-2" :message="form.errors.address" />
                </div>
            </div>

            <!-- Address Details -->
            <div class="border-t border-gray-200 pt-5">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Address Details</h3>
                <div class="grid grid-cols-2 gap-4">

                    <div>
                        <InputLabel for="state" value="State" />
                        <v-select
                            id="state"
                            :options="$page.props.gst_states"
                            label="display"
                            :reduce="state => state.name"
                            v-model="form.state"
                            placeholder="Search & Select State"
                            class="w-full mt-1"
                        ></v-select>
                        <InputError class="mt-1" :message="form.errors.state" />
                    </div>

                    <div>
                        <InputLabel for="district" value="District" />
                        <v-select
                            id="district"
                            :options="availableDistricts"
                            v-model="form.district"
                            placeholder="Search & Select District"
                            class="w-full mt-1"
                            :disabled="!form.state"
                        ></v-select>
                        <InputError class="mt-1" :message="form.errors.district" />
                    </div>

                    <div>
                        <InputLabel for="city" value="City" />
                        <v-select
                            id="city"
                            :options="availableDistricts"
                            v-model="form.city"
                            placeholder="Search & Select City"
                            class="w-full mt-1"
                            :disabled="!form.state"
                        ></v-select>
                        <InputError class="mt-1" :message="form.errors.city" />
                    </div>

                    <div>
                        <InputLabel for="pin_code" value="Pin Code" />
                        <TextInput
                            id="pin_code"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.pin_code"
                            placeholder="Enter pin code"
                            maxlength="10"
                        />
                        <InputError class="mt-1" :message="form.errors.pin_code" />
                    </div>

                    <div class="col-span-2">
                        <InputLabel for="country" value="Country" />
                        <TextInput
                            id="country"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.country"
                            placeholder="Enter country"
                        />
                        <InputError class="mt-1" :message="form.errors.country" />
                    </div>

                </div>
            </div>

            <div v-if="user.role === 'store'">
                <InputLabel for="ledger_pin" value="Private Ledger PIN (4-digit numeric)" />

                <TextInput
                    id="ledger_pin"
                    type="password"
                    pattern="[0-9]*"
                    inputmode="numeric"
                    maxlength="4"
                    class="mt-1 block w-full"
                    v-model="form.ledger_pin"
                    autocomplete="new-password"
                    placeholder="Enter 4-digit PIN to secure private ledger"
                />

                <InputError class="mt-2" :message="form.errors.ledger_pin" />
                <p class="mt-1 text-xs text-gray-500">
                    This PIN is used to secure the Private Ledger. Leave blank if you do not want to change it.
                </p>
            </div>

            <!-- Bank Details -->
            <div v-if="user.role === 'store'" class="border-t border-gray-200 pt-6 space-y-6">
                <h3 class="text-md font-semibold text-gray-800">Bank Details</h3>

                <div>
                    <InputLabel for="bank_name" value="Bank Name" />
                    <TextInput
                        id="bank_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.bank_name"
                        autocomplete="bank_name"
                        placeholder="Enter Bank Name"
                    />
                    <InputError class="mt-2" :message="form.errors.bank_name" />
                </div>

                <div>
                    <InputLabel for="account_number" value="Account Number" />
                    <TextInput
                        id="account_number"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.account_number"
                        autocomplete="account_number"
                        placeholder="Enter Account Number"
                    />
                    <InputError class="mt-2" :message="form.errors.account_number" />
                </div>

                <div>
                    <InputLabel for="ifsc_code" value="IFSC Code" />
                    <TextInput
                        id="ifsc_code"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.ifsc_code"
                        autocomplete="ifsc_code"
                        placeholder="Enter IFSC Code"
                    />
                    <InputError class="mt-2" :message="form.errors.ifsc_code" />
                </div>

                <div>
                    <InputLabel for="branch_name" value="Branch Name" />
                    <TextInput
                        id="branch_name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.branch_name"
                        autocomplete="branch_name"
                        placeholder="Enter Branch Name"
                    />
                    <InputError class="mt-2" :message="form.errors.branch_name" />
                </div>

                <div>
                    <InputLabel for="gstin" value="GSTIN" />
                    <TextInput
                        id="gstin"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.gstin"
                        autocomplete="gstin"
                        placeholder="Enter GSTIN"
                    />
                    <InputError class="mt-2" :message="form.errors.gstin" />
                </div>
            </div>

            <!-- Profile Photo -->
            <div>
                <InputLabel for="profile_photo" value="Profile Photo / Store Logo" />
                <input type="file" id="profile_photo" accept="image/*"
                    @change="handleFileUpload"
                    class="mt-1 block w-full border rounded-md px-3 py-2"/>
                <InputError class="mt-2" :message="form.errors.profile_photo" />


                <div v-if="preview" class="mt-3">
                    <img :src="preview" alt="Profile Preview" class="w-20 h-20 rounded-full object-cover" style="max-height: 80px;" />
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>

<style>
.v-select .vs__dropdown-toggle {
    min-height: 42px;
    border-radius: 0.375rem !important;
    border-color: #d1d5db;
    padding-top: 0.125rem;
    padding-bottom: 0.125rem;
}
.v-select .vs__selected, .v-select .vs__search {
    margin-top: 0;
    margin-bottom: 0;
    line-height: 1.5;
}
</style>
