<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: null,
    },
    options: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: "Sélectionner",
    },
    labelKey: {
        type: String,
        default: "label",
    },
    valueKey: {
        type: String,
        default: "value",
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:modelValue"]);

const isOpen = ref(false);
const dropdownRef = ref(null);

const selectedOption = computed({
    get: () => props.modelValue,
    set: (val) => emit("update:modelValue", val),
});

const currentLabel = computed(() => {
    const found = props.options.find(
        (opt) => opt[props.valueKey] === selectedOption.value
    );

    return found ? found[props.labelKey] : props.placeholder;
});

const toggleDropdown = () => {
    if (props.disabled) return;

    if (props.options.length) {
        isOpen.value = !isOpen.value;
    }
};

const selectOption = (value) => {
    if (props.disabled) return;

    selectedOption.value = value;
    isOpen.value = false;
};

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            isOpen.value = false;
        }
    }
);

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
    <div ref="dropdownRef" class="relative w-full">
        <button
            type="button"
            :disabled="props.disabled"
            @click="toggleDropdown"
            class="block w-full p-3 rounded-lg border text-sm transition-all duration-300 outline-none"
            :class="{
                'border-[#E2E8F0] bg-[#F8FAFC] focus:border-[#C5F82A] focus:bg-white focus:ring-[3px] focus:ring-[#C5F82A]/20':
                    !props.disabled,
                'cursor-not-allowed opacity-60 bg-gray-100 text-gray-400':
                    props.disabled,
            }"
        >
            <div class="flex items-center justify-between w-full">
                <span
                    class="truncate text-left flex-1"
                    :class="
                        props.disabled ? 'text-gray-400' : 'text-gray-800'
                    "
                >
                    {{ currentLabel }}
                </span>

                <svg
                    class="w-4 h-4 ml-3 transition-transform duration-300 flex-shrink-0"
                    :class="{
                        'rotate-180 text-[#062121]':
                            isOpen && !props.disabled,
                        'text-gray-400': !isOpen || props.disabled,
                    }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 9l-7 7-7-7"
                    />
                </svg>
            </div>
        </button>

        <div
            v-if="isOpen && options.length && !props.disabled"
            class="absolute left-0 z-20 mt-1 w-full overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-lg animate-fadeIn"
        >
            <div
                v-for="opt in options"
                :key="opt[valueKey]"
                @click="selectOption(opt[valueKey])"
                class="flex cursor-pointer items-center px-3 py-3 text-sm text-gray-700 transition-colors hover:bg-[#F8FAFC]"
                :class="{
                    'bg-[#C5F82A]/15 font-semibold text-[#062121]':
                        opt[valueKey] === selectedOption,
                }"
            >
                <span class="flex-1 truncate">
                    {{ opt[labelKey] }}
                </span>

                <svg
                    v-if="opt[valueKey] === selectedOption"
                    class="h-4 w-4 text-[#062121]"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        d="M5 13l4 4L19 7"
                    />
                </svg>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference "tailwindcss";

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeIn {
    animation: fadeIn 0.2s ease;
}
</style>