<script setup>
import { onMounted, ref } from "vue";
import { requestAutofocus } from "../utils/autofocus";

defineOptions({
  inheritAttrs: false
});

const model = defineModel({
    type: String,
    required: true,
});

const props = defineProps({
    icon: { type: String, default: null },
    type: { type: String, default: "text" },
    placeholder: { type: String, default: "" },
    error: { type: String, default: null },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
});

const input = ref(null);
const isFocused = ref(false);

onMounted(() => {
    if (props.autofocus) {
        requestAutofocus(() => input.value?.focus());
    }
});

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div class="relative">
        <div
            class="relative transition-all duration-200"
            :class="{ 'transform scale-[1.02]': isFocused }"
        >
            <div v-if="icon" class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none z-10">
                <i :class="[icon, 'text-gray-400 transition-colors duration-200 text-sm', { 'text-[#C5F82A]': isFocused }]"></i>
            </div>

            <input
                :type="type"
                :placeholder="placeholder"
                :required="required"
                class="w-full p-3 rounded-xl border-2 transition-all duration-200 outline-none bg-white"
                :class="[
                    icon ? 'pl-10' : 'pl-4',
                    error
                        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                        : 'border-gray-200 focus:border-[#C5F82A] focus:ring-4 focus:ring-[#C5F82A]/20',
                    { 'border-[#C5F82A] ring-4 ring-[#C5F82A]/20': isFocused && !error }
                ]"
                v-model="model"
                ref="input"
                @focus="isFocused = true"
                @blur="isFocused = false"
            />
        </div>

        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 -translate-y-1"
            enter-to-class="transform opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="transform opacity-100 translate-y-0"
            leave-to-class="transform opacity-0 -translate-y-1"
        >
            <p v-if="error" class="mt-1.5 text-xs text-red-500 flex items-center gap-1">
                <i class="fas fa-exclamation-circle text-[10px]"></i>
                {{ error }}
            </p>
        </transition>
    </div>
</template>