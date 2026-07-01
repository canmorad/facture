<script setup>
import { ref } from "vue";
import { RouterLink } from "vue-router";
import logoUrl from "@/assets/images/logo.png";

const menuOpen = ref(false);

const openFaq = ref(null);
const toggleFaq = (index) => {
  openFaq.value = openFaq.value === index ? null : index;
};

const faqs = [
  {
    question: "Comment créer ma première facture ?",
    answer:
      "En quelques clics : renseignez les informations de votre client, ajoutez vos lignes de produits ou services, et votre facture est prête à être envoyée ou téléchargée en PDF.",
  },
  {
    question: "Puis-je personnaliser le modèle de facture ?",
    answer:
      "Oui, vous pouvez ajouter votre logo, choisir vos couleurs, et personnaliser les mentions légales. Chaque facture reflète l'identité visuelle de votre entreprise.",
  },
  {
    question: "Comment suivre les paiements en attente ?",
    answer:
      "Le tableau de bord vous montre en temps réel toutes vos factures : payées, en attente ou en retard. Vous recevez des rappels automatiques pour ne jamais rater un paiement.",
  },
  {
    question: "Mes données sont-elles sécurisées ?",
    answer:
      "Absolument. Nous utilisons un chiffrement SSL de bout en bout et des sauvegardes automatiques quotidiennes pour protéger toutes vos données financières.",
  },
  {
    question: "Puis-je exporter mes factures en PDF ou Excel ?",
    answer:
      "Oui, toutes vos factures sont exportables en PDF haute qualité ou en fichier Excel pour votre comptable, en un seul clic depuis votre espace.",
  },
];

const testimonials = [
  {
    text: "FactureX a complètement transformé ma gestion administrative. Je crée et envoie mes factures en moins de 2 minutes. Mes clients reçoivent des documents professionnels et je suis payé plus rapidement.",
    name: "Karim Bensalah",
    role: "Consultant freelance",
  },
  {
    text: "Avant, je perdais des heures sur Excel. Maintenant tout est automatisé. Le suivi des paiements en temps réel est une vraie révolution pour mon agence.",
    name: "Sofia Amrani",
    role: "Directrice d'agence",
  },
  {
    text: "L'interface est intuitive, les factures sont magnifiques et le support client est réactif. Je recommande FactureX à tous les entrepreneurs marocains.",
    name: "Yassine El Fassi",
    role: "E-commerçant",
  },
];

const features = [
  {
    icon: "fa-solid fa-file-invoice-dollar",
    title: "Facturation Instantanée",
    description:
      "Créez des factures professionnelles en quelques secondes. Modèles personnalisables, numérotation automatique et envoi par e-mail intégré.",
  },
  {
    icon: "fa-solid fa-credit-card",
    title: "Suivi des Paiements",
    description:
      "Visualisez l'état de chaque facture en temps réel. Relances automatiques pour les impayés et historique complet de vos transactions.",
  },
  {
    icon: "fa-solid fa-chart-line",
    title: "Rapports & Analyses",
    description:
      "Tableaux de bord clairs pour piloter votre activité : chiffre d'affaires, tendances, clients les plus actifs et prévisions de trésorerie.",
  },
];

const whyUs = [
  {
    icon: "fa-solid fa-bolt", 
    title: "Gain de Temps",
    description:
      "Automatisez la création de factures récurrentes, les relances de paiement et la génération de rapports. Concentrez-vous sur votre cœur de métier pendant que FactureX gère l'administratif.",
    link: "#",
  },
  {
    icon: "fa-solid fa-lock", 
    title: "Sécurité des Données",
    description:
      "Vos données financières sont chiffrées et sauvegardées automatiquement dans le cloud. Accédez à votre espace depuis n'importe quel appareil en toute sécurité, 24h/24.",
    link: "#",
  },
  {
    icon: "fa-solid fa-link", 
    title: "Intégration Facile",
    description:
      "Connectez FactureX à vos outils existants : comptabilité, CRM, paiement en ligne. Notre API ouverte s'adapte à votre environnement de travail sans effort technique.",
    link: "#",
  },
  {
    icon: "fa-solid fa-graduation-cap", 
    title: "Support Dédié",
    description:
      "Une équipe française et marocaine disponible par chat et téléphone. Des tutoriels vidéo, une base de connaissances complète et un accompagnement personnalisé à chaque étape.",
    link: "#",
  },
];

