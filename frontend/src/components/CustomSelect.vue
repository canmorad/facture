<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from "vue";

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
    containerClass: {
        type: String,
        default: "",
    },
    searchable: {
        type: Boolean,
        default: true,
    },
    searchPlaceholder: {
        type: String,
        default: "Rechercher...",
    },
    usePortal: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["update:modelValue", "search"]);

const isOpen = ref(false);
const dropdownRef = ref(null);
const triggerRef = ref(null);
const searchInputRef = ref(null);
const searchQuery = ref("");
const dropdownPosition = ref({ top: 0, left: 0, width: 0 });

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

// Filtered options based on search query
const filteredOptions = computed(() => {
    if (!props.searchable || !searchQuery.value) {
        return props.options;
    }

    const query = searchQuery.value.toLowerCase();
    return props.options.filter((opt) => {
        const label = String(opt[props.labelKey] || "").toLowerCase();
        return label.includes(query);
    });
});

const hasSearchResults = computed(() => {
    return filteredOptions.value.length > 0;
});

const calculateDropdownPosition = () => {
    if (!triggerRef.value) return;

    const rect = triggerRef.value.getBoundingClientRect();
    const scrollY = window.scrollY || window.pageYOffset;
    const scrollX = window.scrollX || window.pageXOffset;

    dropdownPosition.value = {
        top: rect.bottom + scrollY + 4,
        left: rect.left + scrollX,
        width: rect.width,
    };
};

const toggleDropdown = () => {
    if (props.disabled) return;

    if (props.options.length) {
        isOpen.value = !isOpen.value;

        if (isOpen.value) {
            if (props.usePortal) {
                calculateDropdownPosition();
            }
            if (props.searchable) {
                nextTick(() => {
                    if (searchInputRef.value) {
                        searchInputRef.value.focus();
                    }
                });
            }
        }
    }
};

const selectOption = (value) => {
    if (props.disabled) return;

    selectedOption.value = value;
    isOpen.value = false;
    searchQuery.value = "";
};

const handleClickOutside = (event) => {
    const trigger = triggerRef.value;
    const dropdown = dropdownRef.value;

    const clickedOutsideTrigger = trigger && !trigger.contains(event.target);
    const clickedOutsideDropdown = dropdown && !dropdown.contains(event.target);

    if (clickedOutsideTrigger && clickedOutsideDropdown) {
        isOpen.value = false;
        searchQuery.value = "";
    }
};

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            isOpen.value = false;
            searchQuery.value = "";
        }
    }
);

watch(
    () => searchQuery.value,
    (query) => {
        emit("search", query);
    }
);

watch(
    () => isOpen.value,
    (open) => {
        if (!open) {
            searchQuery.value = "";
        }
    }
);

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
    window.addEventListener("scroll", calculateDropdownPosition, true);
    window.addEventListener("resize", calculateDropdownPosition);
});

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside);
    window.removeEventListener("scroll", calculateDropdownPosition, true);
    window.removeEventListener("resize", calculateDropdownPosition);
});
</script>

<template>
    <div ref="triggerRef" class="relative" :class="props.containerClass || 'w-full'">
        <!-- Trigger Button -->
        <button
            ref="triggerRef"
            type="button"
            :disabled="props.disabled"
            @click="toggleDropdown"
            class="block w-full p-3 pr-10 rounded-lg border text-sm transition-all duration-300 outline-none"
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

        <!-- Dropdown Menu (Inline or Portal) -->
        <Teleport to="body" v-if="props.usePortal && isOpen">
            <div
                ref="dropdownRef"
                class="fixed z-[9999] overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-lg animate-fadeIn"
                :style="{
                    top: dropdownPosition.top + 'px',
                    left: dropdownPosition.left + 'px',
                    width: dropdownPosition.width + 'px',
                }"
            >
                <!-- Search Input -->
                <div
                    v-if="props.searchable"
                    class="p-3 border-b border-gray-100"
                >
                    <div class="relative">
                        <svg
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                        <input
                            ref="searchInputRef"
                            type="text"
                            :value="searchQuery"
                            @input="(e) => (searchQuery = e.target.value)"
                            :placeholder="searchPlaceholder"
                            class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none transition-all"
                        />
                        <button
                            v-if="searchQuery"
                            type="button"
                            @click="searchQuery = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- No Results Message -->
                <div
                    v-if="!hasSearchResults"
                    class="p-4 text-center text-sm text-gray-500"
                >
                    <svg
                        class="w-8 h-8 mx-auto text-gray-300 mb-2"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <p>Aucun résultat trouvé</p>
                </div>

                <!-- Options List -->
                <div
                    v-else
                    class="max-h-60 overflow-y-auto"
                >
                    <div
                        v-for="opt in filteredOptions"
                        :key="opt[valueKey]"
                        @click="selectOption(opt[valueKey])"
                        class="flex cursor-pointer items-center px-3 py-3 text-sm text-gray-700 transition-colors hover:bg-[#F8FAFC] border-b border-gray-50 last:border-b-0"
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
                            class="h-4 w-4 text-[#062121] flex-shrink-0 ml-2"
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

                <!-- Results Count Footer -->
                <div
                    v-if="hasSearchResults && searchQuery"
                    class="px-3 py-2 bg-gray-50 border-t border-gray-100 text-xs text-gray-500"
                >
                    {{ filteredOptions.length }} résultat(s)
                </div>
            </div>
        </Teleport>

        <!-- Inline Dropdown (fallback) -->
        <div
            v-else-if="!props.usePortal && isOpen && !props.disabled"
            class="absolute left-0 z-50 mt-1 w-full overflow-hidden rounded-lg border border-[#E2E8F0] bg-white shadow-lg animate-fadeIn"
        >
            <!-- Search Input -->
            <div
                v-if="props.searchable"
                class="p-3 border-b border-gray-100"
            >
                <div class="relative">
                    <svg
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    <input
                        ref="searchInputRef"
                        type="text"
                        :value="searchQuery"
                        @input="(e) => (searchQuery = e.target.value)"
                        :placeholder="searchPlaceholder"
                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-[#C5F82A] focus:ring-[3px] focus:ring-[#C5F82A]/20 outline-none transition-all"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        @click="searchQuery = ''"
                        class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- No Results Message -->
            <div
                v-if="!hasSearchResults"
                class="p-4 text-center text-sm text-gray-500"
            >
                <svg
                    class="w-8 h-8 mx-auto text-gray-300 mb-2"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p>Aucun résultat trouvé</p>
            </div>

            <!-- Options List -->
            <div
                v-else
                class="max-h-60 overflow-y-auto"
            >
                <div
                    v-for="opt in filteredOptions"
                    :key="opt[valueKey]"
                    @click="selectOption(opt[valueKey])"
                    class="flex cursor-pointer items-center px-3 py-3 text-sm text-gray-700 transition-colors hover:bg-[#F8FAFC] border-b border-gray-50 last:border-b-0"
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
                        class="h-4 w-4 text-[#062121] flex-shrink-0 ml-2"
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

            <!-- Results Count Footer -->
            <div
                v-if="hasSearchResults && searchQuery"
                class="px-3 py-2 bg-gray-50 border-t border-gray-100 text-xs text-gray-500"
            >
                {{ filteredOptions.length }} résultat(s)
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

/* Custom scrollbar for the dropdown */
.max-h-60.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.max-h-60.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 0 0 8px 0;
}

.max-h-60.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.max-h-60.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>
