<script setup>
defineProps({
    value: {
        type: String,
    },
    for: {
        type: String,
    },
    required: {
        type: Boolean,
        default: false,
    }
});
</script>

<template>
    <label :for="for" class="block text-sm font-semibold text-gray-800 mb-1">
        <span v-if="value">{{ value }} <span v-if="required" class="text-red-500">*</span></span>
        <span v-else><slot /> <span v-if="required" class="text-red-500">*</span></span>
    </label>
</template>