const recentInvoices = [
  {
    client: "Agence Nova",
    category: "Design & Développement",
    amount: "45 800 MAD",
    progress: 100,
    status: "Payée",
    daysLeft: null,
  },
  {
    client: "Groupe Atlas",
    category: "Conseil & Formation",
    amount: "28 500 MAD",
    progress: 70,
    status: "En cours",
    daysLeft: "12 jours",
  },
  {
    client: "StartUp Maroc",
    category: "Services Web",
    amount: "15 200 MAD",
    progress: 30,
    status: "En attente",
    daysLeft: "5 jours",
  },
];
</script>

<template>
  <div class="font-['Plus_Jakarta_Sans',sans-serif] bg-gray-50">
    <!-- ===== HEADER ===== -->
    <header class="fixed top-0 left-0 w-full z-50 py-4 bg-[#062121]">
      <div
        class="w-[90%] max-w-[1200px] mx-auto flex justify-between items-center"
      >
        <div class="flex items-center h-[60px]">
          <img
            :src="logoUrl"
            alt="FactureX"
            class="h-[120px] max-h-[120px] w-auto object-contain scale-150 origin-left contrast-125 brightness-110"
          />
        </div>

        <nav class="hidden lg:flex items-center gap-7">
          <RouterLink
            to="/"
            class="font-semibold text-[15px] text-white/70 transition-all duration-300 hover:text-[#C5F82A]"
            >Accueil</RouterLink
          >
          <a
            href="#features"
            class="font-medium text-[15px] text-white/60 transition-all duration-300 hover:text-[#C5F82A]"
            >Fonctionnalités</a
          >
          <a
            href="#why-us"
            class="font-medium text-[15px] text-white/60 transition-all duration-300 hover:text-[#C5F82A]"
            >Pourquoi nous ?</a
          >
          <a
            href="#faq"
            class="font-medium text-[15px] text-white/60 transition-all duration-300 hover:text-[#C5F82A]"
            >FAQ</a
          >
          <RouterLink
            to="/login"
            class="font-medium text-[15px] text-white/60 transition-all duration-300 hover:text-[#C5F82A]"
            >Se connecter</RouterLink
          >
          <RouterLink
            to="/register"
            class="font-bold text-[14px] px-6 py-3 rounded-lg bg-[#C5F82A] text-[#062121] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:bg-[#C5F82A]/90"
            >Commencer gratuitement</RouterLink
          >
        </nav>

        <button
          class="lg:hidden text-2xl text-white transition-all duration-300 hover:text-[#C5F82A]"
          @click="menuOpen = !menuOpen"
        >
          <span v-if="!menuOpen">☰</span>
          <span v-else>✕</span>
        </button>
      </div>

      <div
        v-if="menuOpen"
        class="lg:hidden absolute top-full left-0 w-full py-6 px-8 flex flex-col gap-5 shadow-xl bg-[#062121]"
      >
        <RouterLink
          to="/"
          class="font-semibold text-base pb-4 text-white/80 border-b border-white/10 transition-all duration-300 hover:text-[#C5F82A]"
          @click="menuOpen = false"
          >Accueil</RouterLink
        >
        <a
          href="#features"
          class="text-base pb-4 text-white/70 border-b border-white/10 transition-all duration-300 hover:text-[#C5F82A]"
          @click="menuOpen = false"
          >Fonctionnalités</a
        >
        <a
          href="#why-us"
          class="text-base pb-4 text-white/70 border-b border-white/10 transition-all duration-300 hover:text-[#C5F82A]"
          @click="menuOpen = false"
          >Pourquoi nous ?</a
        >
        <a
          href="#faq"
          class="text-base pb-4 text-white/70 border-b border-white/10 transition-all duration-300 hover:text-[#C5F82A]"
          @click="menuOpen = false"
          >FAQ</a
        >
        <RouterLink
          to="/login"
          class="text-base pb-4 text-white/70 border-b border-white/10 transition-all duration-300 hover:text-[#C5F82A]"
          @click="menuOpen = false"
          >Se connecter</RouterLink
        >
        <RouterLink
          to="/register"
          class="font-bold text-center py-3 rounded-lg mt-2 bg-[#C5F82A] text-[#062121] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg"
          @click="menuOpen = false"
          >Commencer gratuitement</RouterLink
        >
      </div>
    </header>

    <main class="pt-[74px]">
      <!-- ===== HERO ===== -->
      <section class="bg-gray-50 pt-16 pb-24">
        <div class="w-[90%] max-w-[1200px] mx-auto">
          <!-- Badge -->
          <div class="flex justify-center mb-8">
            
          </div>

          <!-- Headline -->
          <div class="text-center max-w-[780px] mx-auto mb-14">
            <h1
              class="font-extrabold leading-[1.1] mb-6 font-serif text-[clamp(40px,6vw,70px)] text-[#062121]"
            >
              La facturation simple<br />
              <em class="not-italic text-[#C5F82A]">pour les pros marocains</em>
            </h1>
            <p
              class="text-base sm:text-lg leading-relaxed mb-8 max-w-[560px] mx-auto text-gray-600"
            >
              Créez, envoyez et suivez vos factures en quelques secondes. Soyez
              payé plus rapidement et gardez le contrôle de votre trésorerie.
            </p>
            <div class="flex flex-wrap justify-center gap-4">
              <RouterLink
                to="/register"
                class="font-bold px-8 py-4 rounded-lg bg-[#062121] text-white shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:bg-[#062121]/90"
              >
                Créer une facture
              </RouterLink>
              <a
                href="#features"
                class="font-semibold px-8 py-4 rounded-lg border-2 border-[#062121]/20 bg-transparent text-[#062121] transition-all duration-300 hover:-translate-y-0.5  hover:text-[#C5F82A]"
              >
                Voir les fonctionnalités
              </a>
            </div>
          </div>

          <!-- Hero Cards Grid -->
          <section class="">
            <div class="w-[90%] max-w-[1200px] mx-auto">
              <div class="flex flex-wrap justify-between items-end gap-6 mb-14">
                <h2
                  class="text-4xl md:text-[42px] font-extrabold max-w-[550px] leading-[1.1] text-[#062121]"
                >
                  Facturez plus vite,
                  <em class="not-italic text-[#C5F82A]">
                    encaissez mieux
                  </em>
                </h2>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Card 1 -->
                <div
                  class="p-10 pt-14 justify-center items-center text-center min-h-[420px] bg-[#062121] transition-all duration-300 hover:scale-[1.02] hover:shadow-xl hover:ring-[3px] hover:ring-[#C5F82A] hover:ring-opacity-20 rounded-[30%_70%_70%_30%/_30%_30%_70%_70%]"
                >
                  <div>
                    <div
                      class="w-20 h-20 mx-auto mb-7 flex items-center justify-center text-4xl rounded-full bg-white/10"
                    >
                      <i class="fa-solid fa-file-invoice text-[#C5F82A]"></i>
                    </div>

                    <p
                      class="text-xs font-semibold uppercase tracking-widest mb-2 text-white/40"
                    >
                      Factures créées
                    </p>

                    <h3 class="text-4xl font-black mb-2 text-white">
                      12 483
                    </h3>

                    <p class="text-sm mb-8 text-white/40">ce mois</p>

                    <div class="rounded-2xl p-5 bg-white/5">
                      <div class="flex justify-between items-center mb-3">
                        <span class="text-sm text-white/50">
                          Entrepreneurs actifs
                        </span>

                        <span class="font-black text-xl text-[#C5F82A]">
                          +8 000
                        </span>
                      </div>

                      <div
                        class="h-2 rounded-full overflow-hidden bg-white/10"
                      >
                        <div
                          class="h-full rounded-full bg-[#C5F82A]"
                          style="width: 85%"
                        ></div>
                      </div>
                    </div>
                  </div>

                  <div class="mt-8">
                    <button
                      class="font-bold px-5 py-3 rounded-lg text-sm whitespace-nowrap bg-[#C5F82A] text-[#062121] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:bg-[#C5F82A]/90"
                    >
                      Rejoindre la communauté
                    </button>
                  </div>
                </div>

                <!-- Card 2 -->
                <div
                  class="p-10 pt-14 justify-center items-center text-center min-h-[420px] bg-white transition-all duration-300 hover:scale-[1.02] hover:shadow-xl hover:ring-[3px] hover:ring-[#C5F82A] hover:ring-opacity-20 rounded-[70%_30%_30%_70%/_58%_64%_36%_42%] shadow-sm"
                >
                  <div>
                    <div
                      class="w-20 h-20 mx-auto mb-7 flex items-center justify-center text-4xl rounded-full bg-[#062121]/5"
                    >
                      <i class="fa-solid fa-money-bill-trend-up text-[#062121]"></i>
                    </div>

                    <p
                      class="text-xs uppercase tracking-widest mb-2 text-gray-500"
                    >
                      Chiffre d'affaires
                    </p>

                    <h3 class="text-4xl font-black mb-2 text-[#062121]">
                      +34%
                    </h3>

                    <p class="text-sm mb-8 text-gray-500">vs mois dernier</p>

                    <div class="rounded-2xl p-5 bg-[#062121]/5">
                      <h3
                        class="font-black text-2xl leading-tight mb-3 font-serif text-[#062121]"
                      >
                        Facturez vite,<br />
                        encaissez mieux
                      </h3>

                      <p class="text-sm text-gray-600">
                        Gérez vos paiements et vos clients avec une interface
                        moderne et rapide.
                      </p>
                    </div>
                  </div>

                  <div class="mt-8">
                    <button
                      class="font-bold text-sm tracking-wide inline-flex items-center text-[#062121] transition-all duration-300 hover:text-[#C5F82A]"
                    >
                      En savoir plus
                      <i class="fa-solid fa-arrow-right-long ml-2"></i>
                    </button>
                  </div>
                </div>

                <!-- Card 3 -->
                <div
                  class="p-10 pt-14 justify-center items-center text-center min-h-[420px] bg-white transition-all duration-300 hover:scale-[1.02] hover:shadow-xl hover:ring-[3px] hover:ring-[#C5F82A] hover:ring-opacity-20 rounded-[46%_54%_31%_69%/_58%_41%_59%_42%] shadow-sm"
                >
                  <div>
                    <div
                      class="w-20 h-20 mx-auto mb-7 flex items-center justify-center text-4xl rounded-full bg-[#062121]/5"
                    >
                      <i class="fa-solid fa-rocket text-[#062121]"></i>
                    </div>

                    <p
                      class="text-xs uppercase tracking-widest mb-2 text-gray-500"
                    >
                      Dernière facture
                    </p>

                    <h2 class="text-3xl font-black mb-1 text-[#062121]">
                      Nova Digital
                    </h2>

                    <p class="text-sm mb-8 text-gray-500">
                      Design & Développement Web
                    </p>

                    <div class="rounded-2xl p-5 bg-[#062121]/5">
                      <div class="flex justify-between items-center mb-3">
                        <span class="text-sm text-gray-600">
                          Montant total
                        </span>

                        <span class="font-black text-xl text-[#062121]">
                          45 800 MAD
                        </span>
                      </div>

                      <div class="flex justify-between items-center mb-4">
                        <span class="text-sm text-gray-600"> Statut </span>

                        <span
                          class="text-xs font-bold px-3 py-1 rounded-lg bg-[#C5F82A]/20 text-[#062121]"
                        >
                          ✓ Payée
                        </span>
                      </div>

                      <div class="h-2 rounded-full overflow-hidden bg-[#062121]/10">
                        <div
                          class="h-full rounded-full bg-[#C5F82A]"
                          style="width: 100%"
                        ></div>
                      </div>
                    </div>
                  </div>

                  <div class="mt-8">
                    <span
                      class="font-bold text-sm cursor-pointer inline-flex items-center text-[#062121] transition-all duration-300 hover:text-[#C5F82A]"
                    >
                      Voir toutes les factures
                      <i class="fa-solid fa-arrow-right-long ml-2"></i>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </section>

      <!-- ===== FEATURES ===== -->
      <section id="features" class="py-20 bg-white">
        <div class="w-[90%] max-w-[1200px] mx-auto">
          <div class="flex flex-wrap justify-between items-start gap-10 mb-16">
            <div class="flex-1 min-w-[280px]">
              <p
                class="text-xs uppercase tracking-[0.2em] font-semibold mb-3 text-[#C5F82A]"
              >
                Nos fonctionnalités
              </p>
              <h2
                class="font-extrabold leading-[1.15] m-0 font-serif text-[#062121]"
                style="font-size: clamp(32px, 4vw, 46px)"
              >
                La <em class="not-italic text-[#C5F82A]">puissance</em><br />
                de la facturation moderne
              </h2>
            </div>
            <div class="flex-1 min-w-[280px] flex items-end">
              <p class="text-base leading-relaxed m-0 text-gray-600">
                Chaque jour, des centaines d'entrepreneurs perdent des heures
                sur leur gestion administrative. FactureX vous redonne ce
                temps précieux grâce à des outils pensés pour vous.
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-7">
            <div
              v-for="(f, i) in features"
              :key="i"
              class="rounded-3xl p-10 text-center bg-gray-50 border border-gray-200 transition-all duration-300 hover:scale-[1.02] hover:shadow-xl hover:ring-[3px] hover:ring-[#C5F82A] hover:ring-opacity-20"
            >
              <div
                class="w-20 h-20 mx-auto mb-7 flex items-center justify-center text-4xl rounded-full bg-[#062121]/5"
              >
                <i :class="f.icon" class="text-[#062121]"></i>
              </div>
              <h3
                class="text-2xl font-bold mb-3 font-serif text-[#062121]"
              >
                {{ f.title }}
              </h3>
              <p class="text-[15px] leading-relaxed m-0 text-gray-600">
                {{ f.description }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== IMPACT GLOBAL (Stats Banner) ===== -->
      <section class="py-16 bg-white">
        <div class="w-[90%] max-w-[1200px] mx-auto">
          <div
            class="rounded-[40px] py-20 px-6 text-center relative overflow-hidden bg-[#062121]"
          >
            <!-- Decorative -->
            <span
              class="hidden xl:block absolute left-[-15px] top-1/2 -translate-y-1/2 font-serif leading-none select-none pointer-events-none text-[400px] font-thin text-white/5"
            >
              {</span
            >
            <span
              class="hidden xl:block absolute right-[-15px] top-1/2 -translate-y-1/2 font-serif leading-none select-none pointer-events-none text-[400px] font-thin text-white/5"
              >}</span
            >

            <div class="max-w-[850px] mx-auto relative z-10">
              <p
                class="text-xs uppercase tracking-[0.2em] font-semibold mb-5 text-[#C5F82A]"
              >
                Notre impact
              </p>
              <h2
                class="font-extrabold leading-[1.2] mb-5 font-serif text-white"
                style="font-size: clamp(28px, 4vw, 42px)"
              >
                Ensemble, nous simplifions la gestion<br />financière des
                entreprises.
              </h2>
              <p class="text-lg mb-10 text-white/60">
                Chaque facture envoyée est un pas vers une entreprise plus
                organisée, plus crédible et plus rentable.
              </p>

              <div
                class="font-black tracking-tighter leading-none mb-8 font-serif text-[#C5F82A]"
                style="font-size: clamp(60px, 15vw, 130px)"
              >
                421 530+
              </div>

              <RouterLink
                to="/register"
                class="inline-flex items-center gap-2 font-bold text-lg px-12 py-5 rounded-lg bg-[#C5F82A] text-[#062121] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:bg-[#C5F82A]/90"
              >
                Rejoindre maintenant <span>→</span>
              </RouterLink>

              <p class="text-base mt-7 text-white/40">
                Des entrepreneurs font déjà confiance à FactureX
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== WHY US ===== -->
      <section id="why-us" class="py-20 bg-gray-50">
        <div class="w-[90%] max-w-[1200px] mx-auto">
          <div class="text-center max-w-[760px] mx-auto mb-16">
            <p
              class="text-xs uppercase tracking-[0.2em] font-semibold mb-3 text-[#C5F82A]"
            >
              Pourquoi nous
            </p>
            <h2
              class="font-extrabold leading-[1.2] mb-5 font-serif text-[#062121]"
              style="font-size: clamp(30px, 4vw, 44px)"
            >
              Pourquoi choisir FactureX ?
            </h2>
            <p class="text-base leading-relaxed text-gray-600">
              Nous avons conçu chaque fonctionnalité pour répondre aux vrais
              besoins des entrepreneurs marocains. Voici comment notre
              plateforme vous aide à mieux travailler :
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-7">
            <div
              v-for="(w, i) in whyUs"
              :key="i"
              class="p-10 rounded-3xl bg-white border border-gray-200 transition-all duration-300 hover:scale-[1.02] hover:shadow-xl hover:ring-[3px] hover:ring-[#C5F82A] hover:ring-opacity-20 group"
            >
              <div
                class="w-14 h-14 flex items-center justify-center text-xl mb-6 rounded-full bg-[#062121]/5 text-[#062121] transition-all duration-300 group-hover:bg-[#C5F82A] group-hover:text-[#062121]"
              >
                <i :class="w.icon"></i>
              </div>

              <h3
                class="text-2xl font-bold mb-3 font-serif text-[#062121]"
              >
                {{ w.title }}
              </h3>
              <p class="text-[15px] leading-relaxed mb-5 text-gray-600">
                {{ w.description }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== TESTIMONIALS ===== -->
      <section class="py-20 bg-white">
        <div class="w-[90%] max-w-[1200px] mx-auto">
          <div class="flex flex-wrap justify-between items-end gap-6 mb-14">
            <div>
              <p
                class="text-xs uppercase tracking-[0.2em] font-semibold mb-3 text-[#C5F82A]"
              >
                Témoignages
              </p>
              <h2
                class="font-extrabold leading-[1.1] max-w-[550px] font-serif text-[#062121]"
                style="font-size: clamp(28px, 4vw, 42px)"
              >
                Votre succès est notre meilleure
                <em class="not-italic text-[#C5F82A]">récompense</em>
              </h2>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
              v-for="(t, i) in testimonials"
              :key="i"
              class="p-10 pt-14 flex flex-col justify-between rounded-3xl bg-[#062121] transition-all duration-300 hover:scale-[1.02] hover:shadow-xl hover:ring-[3px] hover:ring-[#C5F82A] hover:ring-opacity-20"
            >
              <div>
                <div class="text-4xl mb-5 text-white/20">❝</div>
                <p
                  class="text-[17px] font-medium leading-relaxed mb-8 text-white/80"
                >
                  "{{ t.text }}"
                </p>
              </div>
              <div class="flex items-center gap-4">
                <div
                  class="w-12 h-12 rounded-full flex items-center justify-center font-black text-lg bg-[#C5F82A] text-[#062121]"
                >
                  {{ t.name.charAt(0) }}
                </div>
                <div>
                  <h4 class="font-bold text-[15px] m-0 text-white">
                    {{ t.name }}
                  </h4>
                  <span class="text-[13px] text-white/50">{{ t.role }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== FAQ ===== -->
      <section id="faq" class="py-20 bg-gray-50">
        <div class="w-[90%] max-w-[1200px] mx-auto">
          <div
            class="rounded-[20px] p-8 sm:p-14 relative overflow-hidden bg-[#062121]"
            style="clip-path: polygon(0 0, 93% 0, 100% 5%, 100% 100%, 0 100%)"
          >
            <div
              class="flex flex-wrap justify-between items-center gap-4 mb-12"
            >
              <div>
                <p
                  class="text-xs uppercase tracking-[0.2em] font-semibold mb-3 text-[#C5F82A]"
                >
                  FAQ
                </p>
                <h2
                  class="font-bold m-0 font-serif text-white"
                  style="font-size: clamp(24px, 3.5vw, 38px)"
                >
                  Questions Fréquemment Posées
                </h2>
              </div>
              <RouterLink
                to="/login"
                class="font-bold px-6 py-3 rounded-lg bg-[#C5F82A] text-[#062121] transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:bg-[#C5F82A]/90"
              >
                Contactez-nous
              </RouterLink>
            </div>

            <div>
              <div
                v-for="(faq, i) in faqs"
                :key="i"
                class="border-b border-white/10 last:border-none"
              >
                <button
                  class="w-full text-left py-6 flex justify-between items-center text-[17px] font-medium bg-transparent border-none cursor-pointer transition-all duration-300"
                  :style="{ color: openFaq === i ? '#C5F82A' : '#FFFFFF' }"
                  @click="toggleFaq(i)"
                >
                  <span>{{ faq.question }}</span>
                  <span
                    class="text-xl transition-transform duration-300 ml-4 shrink-0"
                    :class="openFaq === i ? 'rotate-45' : ''"
                    :style="{ color: openFaq === i ? '#C5F82A' : '#FFFFFF' }"
                    >+</span
                  >
                </button>
                <div
                  class="overflow-hidden transition-all duration-400"
                  :style="
                    openFaq === i
                      ? 'max-height: 200px; opacity: 1'
                      : 'max-height: 0; opacity: 0'
                  "
                >
                  <p
                    class="leading-relaxed pb-6 m-0 text-white/50"
                  >
                    {{ faq.answer }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <!-- ===== FOOTER ===== -->
    <footer
      class="mx-5 mb-5 pt-16 pb-8 bg-[#062121] rounded-[80px_30px_30px_30px]"
    >
      <div class="w-[90%] max-w-[1200px] mx-auto">
        <div class="flex flex-wrap justify-between items-start gap-10 pb-10">
          <div class="flex-1 min-w-[240px]">
            <div class="flex items-center h-[60px] mb-4">
              <img
                :src="logoUrl"
                alt="FactureX"
                class="h-[120px] max-h-[120px] w-auto object-contain scale-150 origin-left contrast-125 brightness-110"
              />
            </div>
            <h4 class="text-lg font-semibold mb-4 text-[#C5F82A]">
              Suivez-nous
            </h4>
            <div class="flex gap-3">
              <a
                href="#"
                class="w-10 h-10 flex items-center justify-center rounded-lg text-white/80 bg-white/5 transition-all duration-300 hover:bg-[#C5F82A] hover:text-[#062121]"
              >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                  <path
                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"
                  />
                </svg>
              </a>
              <a
                href="#"
                class="w-10 h-10 flex items-center justify-center rounded-lg text-white/80 bg-white/5 transition-all duration-300 hover:bg-[#C5F82A] hover:text-[#062121]"
              >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                  <path
                    d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"
                  />
                </svg>
              </a>
              <a
                href="#"
                class="w-10 h-10 flex items-center justify-center rounded-lg text-white/80 bg-white/5 transition-all duration-300 hover:bg-[#C5F82A] hover:text-[#062121]"
              >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                  <path
                    d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"
                  />
                </svg>
              </a>
            </div>
          </div>

          <nav class="flex flex-wrap gap-14 flex-[2] justify-end">
            <div>
              <h3 class="font-semibold text-lg mb-5 text-[#C5F82A]">Produit</h3>
              <ul class="list-none p-0 m-0 space-y-3">
                <li>
                  <a
                    href="#"
                    class="text-sm no-underline text-white/40 transition-all duration-300 hover:text-[#C5F82A]"
                  >
                    Fonctionnalités
                  </a>
                </li>
                <li>
                  <a
                    href="#"
                    class="text-sm no-underline text-white/40 transition-all duration-300 hover:text-[#C5F82A]"
                  >
                    Tarifs
                  </a>
                </li>
                <li>
                  <a
                    href="#"
                    class="text-sm no-underline text-white/40 transition-all duration-300 hover:text-[#C5F82A]"
                  >
                    Templates
                  </a>
                </li>
                <li>
                  <a
                    href="#"
                    class="text-sm no-underline text-white/40 transition-all duration-300 hover:text-[#C5F82A]"
                  >
                    Intégrations
                  </a>
                </li>
              </ul>
            </div>

            <div>
              <h3 class="font-semibold text-lg mb-5 text-[#C5F82A]">
                À propos
              </h3>
              <ul class="list-none p-0 m-0 space-y-3">
                <li>
                  <a
                    href="#"
                    class="text-sm no-underline text-white/40 transition-all duration-300 hover:text-[#C5F82A]"
                  >
                    Notre mission
                  </a>
                </li>
                <li>
                  <a
                    href="#"
                    class="text-sm no-underline text-white/40 transition-all duration-300 hover:text-[#C5F82A]"
                  >
                    Blog
                  </a>
                </li>
                <li>
                  <a
                    href="#"
                    class="text-sm no-underline text-white/40 transition-all duration-300 hover:text-[#C5F82A]"
                  >
                    Témoignages
                  </a>
                </li>
              </ul>
            </div>

            <div>
              <h3 class="font-semibold text-lg mb-5 text-[#C5F82A]">Contact</h3>
              <ul class="list-none p-0 m-0 space-y-3">
                <li>
                  <a
                    href="mailto:contact@FactureX.ma"
                    class="text-sm no-underline text-white/40 transition-all duration-300 hover:text-[#C5F82A]"
                  >
                    contact@FactureX.ma
                  </a>
                </li>
                <li>
                  <a
                    href="tel:212500000000"
                    class="text-sm no-underline text-white/40 transition-all duration-300 hover:text-[#C5F82A]"
                  >
                    +212 5XX XX XX XX
                  </a>
                </li>
                <li>
                  <span class="text-sm text-white/40"
                    >Casablanca, Maroc</span
                  >
                </li>
              </ul>
            </div>
          </nav>
        </div>

        <div
          class="border-t border-white/10 pt-7 flex flex-wrap justify-between items-center gap-4 text-[13px] text-white/30"
        >
          <p class="m-0">© 2026 FactureX. Tous droits réservés.</p>
          <div class="flex gap-5">
            <a
              href="#"
              class="no-underline text-white/30 transition-all duration-300 hover:text-[#C5F82A]"
            >
              Conditions générales
            </a>
            <a
              href="#"
              class="no-underline text-white/30 transition-all duration-300 hover:text-[#C5F82A]"
            >
              Politique de confidentialité
            </a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>