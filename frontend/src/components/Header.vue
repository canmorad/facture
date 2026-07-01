<script setup>
import { Link } from "@inertiajs/vue3";
import { ref } from "vue";
import logoUrl from "@/../images/logo-main.png";

const isMenuOpen = ref(false);

const scrollToSection = (e, targetId) => {
    e.preventDefault();
    const target = document.querySelector(targetId);
    if (target) {
        target.scrollIntoView({ behavior: "smooth" });
        isMenuOpen.value = false;
    }
};
</script>

<template>
    <header
        class="fixed top-0 left-0 w-full py-[15px] bg-[#C5F82A] z-[1000] font-['Inter',sans-serif]"
    >
        <div
            class="w-[90%] max-w-[1200px] m-0 mx-auto flex justify-between items-center"
        >
            <div class="header-logo">
                <img
                    :src="logoUrl"
                    alt="Logo"
                    class="h-[45px] w-auto object-contain"
                />
            </div>

            <nav class="navigation-menu relative">
                <span
                    @click="isMenuOpen = true"
                    class="block min-[1001px]:hidden text-[24px] cursor-pointer text-[#062121]"
                >
                    <i class="fa-solid fa-bars-staggered"></i>
                </span>

                <div
                    @click="isMenuOpen = false"
                    :class="isMenuOpen ? 'block' : 'hidden'"
                    class="min-[1001px]:hidden text-[22px] cursor-pointer text-white fixed top-[25px] right-[25px] z-[2001]"
                >
                    <i class="fa-solid fa-x"></i>
                </div>

                <ul
                    :class="isMenuOpen ? 'right-0' : '-right-full'"
                    class="flex list-none items-center gap-[25px] m-0 p-0 transition-[right] duration-[0.35s] ease-in-out z-[2000] max-[1000px]:flex-col max-[1000px]:bg-[#062121] max-[1000px]:w-[300px] max-[1000px]:fixed max-[1000px]:items-start max-[1000px]:top-0 max-[1000px]:h-screen max-[1000px]:p-[90px_25px]"
                >
                    <li
                        class="navigation-item max-[1000px]:w-full max-[1000px]:border-b max-[1000px]:border-[#C5F82A]/40 max-[1000px]:pb-2.5"
                    >
                        <Link
                            :href="route('accueil')"
                            class="no-underline text-[#062121] text-[15px] font-medium hover:text-white transition-colors duration-200 max-[1000px]:text-white max-[1000px]:text-[16px] max-[1000px]:hover:text-[#C5F82A]"
                        >
                            Accueil
                        </Link>
                    </li>

                    <li
                        class="navigation-item max-[1000px]:w-full max-[1000px]:border-b max-[1000px]:border-[#C5F82A]/40 max-[1000px]:pb-2.5"
                    >
                        <Link
                            :href="route('login.create')"
                            class="no-underline text-[#062121] text-[15px] font-medium hover:text-white transition-colors duration-200 max-[1000px]:text-white max-[1000px]:text-[16px] max-[1000px]:hover:text-[#C5F82A]"
                        >
                            Voir les collectes
                        </Link>
                    </li>

                    <li
                        class="navigation-item max-[1000px]:w-full max-[1000px]:border-b max-[1000px]:border-[#C5F82A]/40 max-[1000px]:pb-2.5"
                    >
                        <a
                            href="#missions"
                            @click="scrollToSection($event, '#missions')"
                            class="no-underline text-[#062121] text-[15px] font-medium hover:text-white transition-colors duration-200 max-[1000px]:text-white max-[1000px]:text-[16px] max-[1000px]:hover:text-[#C5F82A]"
                        >
                            Qui sommes-nous ?
                        </a>
                    </li>

                    <li
                        class="navigation-item max-[1000px]:w-full max-[1000px]:border-b max-[1000px]:border-[#C5F82A]/40 max-[1000px]:pb-2.5"
                    >
                        <a
                            href="#faq"
                            @click="scrollToSection($event, '#faq')"
                            class="no-underline text-[#062121] text-[15px] font-medium hover:text-white transition-colors duration-200 max-[1000px]:text-white max-[1000px]:text-[16px] max-[1000px]:hover:text-[#C5F82A]"
                        >
                            Comment ça marche ?
                        </a>
                    </li>

                    <li
                        class="navigation-item max-[1000px]:w-full max-[1000px]:pb-2.5"
                    >
                        <Link
                            :href="route('login.create')"
                            class="no-underline text-[#062121] text-[15px] font-medium hover:text-white transition-colors duration-200 max-[1000px]:text-white max-[1000px]:text-[16px] max-[1000px]:hover:text-[#C5F82A]"
                        >
                            Se connecter
                        </Link>
                    </li>

                    <li
                        class="navigation-item max-[1000px]:w-full max-[1000px]:flex max-[1000px]:justify-center max-[1000px]:mt-[100px]"
                    >
                        <Link
                            :href="route('login.create')"
                            class="p-[12px_30px] rounded-[10px] border-none font-semibold cursor-pointer text-[14px] inline-flex items-center gap-[8px] transition-all duration-300 bg-[#062121] text-white hover:bg-opacity-90 max-[1000px]:bg-[#C5F82A] max-[1000px]:text-[#062121] max-[1000px]:hover:text-white"
                        >
                            Démarrer une cagnotte
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
</template>
