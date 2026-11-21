<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Sher-Dor Moliya | Mini App</title>

    <!-- Telegram WebApp SDK -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Vue 3 CDN -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>

    <style>
        :root {
            --tg-theme-bg-color: #f8fafc;
            --tg-theme-text-color: #0f172a;
            --tg-theme-hint-color: #64748b;
            --tg-theme-link-color: #f97316;
            --tg-theme-button-color: #f97316;
            --tg-theme-button-text-color: #ffffff;
            --tg-theme-secondary-bg-color: #ffffff;
        }

        body {
            background-color: var(--tg-theme-bg-color, #f8fafc);
            color: var(--tg-theme-text-color, #0f172a);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        /* Hide scrollbar for pills */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="min-h-screen antialiased flex flex-col pb-safe">

    <div id="app" v-cloak class="flex-1 flex flex-col max-w-lg mx-auto w-full min-h-screen">

        <!-- ================= TOP HEADER ================= -->
        <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200/80 px-4 py-3 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-orange-500 to-amber-500 text-white flex items-center justify-center font-black text-sm shadow-sm">
                    🦁
                </div>
                <div>
                    <h1 class="text-xs font-black text-slate-900 leading-tight">Sher-Dor Moliya</h1>
                    <p class="text-[10px] text-slate-500 font-medium truncate max-w-[170px]" v-if="user">
                        👤 @{{ user.name }}
                    </p>
                    <p class="text-[10px] text-slate-400 font-medium" v-else>Yuklanmoqda...</p>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    Onlayn
                </span>
                <button
                    type="button"
                    @click="initApp"
                    :disabled="loading"
                    class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-xs text-slate-600 transition-colors cursor-pointer"
                    title="Yangilash"
                >
                    🔄
                </button>
            </div>
        </header>

        <!-- ================= LOADING SCREEN ================= -->
        <div v-if="loading && !initialized" class="flex-1 flex flex-col items-center justify-center p-6 text-center space-y-3">
            <div class="w-10 h-10 border-3 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="text-xs font-bold text-slate-600">Telegram ma'lumotlari yuklanmoqda...</p>
        </div>

        <!-- ================= ERROR SCREEN ================= -->
        <div v-else-if="errorMessage" class="flex-1 flex flex-col items-center justify-center p-6 text-center space-y-4">
            <div class="w-16 h-16 rounded-3xl bg-red-50 text-red-500 flex items-center justify-center text-3xl font-bold border border-red-200 shadow-sm">
                ⚠️
            </div>
            <div class="space-y-1">
                <h3 class="text-sm font-black text-slate-900">Ruxsat berilmadi</h3>
                <p class="text-xs text-slate-600 leading-relaxed max-w-xs">@{{ errorMessage }}</p>
            </div>
            <div class="bg-slate-100 px-3.5 py-2 rounded-xl text-xs font-mono font-bold text-slate-700">
                Sizning Chat ID: <code>@{{ chatId || 'aniqlanmadi' }}</code>
            </div>
            <button
                @click="initApp"
                class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-xs font-black shadow-md shadow-orange-500/20 cursor-pointer transition-all"
            >
                Qayta urinish
            </button>
        </div>

        <!-- ================= MAIN APP CONTENT ================= -->
        <main v-else class="flex-1 flex flex-col p-3 space-y-3">

            <!-- TAB SWITCHER (Kirim / Chiqim / Tarix) -->
            <div class="bg-slate-200/70 p-1 rounded-2xl flex items-center gap-1.5 shadow-inner shrink-0">
                <button
                    type="button"
                    @click="switchTab('income')"
                    class="flex-1 py-2.5 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'income' ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : 'text-slate-600 hover:text-slate-900'"
                >
                    <span class="text-sm">🟢</span>
                    <span>Kirim</span>
                </button>

                <button
                    type="button"
                    @click="switchTab('expense')"
                    class="flex-1 py-2.5 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'expense' ? 'bg-rose-500 text-white shadow-md shadow-rose-500/20' : 'text-slate-600 hover:text-slate-900'"
                >
                    <span class="text-sm">🔴</span>
                    <span>Chiqim</span>
                </button>

                <button
                    type="button"
                    @click="switchTab('history')"
                    class="flex-1 py-2.5 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'history' ? 'bg-white text-slate-900 shadow-md' : 'text-slate-600 hover:text-slate-900'"
                >
                    <span class="text-sm">📜</span>
                    <span>Tarix</span>
                </button>
            </div>

            <!-- ================= FORM (KIRIM / CHIQIM) ================= -->
            <div v-show="activeTab === 'income' || activeTab === 'expense'" class="space-y-3">

                <!-- 1. SUMMA CARD (Hero Input) -->
                <div class="bg-white rounded-3xl p-4 border border-slate-200/90 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-black uppercase tracking-wider" :class="form.type === 'income' ? 'text-emerald-600' : 'text-rose-600'">
                            @{{ form.type === 'income' ? '🟢 Kirim Summasi' : '🔴 Chiqim Summasi' }}
                        </span>
                        
                        <!-- Currency Selector -->
                        <div class="bg-slate-100 p-0.5 rounded-xl flex items-center gap-0.5 border border-slate-200">
                            <button
                                type="button"
                                @click="form.currency = 'UZS'; triggerHaptic('light')"
                                class="px-2.5 py-1 rounded-lg text-[11px] font-black transition-all cursor-pointer"
                                :class="form.currency === 'UZS' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500'"
                            >
                                UZS
                            </button>
                            <button
                                type="button"
                                @click="form.currency = 'USD'; triggerHaptic('light')"
                                class="px-2.5 py-1 rounded-lg text-[11px] font-black transition-all cursor-pointer"
                                :class="form.currency === 'USD' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500'"
                            >
                                USD ($)
                            </button>
                        </div>
                    </div>

                    <!-- Large Amount Input Display -->
                    <div class="relative flex items-center">
                        <input
                            type="number"
                            v-model.number="form.amount"
                            placeholder="0"
                            min="0"
                            step="any"
                            class="w-full text-2xl font-mono font-black text-slate-900 bg-slate-50 border border-slate-200 rounded-2xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:bg-white transition-all text-right"
                        />
                        <span class="absolute left-3 text-xs font-bold text-slate-400">
                            @{{ form.currency }}
                        </span>
                    </div>

                    <!-- Humanized Formatted Amount -->
                    <div class="flex items-center justify-between text-xs font-bold px-1" v-if="form.amount > 0">
                        <span class="text-slate-400 font-medium">Miqdor:</span>
                        <span class="font-mono font-black text-sm" :class="form.type === 'income' ? 'text-emerald-600' : 'text-rose-600'">
                            @{{ formatNumber(form.amount) }} @{{ form.currency }}
                        </span>
                    </div>

                    <!-- Quick Increment Pills -->
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-1">
                        <button
                            v-for="inc in quickAmounts"
                            :key="inc.label"
                            type="button"
                            @click="addAmount(inc.val)"
                            class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-700 text-[11px] font-bold rounded-xl shrink-0 transition-all border border-slate-200 cursor-pointer"
                        >
                            +@{{ inc.label }}
                        </button>
                        <button
                            type="button"
                            @click="form.amount = 0; triggerHaptic('warning')"
                            class="px-2 py-1 bg-red-50 hover:bg-red-100 active:scale-95 text-red-600 text-[11px] font-bold rounded-xl shrink-0 transition-all border border-red-200 cursor-pointer"
                        >
                            ✕ Tozalash
                        </button>
                    </div>
                </div>

                <!-- 2. PAYMENT METHOD, REGISTER & CATEGORY -->
                <div class="bg-white rounded-3xl p-4 border border-slate-200/90 shadow-sm space-y-3">
                    
                    <!-- Payment Method Pills -->
                    <div>
                        <label class="block text-[11px] font-black text-slate-500 uppercase tracking-wider mb-1.5">To'lov Usuli</label>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button
                                type="button"
                                @click="form.payment_method = 'cash'; triggerHaptic('light')"
                                class="py-2 px-1.5 rounded-xl border text-xs font-bold flex flex-col items-center justify-center gap-1 transition-all cursor-pointer"
                                :class="form.payment_method === 'cash' ? 'bg-orange-50 border-orange-500 text-orange-950 font-black ring-1 ring-orange-400' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                            >
                                <span class="text-base">💵</span>
                                <span>Naqd</span>
                            </button>

                            <button
                                type="button"
                                @click="form.payment_method = 'card'; triggerHaptic('light')"
                                class="py-2 px-1.5 rounded-xl border text-xs font-bold flex flex-col items-center justify-center gap-1 transition-all cursor-pointer"
                                :class="form.payment_method === 'card' ? 'bg-orange-50 border-orange-500 text-orange-950 font-black ring-1 ring-orange-400' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                            >
                                <span class="text-base">💳</span>
                                <span>Plastik / Karta</span>
                            </button>

                            <button
                                type="button"
                                @click="form.payment_method = 'bank_transfer'; triggerHaptic('light')"
                                class="py-2 px-1.5 rounded-xl border text-xs font-bold flex flex-col items-center justify-center gap-1 transition-all cursor-pointer"
                                :class="form.payment_method === 'bank_transfer' ? 'bg-orange-50 border-orange-500 text-orange-950 font-black ring-1 ring-orange-400' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
                            >
                                <span class="text-base">🏦</span>
                                <span>Hisob raqam</span>
                            </button>
                        </div>
                    </div>

                    <!-- Cash Register Selector -->
                    <div>
                        <label class="block text-[11px] font-black text-slate-500 uppercase tracking-wider mb-1">
                            Kassa / Hisob raqami <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.account_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option v-for="reg in registers" :key="reg.id" :value="reg.id">
                                @{{ reg.name }} (@{{ reg.currency }}) — @{{ formatNumber(reg.currency === 'USD' ? reg.balance_usd : reg.balance_uzs) }} @{{ reg.currency }}
                            </option>
                        </select>
                    </div>

                    <!-- Category Selector -->
                    <div>
                        <label class="block text-[11px] font-black text-slate-500 uppercase tracking-wider mb-1">
                            Kategoriya <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.category_id"
                            @change="onCategoryChange"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option value="" disabled>-- Kategoriyani tanlang --</option>
                            <option v-for="cat in filteredCategories" :key="cat.id" :value="cat.id">
                                @{{ cat.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- 3. DYNAMIC TARGET ENTITY SELECTION (Kategoriyaga mos odamlar/shartnomalar) -->
                <div v-if="activeTargetType" class="bg-white rounded-3xl p-4 border border-slate-200/90 shadow-sm space-y-3">
                    
                    <!-- A. Mijozga to'lov (Bekor qilingan shartnomalar va Ortiqcha to'lovlar birlashgan) -->
                    <div v-if="activeTargetType === 'client_payout' || activeTargetType === 'cancelled_contract' || activeTargetType === 'overpayment'" class="space-y-2.5">
                        <label class="block text-[11px] font-black text-slate-800 flex items-center justify-between uppercase tracking-wider">
                            <span>👥 Mijoz va Shartnoma <span class="text-rose-500">*</span></span>
                            <span class="text-[10px] text-slate-500 font-normal">(@{{ cancelledContracts.length + overpaidContracts.length }} ta mijoz)</span>
                        </label>
                        <select
                            v-model="form.contract_id"
                            @change="onClientPayoutSelect"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option :value="null">-- Shartnoma / Mijozni tanlang --</option>
                            
                            <!-- Bekor qilingan shartnomalar -->
                            <optgroup v-if="cancelledContracts.length > 0" label="🚫 Bekor qilingan shartnomalar">
                                <option v-for="c in cancelledContracts" :key="'canc_' + c.id" :value="c.id">
                                    [Bekor qilingan] @{{ c.label }}
                                </option>
                            </optgroup>

                            <!-- Ortiqcha to'lov qilgan mijozlar -->
                            <optgroup v-if="overpaidContracts.length > 0" label="💸 Ortiqcha to'lov qilgan mijozlar">
                                <option v-for="c in overpaidContracts" :key="'over_' + c.id" :value="c.id">
                                    [Ortiqcha to'lov] @{{ c.label }}
                                </option>
                            </optgroup>
                        </select>

                        <!-- 1. If Cancelled Contract selected -->
                        <div v-if="selectedCancelledContract" class="p-3.5 bg-rose-50/80 rounded-2xl border border-rose-200 text-[11px] text-rose-900 space-y-1.5 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 bg-rose-100 text-rose-700 text-[9px] font-black uppercase rounded-md tracking-wider">
                                    🚫 Bekor qilingan shartnoma
                                </span>
                                <span class="font-bold text-xs text-rose-800">№@{{ selectedCancelledContract.contract_number }}</span>
                            </div>
                            <div class="font-black text-xs text-slate-900 flex justify-between">
                                <span>👤 @{{ selectedCancelledContract.client_name }}</span>
                                <span>🏠 @{{ selectedCancelledContract.unit_number }}-xonadon</span>
                            </div>
                            <div class="flex justify-between text-[10px] text-rose-700">
                                <span>Jami qaytarilishi kerak: @{{ formatNumber(selectedCancelledContract.refund_target) }} UZS</span>
                                <span>To'langan: @{{ formatNumber(selectedCancelledContract.refunded_amount) }} UZS</span>
                            </div>
                            <div class="font-black text-xs text-rose-700 pt-1.5 border-t border-rose-200 flex justify-between">
                                <span>Qolgan qaytariladigan summa:</span>
                                <span>@{{ formatNumber(selectedCancelledContract.remaining_refund) }} UZS</span>
                            </div>
                        </div>

                        <!-- 2. If Overpaid Contract selected -->
                        <div v-else-if="selectedOverpaidContract" class="p-3.5 bg-amber-50/80 rounded-2xl border border-amber-200 text-[11px] text-amber-900 space-y-1.5 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-[9px] font-black uppercase rounded-md tracking-wider">
                                    💸 Ortiqcha to'lov
                                </span>
                                <span class="font-bold text-xs text-amber-900">№@{{ selectedOverpaidContract.contract_number }}</span>
                            </div>
                            <div class="font-black text-xs text-slate-900 flex justify-between">
                                <span>👤 @{{ selectedOverpaidContract.client_name }}</span>
                                <span>🏠 @{{ selectedOverpaidContract.unit_number }}-xonadon</span>
                            </div>
                            <div class="flex justify-between text-[10px] text-amber-700">
                                <span>Shartnoma qiymati: @{{ formatNumber(selectedOverpaidContract.total_amount) }} UZS</span>
                                <span>To'langan: @{{ formatNumber(selectedOverpaidContract.paid_amount) }} UZS</span>
                            </div>
                            <div class="font-black text-xs text-amber-800 pt-1.5 border-t border-amber-200 flex justify-between">
                                <span>Ortiqcha to'lov (qaytariladigan):</span>
                                <span>+@{{ formatNumber(selectedOverpaidContract.overpaid_amount) }} UZS</span>
                            </div>
                        </div>
                    </div>

                    <!-- C. Faol shartnomalar / Mijozlar -->
                    <div v-else-if="activeTargetType === 'contract'" class="space-y-1.5">
                        <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider">
                            📑 Shartnoma / Mijoz <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.contract_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option :value="null">-- Shartnoma / Mijozni tanlang --</option>
                            <option v-for="c in contracts" :key="c.id" :value="c.id">
                                @{{ c.label }}
                            </option>
                        </select>
                    </div>

                    <!-- D. Ishchilar / Ustalar -->
                    <div v-else-if="activeTargetType === 'worker'" class="space-y-1.5">
                        <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider">
                            👷 Ishchi / Usta <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.worker_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option :value="null">-- Ishchini tanlang --</option>
                            <option v-for="w in workers" :key="w.id" :value="w.id">
                                @{{ w.name }}
                            </option>
                        </select>
                    </div>

                    <!-- E. Ta'minotchilar -->
                    <div v-else-if="activeTargetType === 'supplier'" class="space-y-1.5">
                        <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider">
                            🚚 Ta'minotchi <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.supplier_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option :value="null">-- Ta'minotchini tanlang --</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                @{{ s.name }}
                            </option>
                        </select>
                    </div>

                    <!-- F. Hamkorlar / Ta'sischilar -->
                    <div v-else-if="activeTargetType === 'partner'" class="space-y-1.5">
                        <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider">
                            🤝 Hamkor / Ta'sischi <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.partner_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option :value="null">-- Hamkorni tanlang --</option>
                            <option v-for="p in partners" :key="p.id" :value="p.id">
                                @{{ p.name }}
                            </option>
                        </select>
                    </div>

                    <!-- G. Brigadalar -->
                    <div v-else-if="activeTargetType === 'brigade'" class="space-y-1.5">
                        <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider">
                            🏗️ Brigada <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.brigade_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option :value="null">-- Brigadani tanlang --</option>
                            <option v-for="br in brigades" :key="br.id" :value="br.id">
                                @{{ br.name }}
                            </option>
                        </select>
                    </div>

                    <!-- H. Texnika Egalari -->
                    <div v-else-if="activeTargetType === 'machine_owner'" class="space-y-1.5">
                        <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider">
                            🚜 Texnika Egasi <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.machine_owner_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option :value="null">-- Texnika egasini tanlang --</option>
                            <option v-for="mo in machineOwners" :key="mo.id" :value="mo.id">
                                @{{ mo.name }}
                            </option>
                        </select>
                    </div>

                    <!-- I. Obyekt / Bino -->
                    <div v-else-if="activeTargetType === 'building'" class="space-y-1.5">
                        <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider">
                            🏢 Obyekt / Bino <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="form.building_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option :value="null">-- Binoni tanlang --</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">
                                @{{ b.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- 4. SANA VA VAQT (Joriy vaqt yoki qo'lda kiritish) -->
                <div class="bg-white rounded-3xl p-4 border border-slate-200/90 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="text-[11px] font-black text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                            <span>🕒 Sana va Vaqt</span>
                            <span v-if="timeMode === 'now'" class="px-2 py-0.5 bg-emerald-50 text-emerald-700 text-[10px] rounded-full font-bold">Joriy vaqt</span>
                            <span v-else class="px-2 py-0.5 bg-amber-50 text-amber-700 text-[10px] rounded-full font-bold">Qo'lda kiritilgan</span>
                        </label>

                        <!-- Quick Switcher: Hozir vs Qo'lda -->
                        <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-xl border border-slate-200">
                            <button
                                type="button"
                                @click="setTimeToNow(); triggerHaptic('light')"
                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer flex items-center gap-1"
                                :class="timeMode === 'now' ? 'bg-white text-emerald-700 shadow-xs font-black' : 'text-slate-500 hover:text-slate-900'"
                            >
                                <span>⚡ Hozir</span>
                            </button>
                            <button
                                type="button"
                                @click="timeMode = 'manual'; triggerHaptic('light')"
                                class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition-all cursor-pointer flex items-center gap-1"
                                :class="timeMode === 'manual' ? 'bg-white text-amber-700 shadow-xs font-black' : 'text-slate-500 hover:text-slate-900'"
                            >
                                <span>📅 Qo'lda</span>
                            </button>
                        </div>
                    </div>

                    <!-- Datetime Input -->
                    <div>
                        <input
                            type="datetime-local"
                            v-model="form.transaction_datetime"
                            @input="timeMode = 'manual'"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer font-mono"
                        />
                    </div>

                    <!-- Quick Date presets -->
                    <div class="flex items-center gap-1.5 text-[11px]">
                        <button
                            type="button"
                            @click="setTimeToNow()"
                            class="flex-1 py-1.5 px-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-center transition-colors"
                        >
                            Hozirgi vaqt
                        </button>
                        <button
                            type="button"
                            @click="setTimeToYesterday()"
                            class="flex-1 py-1.5 px-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-center transition-colors"
                        >
                            Kecha
                        </button>
                    </div>
                </div>

                <!-- 5. SUBMIT BUTTON -->
                <div class="pt-2">
                    <button
                        type="button"
                        @click="submitTransaction"
                        :disabled="isSubmitting || form.amount <= 0 || !form.account_id || !form.category_id"
                        class="w-full py-4 px-4 rounded-2xl text-white font-black text-sm shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed active:scale-98"
                        :class="form.type === 'income' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-emerald-500/25' : 'bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 shadow-rose-500/25'"
                    >
                        <span>@{{ isSubmitting ? '⏳' : (form.type === 'income' ? '🟢' : '🔴') }}</span>
                        <span>@{{ isSubmitting ? 'Bazaga saqlanmoqda...' : (form.type === 'income' ? 'Kirimni Saqlash' : 'Chiqimni Saqlash') }}</span>
                    </button>
                </div>

            </div>

            <!-- ================= HISTORY TAB ================= -->
            <div v-show="activeTab === 'history'" class="space-y-3">
                <!-- Search & Filter Controls -->
                <div class="bg-white rounded-3xl p-4 border border-slate-200/90 shadow-sm space-y-3">
                    <!-- Search Input -->
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
                        <input
                            type="text"
                            v-model="historySearch"
                            placeholder="Qidirish (kategoriya, odam, kassa)..."
                            class="w-full pl-9 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        />
                        <button
                            v-if="historySearch"
                            type="button"
                            @click="historySearch = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-black"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Filter Sub-tabs: Hammasi / Kirimlar / Chiqimlar -->
                    <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
                        <button
                            type="button"
                            @click="historyFilter = 'all'; triggerHaptic('light')"
                            class="flex-1 py-1.5 text-xs font-bold rounded-lg transition-all text-center cursor-pointer"
                            :class="historyFilter === 'all' ? 'bg-white text-slate-900 shadow-xs font-black' : 'text-slate-500 hover:text-slate-900'"
                        >
                            Barchasi (@{{ filteredRecentTransactions.length }})
                        </button>
                        <button
                            type="button"
                            @click="historyFilter = 'income'; triggerHaptic('light')"
                            class="flex-1 py-1.5 text-xs font-bold rounded-lg transition-all text-center cursor-pointer"
                            :class="historyFilter === 'income' ? 'bg-emerald-500 text-white shadow-xs font-black' : 'text-slate-500 hover:text-slate-900'"
                        >
                            🟢 Kirimlar
                        </button>
                        <button
                            type="button"
                            @click="historyFilter = 'expense'; triggerHaptic('light')"
                            class="flex-1 py-1.5 text-xs font-bold rounded-lg transition-all text-center cursor-pointer"
                            :class="historyFilter === 'expense' ? 'bg-rose-500 text-white shadow-xs font-black' : 'text-slate-500 hover:text-slate-900'"
                        >
                            🔴 Chiqimlar
                        </button>
                    </div>
                </div>

                <!-- Transactions List -->
                <div v-if="filteredRecentTransactions.length === 0" class="bg-white p-8 rounded-3xl border border-slate-200/90 text-center space-y-2 shadow-sm">
                    <div class="text-3xl">📭</div>
                    <p class="text-xs font-bold text-slate-600">Tranzaksiyalar topilmadi</p>
                    <p class="text-[11px] text-slate-400">Tanlangan parametrlar bo'yicha amalyotlar mavjud emas.</p>
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="tx in filteredRecentTransactions"
                        :key="tx.id"
                        class="bg-white p-3.5 rounded-2xl border border-slate-200/90 shadow-sm space-y-2 hover:border-slate-300 transition-colors"
                    >
                        <!-- Top line: Category and Amount -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold"
                                      :class="tx.type === 'income' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                                    @{{ tx.type === 'income' ? '🟢' : '🔴' }}
                                </span>
                                <div>
                                    <div class="font-black text-xs text-slate-900 leading-tight">@{{ tx.category }}</div>
                                    <div v-if="tx.target_label" class="text-[10px] font-semibold text-slate-500 mt-0.5">
                                        @{{ tx.target_label }}
                                    </div>
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="font-mono font-black text-xs tracking-tight"
                                     :class="tx.type === 'income' ? 'text-emerald-600' : 'text-rose-600'">
                                    @{{ tx.type === 'income' ? '+' : '-' }}@{{ formatNumber(tx.amount) }} @{{ tx.currency }}
                                </div>
                                <div class="text-[10px] font-mono text-slate-400 mt-0.5">
                                    @{{ tx.datetime || tx.date }}
                                </div>
                            </div>
                        </div>

                        <!-- Bottom line: Register, Payment Method, Status -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[10px] text-slate-500">
                            <span class="font-medium">🏦 @{{ tx.register }}</span>
                            <span class="px-2 py-0.5 rounded-md font-bold"
                                  :class="tx.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : (tx.status === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700')">
                                @{{ tx.status === 'approved' ? '✓ Bajarildi' : (tx.status === 'pending' ? '⏳ Kutilmoqda' : '✕ Rad etildi') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <!-- ================= SUCCESS / ERROR TOAST ================= -->
        <div
            v-if="toastMessage"
            class="fixed bottom-6 left-4 right-4 z-50 p-3.5 rounded-2xl bg-slate-900/95 text-white text-xs font-bold shadow-2xl flex items-center justify-between backdrop-blur-md animate-in fade-in slide-in-from-bottom duration-200"
        >
            <div class="flex items-center gap-2">
                <span class="text-base">@{{ toastType === 'success' ? '✅' : '⚠️' }}</span>
                <span>@{{ toastMessage }}</span>
            </div>
            <button @click="toastMessage = null" class="text-slate-400 hover:text-white">&times;</button>
        </div>

    </div>

    <!-- ================= VUE 3 APPLICATION LOGIC ================= -->
    <script>
        const { createApp, ref, computed, onMounted } = Vue;

        createApp({
            setup() {
                const tg = window.Telegram?.WebApp;
                const initialized = ref(false);
                const loading = ref(true);
                const errorMessage = ref(null);
                const chatId = ref(null);

                const activeTab = ref('income'); // 'income', 'expense', 'history'
                const timeMode = ref('now'); // 'now', 'manual'

                const user = ref(null);
                const registers = ref([]);
                const categories = ref([]);
                const contracts = ref([]);
                const cancelledContracts = ref([]);
                const overpaidContracts = ref([]);
                const partners = ref([]);
                const buildings = ref([]);
                const suppliers = ref([]);
                const workers = ref([]);
                const brigades = ref([]);
                const machineOwners = ref([]);
                const recentTransactions = ref([]);
                const historySearch = ref('');
                const historyFilter = ref('all'); // 'all', 'income', 'expense'

                const isSubmitting = ref(false);
                const toastMessage = ref(null);
                const toastType = ref('success');

                // Main Form State
                const form = ref({
                    type: 'income',
                    amount: 0,
                    currency: 'UZS',
                    payment_method: 'cash',
                    account_id: null,
                    category_id: null,
                    transaction_datetime: '',
                    contract_id: null,
                    building_id: null,
                    partner_id: null,
                    supplier_id: null,
                    worker_id: null,
                    brigade_id: null,
                    machine_owner_id: null
                });

                // Quick Increment Amounts
                const quickAmounts = computed(() => {
                    if (form.value.currency === 'USD') {
                        return [
                            { label: '50$', val: 50 },
                            { label: '100$', val: 100 },
                            { label: '500$', val: 500 },
                            { label: '1,000$', val: 1000 },
                            { label: '5,000$', val: 5000 },
                        ];
                    }
                    return [
                        { label: '100 ming', val: 100000 },
                        { label: '500 ming', val: 500000 },
                        { label: '1 mln', val: 1000000 },
                        { label: '5 mln', val: 5000000 },
                        { label: '10 mln', val: 10000000 },
                        { label: '50 mln', val: 50000000 },
                    ];
                });

                // Filter categories based on income / expense
                const filteredCategories = computed(() => {
                    return categories.value.filter(c => c.type === form.value.type || c.type === 'both');
                });

                // Detect Active Target Type based on chosen Category
                const activeTargetType = computed(() => {
                    if (!form.value.category_id) return null;
                    const cat = categories.value.find(c => c.id === form.value.category_id);
                    if (!cat) return null;
                    return cat.target_type || null;
                });

                // Selected Cancelled Contract details
                const selectedCancelledContract = computed(() => {
                    if (!form.value.contract_id) return null;
                    return cancelledContracts.value.find(c => c.id === form.value.contract_id);
                });

                // Selected Overpaid Contract details
                const selectedOverpaidContract = computed(() => {
                    if (!form.value.contract_id) return null;
                    return overpaidContracts.value.find(c => c.id === form.value.contract_id);
                });

                // When Category Changes
                const onCategoryChange = () => {
                    triggerHaptic('light');
                    // Reset linked entity IDs
                    form.value.contract_id = null;
                    form.value.worker_id = null;
                    form.value.supplier_id = null;
                    form.value.partner_id = null;
                    form.value.brigade_id = null;
                    form.value.machine_owner_id = null;
                    form.value.building_id = null;
                };

                // When Client Payout Contract is selected (cancelled or overpaid)
                const onClientPayoutSelect = () => {
                    triggerHaptic('medium');
                    const canc = selectedCancelledContract.value;
                    if (canc) {
                        form.value.amount = canc.remaining_refund || canc.refund_target || 0;
                        return;
                    }
                    const over = selectedOverpaidContract.value;
                    if (over) {
                        form.value.amount = over.overpaid_amount || 0;
                        return;
                    }
                };

                // When Cancelled Contract is selected
                const onCancelledContractSelect = () => {
                    triggerHaptic('medium');
                    const c = selectedCancelledContract.value;
                    if (c) {
                        if (!form.value.amount || form.value.amount === 0) {
                            form.value.amount = c.remaining_refund || c.refund_target || 0;
                        }
                    }
                };

                // When Overpaid Contract is selected
                const onOverpaidContractSelect = () => {
                    triggerHaptic('medium');
                    const c = selectedOverpaidContract.value;
                    if (c) {
                        if (!form.value.amount || form.value.amount === 0) {
                            form.value.amount = c.overpaid_amount || 0;
                        }
                    }
                };

                // Helper: Haptic Feedback
                const triggerHaptic = (type = 'light') => {
                    try {
                        if (tg?.HapticFeedback) {
                            if (type === 'light' || type === 'medium' || type === 'heavy') {
                                tg.HapticFeedback.impactOccurred(type);
                            } else if (type === 'success' || type === 'error' || type === 'warning') {
                                tg.HapticFeedback.notificationOccurred(type);
                            }
                        }
                    } catch(e) {}
                };

                // Helper: Format Date for datetime-local input
                const formatDateForInput = (d) => {
                    const pad = (n) => String(n).padStart(2, '0');
                    const year = d.getFullYear();
                    const month = pad(d.getMonth() + 1);
                    const day = pad(d.getDate());
                    const hours = pad(d.getHours());
                    const minutes = pad(d.getMinutes());
                    return `${year}-${month}-${day}T${hours}:${minutes}`;
                };

                const setTimeToNow = () => {
                    form.value.transaction_datetime = formatDateForInput(new Date());
                    timeMode.value = 'now';
                    triggerHaptic('light');
                };

                const setTimeToYesterday = () => {
                    const yesterday = new Date();
                    yesterday.setDate(yesterday.getDate() - 1);
                    form.value.transaction_datetime = formatDateForInput(yesterday);
                    timeMode.value = 'manual';
                    triggerHaptic('light');
                };

                // Filtered Recent Transactions for History Tab
                const filteredRecentTransactions = computed(() => {
                    return recentTransactions.value.filter(t => {
                        // Type filter
                        if (historyFilter.value === 'income' && t.type !== 'income') return false;
                        if (historyFilter.value === 'expense' && t.type !== 'expense') return false;

                        // Search filter
                        if (historySearch.value && historySearch.value.trim() !== '') {
                            const q = historySearch.value.toLowerCase().trim();
                            const matchCat = (t.category || '').toLowerCase().includes(q);
                            const matchTarget = (t.target_label || '').toLowerCase().includes(q);
                            const matchReg = (t.register || '').toLowerCase().includes(q);
                            const matchNotes = (t.notes || '').toLowerCase().includes(q);
                            const matchAmount = String(t.amount || '').includes(q);
                            return matchCat || matchTarget || matchReg || matchNotes || matchAmount;
                        }
                        return true;
                    });
                });

                // Helper: Show Toast
                const showToast = (msg, type = 'success') => {
                    toastMessage.value = msg;
                    toastType.value = type;
                    triggerHaptic(type);
                    setTimeout(() => {
                        toastMessage.value = null;
                    }, 4000);
                };

                // Helper: Format Number
                const formatNumber = (val) => {
                    if (!val) return '0';
                    return Number(val).toLocaleString('uz-UZ');
                };

                // Add quick amount
                const addAmount = (val) => {
                    form.value.amount = (Number(form.value.amount) || 0) + val;
                    triggerHaptic('light');
                };

                // Switch Tab (Kirim / Chiqim / Tarix)
                const switchTab = (tab) => {
                    triggerHaptic('light');
                    activeTab.value = tab;
                    if (tab === 'income' || tab === 'expense') {
                        form.value.type = tab;
                        // Reset entity selections
                        form.value.contract_id = null;
                        form.value.worker_id = null;
                        form.value.supplier_id = null;
                        form.value.partner_id = null;
                        form.value.brigade_id = null;
                        form.value.machine_owner_id = null;
                        form.value.building_id = null;
                        // Auto-pick first matching category
                        const first = filteredCategories.value[0];
                        if (first) {
                            form.value.category_id = first.id;
                        }
                    }
                };

                // Initialize App
                const initApp = async () => {
                    loading.value = true;
                    errorMessage.value = null;

                    // Extract Chat ID from Telegram WebApp initDataUnsafe or URL query params
                    const tgUser = tg?.initDataUnsafe?.user;
                    const urlParams = new URLSearchParams(window.location.search);
                    const detectedChatId = tgUser?.id || urlParams.get('chat_id');

                    if (!detectedChatId) {
                        loading.value = false;
                        errorMessage.value = "Telegram Chat ID aniqlanmadi. Iltimos, Telegram boti orqali qayta kiring.";
                        return;
                    }

                    chatId.value = String(detectedChatId);

                    try {
                        const response = await fetch('/miniapp/api/init', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ chat_id: chatId.value })
                        });

                        const res = await response.json();

                        if (!res.ok) {
                            errorMessage.value = res.error || "Ulanishda xatolik yuz berdi.";
                            loading.value = false;
                            return;
                        }

                        user.value = res.user;
                        registers.value = res.registers || [];
                        categories.value = res.categories || [];
                        contracts.value = res.contracts || [];
                        cancelledContracts.value = res.cancelled_contracts || [];
                        overpaidContracts.value = res.overpaid_contracts || [];
                        partners.value = res.partners || [];
                        buildings.value = res.buildings || [];
                        suppliers.value = res.suppliers || [];
                        workers.value = res.workers || [];
                        brigades.value = res.brigades || [];
                        machineOwners.value = res.machine_owners || [];
                        recentTransactions.value = res.recent_transactions || [];

                        // Default account
                        if (registers.value.length > 0 && !form.value.account_id) {
                            form.value.account_id = registers.value[0].id;
                        }

                        // Default category
                        if (filteredCategories.value.length > 0 && !form.value.category_id) {
                            form.value.category_id = filteredCategories.value[0].id;
                        }

                        initialized.value = true;
                    } catch(err) {
                        console.error('Init error:', err);
                        errorMessage.value = "Serverga ulanishda xatolik yuz berdi.";
                    } finally {
                        loading.value = false;
                    }
                };

                // Submit Transaction (Darhol bazaga biriktiriladi)
                const submitTransaction = async () => {
                    if (form.value.amount <= 0 || !form.value.account_id || !form.value.category_id) {
                        showToast("Iltimos, summa, kassa va kategoriyani tanlang", 'warning');
                        return;
                    }

                    isSubmitting.value = true;

                    try {
                        const payload = {
                            chat_id: chatId.value,
                            type: form.value.type,
                            amount: form.value.amount,
                            currency: form.value.currency,
                            payment_method: form.value.payment_method,
                            account_id: form.value.account_id,
                            category_id: form.value.category_id,
                            transaction_datetime: form.value.transaction_datetime,
                            target_mode: activeTargetType.value
                        };

                        if (activeTargetType.value === 'client_payout' || activeTargetType.value === 'cancelled_contract' || activeTargetType.value === 'overpayment' || activeTargetType.value === 'contract') {
                            payload.contract_id = form.value.contract_id;
                        } else if (activeTargetType.value === 'worker') {
                            payload.worker_id = form.value.worker_id;
                        } else if (activeTargetType.value === 'supplier') {
                            payload.supplier_id = form.value.supplier_id;
                        } else if (activeTargetType.value === 'partner') {
                            payload.partner_id = form.value.partner_id;
                        } else if (activeTargetType.value === 'brigade') {
                            payload.brigade_id = form.value.brigade_id;
                        } else if (activeTargetType.value === 'machine_owner') {
                            payload.machine_owner_id = form.value.machine_owner_id;
                        } else if (activeTargetType.value === 'building') {
                            payload.building_id = form.value.building_id;
                        }

                        const response = await fetch('/miniapp/api/transactions', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        });

                        const res = await response.json();

                        if (res.ok) {
                            showToast(res.message, 'success');
                            
                            // Reset form fields
                            form.value.amount = 0;
                            setTimeToNow();
                            form.value.contract_id = null;
                            form.value.worker_id = null;
                            form.value.supplier_id = null;
                            form.value.partner_id = null;
                            form.value.brigade_id = null;
                            form.value.machine_owner_id = null;
                            form.value.building_id = null;

                            // Refresh balances and lists
                            await initApp();

                            // Show newly added transaction in history
                            activeTab.value = 'history';
                        } else {
                            showToast(res.error || res.message || "Xatolik yuz berdi", 'error');
                        }
                    } catch(err) {
                        console.error('Submit error:', err);
                        showToast("Serverga ulanishda xatolik", 'error');
                    } finally {
                        isSubmitting.value = false;
                    }
                };

                onMounted(() => {
                    // Telegram WebApp Setup
                    if (tg) {
                        try {
                            tg.ready();
                            tg.expand();
                            tg.enableClosingConfirmation();
                        } catch(e) {}
                    }

                    setTimeToNow();
                    initApp();
                });

                return {
                    initialized,
                    loading,
                    errorMessage,
                    chatId,
                    activeTab,
                    timeMode,
                    user,
                    registers,
                    categories,
                    filteredCategories,
                    activeTargetType,
                    contracts,
                    cancelledContracts,
                    overpaidContracts,
                    selectedCancelledContract,
                    selectedOverpaidContract,
                    partners,
                    buildings,
                    suppliers,
                    workers,
                    brigades,
                    machineOwners,
                    recentTransactions,
                    historySearch,
                    historyFilter,
                    filteredRecentTransactions,
                    quickAmounts,
                    form,
                    isSubmitting,
                    toastMessage,
                    toastType,
                    formatNumber,
                    addAmount,
                    switchTab,
                    setTimeToNow,
                    setTimeToYesterday,
                    onCategoryChange,
                    onClientPayoutSelect,
                    onCancelledContractSelect,
                    onOverpaidContractSelect,
                    initApp,
                    submitTransaction,
                    triggerHaptic
                };
            }
        }).mount('#app');
    </script>
</body>
</html>