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

        /* Hide scrollbar for tabs */
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

            <!-- TAB SWITCHER (Kirim / Chiqim / Tarix / Tasdiqlash) -->
            <div class="bg-slate-200/70 p-1 rounded-2xl flex items-center gap-1 shadow-inner shrink-0">
                <button
                    type="button"
                    @click="switchTab('income')"
                    class="flex-1 py-2 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'income' ? 'bg-emerald-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                >
                    <span>🟢</span>
                    <span>Kirim</span>
                </button>

                <button
                    type="button"
                    @click="switchTab('expense')"
                    class="flex-1 py-2 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'expense' ? 'bg-rose-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                >
                    <span>🔴</span>
                    <span>Chiqim</span>
                </button>

                <button
                    type="button"
                    @click="switchTab('history')"
                    class="flex-1 py-2 px-2 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    :class="activeTab === 'history' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                >
                    <span>📜</span>
                    <span>Tarix</span>
                </button>

                <button
                    v-if="user?.is_admin"
                    type="button"
                    @click="switchTab('pending')"
                    class="flex-1 py-2 px-1.5 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1 cursor-pointer relative"
                    :class="activeTab === 'pending' ? 'bg-orange-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                >
                    <span>⏳</span>
                    <span>Tasdiq</span>
                    <span v-if="pendingCount > 0" class="px-1.5 py-0.2 bg-red-600 text-white text-[9px] rounded-full font-black animate-pulse">
                        @{{ pendingCount }}
                    </span>
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
                        <span class="font-mono" :class="form.type === 'income' ? 'text-emerald-600' : 'text-rose-600'">
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

                <!-- 2. PAYMENT METHOD & REGISTER -->
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
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500 cursor-pointer"
                        >
                            <option value="" disabled>-- Kategoriyani tanlang --</option>
                            <option v-for="cat in filteredCategories" :key="cat.id" :value="cat.id">
                                @{{ cat.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- 3. TARGET & RELATION SELECTION (Collapsible / Dynamic) -->
                <div class="bg-white rounded-3xl p-4 border border-slate-200/90 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-black text-slate-500 uppercase tracking-wider">
                            Yo'nalish / Kimdan - Kimga
                        </span>
                        <span class="text-[10px] text-slate-400 font-medium">(Ixtiyoriy)</span>
                    </div>

                    <!-- Target Type Selector Chips -->
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1">
                        <button
                            v-for="t in targetOptions"
                            :key="t.key"
                            type="button"
                            @click="form.target_mode = (form.target_mode === t.key ? null : t.key); triggerHaptic('light')"
                            class="px-2.5 py-1.5 rounded-xl text-[11px] font-bold border transition-all shrink-0 flex items-center gap-1 cursor-pointer"
                            :class="form.target_mode === t.key ? 'bg-orange-500 text-white border-orange-600 shadow-xs font-black' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                        >
                            <span>@{{ t.icon }}</span>
                            <span>@{{ t.label }}</span>
                        </button>
                    </div>

                    <!-- Dynamic Dropdown based on Target Mode -->
                    <div v-if="form.target_mode === 'contract'" class="space-y-1">
                        <label class="block text-[11px] font-bold text-slate-600">Shartnoma / Xonadon / Mijoz:</label>
                        <select
                            v-model="form.contract_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900"
                        >
                            <option :value="null">-- Shartnomani tanlang --</option>
                            <option v-for="c in contracts" :key="c.id" :value="c.id">
                                @{{ c.label }}
                            </option>
                        </select>
                    </div>

                    <div v-else-if="form.target_mode === 'building'" class="space-y-1">
                        <label class="block text-[11px] font-bold text-slate-600">Obyekt / Bino:</label>
                        <select
                            v-model="form.building_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900"
                        >
                            <option :value="null">-- Binoni tanlang --</option>
                            <option v-for="b in buildings" :key="b.id" :value="b.id">
                                @{{ b.name }}
                            </option>
                        </select>
                    </div>

                    <div v-else-if="form.target_mode === 'partner'" class="space-y-1">
                        <label class="block text-[11px] font-bold text-slate-600">Hamkor:</label>
                        <select
                            v-model="form.partner_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900"
                        >
                            <option :value="null">-- Hamkorni tanlang --</option>
                            <option v-for="p in partners" :key="p.id" :value="p.id">
                                @{{ p.name }}
                            </option>
                        </select>
                    </div>

                    <div v-else-if="form.target_mode === 'supplier'" class="space-y-1">
                        <label class="block text-[11px] font-bold text-slate-600">Ta'minotchi:</label>
                        <select
                            v-model="form.supplier_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900"
                        >
                            <option :value="null">-- Ta'minotchini tanlang --</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">
                                @{{ s.name }}
                            </option>
                        </select>
                    </div>

                    <div v-else-if="form.target_mode === 'worker'" class="space-y-1">
                        <label class="block text-[11px] font-bold text-slate-600">Ishchi / Usta:</label>
                        <select
                            v-model="form.worker_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900"
                        >
                            <option :value="null">-- Ishchini tanlang --</option>
                            <option v-for="w in workers" :key="w.id" :value="w.id">
                                @{{ w.name }}
                            </option>
                        </select>
                    </div>

                    <div v-else-if="form.target_mode === 'brigade'" class="space-y-1">
                        <label class="block text-[11px] font-bold text-slate-600">Brigada:</label>
                        <select
                            v-model="form.brigade_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900"
                        >
                            <option :value="null">-- Brigadani tanlang --</option>
                            <option v-for="br in brigades" :key="br.id" :value="br.id">
                                @{{ br.name }}
                            </option>
                        </select>
                    </div>

                    <div v-else-if="form.target_mode === 'machine_owner'" class="space-y-1">
                        <label class="block text-[11px] font-bold text-slate-600">Texnika Egasi:</label>
                        <select
                            v-model="form.machine_owner_id"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900"
                        >
                            <option :value="null">-- Texnika egasini tanlang --</option>
                            <option v-for="mo in machineOwners" :key="mo.id" :value="mo.id">
                                @{{ mo.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- 4. NOTES & ATTACHMENT -->
                <div class="bg-white rounded-3xl p-4 border border-slate-200/90 shadow-sm space-y-3">
                    <div>
                        <label class="block text-[11px] font-black text-slate-500 uppercase tracking-wider mb-1">
                            Izoh / Tavsif
                        </label>
                        <textarea
                            v-model="form.notes"
                            rows="2"
                            placeholder="Qo'shimcha eslatma yoki ma'lumot..."
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] font-black text-slate-500 uppercase tracking-wider mb-1">
                            Kvitansiya / Chek Rasmi
                        </label>
                        <div class="flex items-center gap-2">
                            <label class="flex-1 py-2 px-3 bg-slate-50 hover:bg-slate-100 border border-dashed border-slate-300 rounded-xl text-xs font-bold text-slate-700 flex items-center justify-center gap-1.5 cursor-pointer">
                                <span>📷</span>
                                <span class="truncate">@{{ attachmentFileName || "Rasm yuklash" }}</span>
                                <input
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    @change="handleFileUpload"
                                    class="hidden"
                                />
                            </label>
                            <button
                                v-if="attachmentFileName"
                                type="button"
                                @click="clearAttachment"
                                class="w-8 h-8 rounded-xl bg-red-50 text-red-600 border border-red-200 flex items-center justify-center text-xs cursor-pointer font-bold"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SUBMIT BUTTON -->
                <div class="pt-1">
                    <button
                        type="button"
                        @click="submitTransaction"
                        :disabled="isSubmitting || form.amount <= 0 || !form.account_id || !form.category_id"
                        class="w-full py-3.5 px-4 rounded-2xl text-white font-black text-sm shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        :class="form.type === 'income' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-emerald-500/20' : 'bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 shadow-rose-500/20'"
                    >
                        <span>@{{ isSubmitting ? '⏳' : (form.type === 'income' ? '🟢' : '🔴') }}</span>
                        <span>@{{ isSubmitting ? 'Yuborilmoqda...' : (form.type === 'income' ? 'Kirimni Saqlash' : 'Chiqimni Saqlash') }}</span>
                    </button>
                </div>
            </div>

            <!-- ================= HISTORY TAB ================= -->
            <div v-show="activeTab === 'history'" class="space-y-2.5">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Oxirgi Tranzaksiyalar</h3>
                    <span class="text-[11px] font-mono text-slate-400">@{{ recentTransactions.length }} ta</span>
                </div>

                <div v-if="recentTransactions.length === 0" class="bg-white p-6 rounded-2xl border border-slate-200 text-center text-slate-400 text-xs">
                    Hozircha tranzaksiyalar mavjud emas.
                </div>

                <div
                    v-for="tx in recentTransactions"
                    :key="tx.id"
                    class="bg-white p-3 rounded-2xl border border-slate-200/90 shadow-xs space-y-1.5"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm">@{{ tx.type === 'income' ? '🟢' : '🔴' }}</span>
                            <span class="font-bold text-xs text-slate-900">@{{ tx.category }}</span>
                        </div>
                        <span
                            class="font-mono font-black text-xs"
                            :class="tx.type === 'income' ? 'text-emerald-600' : 'text-rose-600'"
                        >
                            @{{ tx.type === 'income' ? '+' : '-' }}@{{ formatNumber(tx.amount) }} @{{ tx.currency }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                        <span>🏦 @{{ tx.register }}</span>
                        <span
                            class="px-2 py-0.5 rounded-md font-bold text-[10px]"
                            :class="tx.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : (tx.status === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700')"
                        >
                            @{{ tx.status === 'approved' ? '✓ Tasdiqlangan' : (tx.status === 'pending' ? '⏳ Kutilmoqda' : '✕ Rad etilgan') }}
                        </span>
                    </div>

                    <div v-if="tx.notes" class="text-[11px] text-slate-600 italic bg-slate-50 p-1.5 rounded-lg">
                        "@{{ tx.notes }}"
                    </div>

                    <div class="text-[10px] text-slate-400 flex items-center justify-between pt-1 border-t border-slate-100">
                        <span>👤 @{{ tx.creator }}</span>
                        <span>📅 @{{ tx.date }}</span>
                    </div>
                </div>
            </div>

            <!-- ================= PENDING APPROVALS TAB ================= -->
            <div v-show="activeTab === 'pending'" class="space-y-2.5">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Tasdiq Kutayotganlar</h3>
                    <span class="text-[11px] font-mono text-orange-600 font-bold bg-orange-50 px-2 py-0.5 rounded">@{{ pendingTransactions.length }} ta</span>
                </div>

                <div v-if="pendingTransactions.length === 0" class="bg-white p-6 rounded-2xl border border-slate-200 text-center text-slate-400 text-xs">
                    🎉 Barcha tranzaksiyalar tasdiqlangan!
                </div>

                <div
                    v-for="ptx in pendingTransactions"
                    :key="ptx.id"
                    class="bg-white p-3.5 rounded-2xl border border-orange-200 shadow-xs space-y-2.5"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm">@{{ ptx.type === 'income' ? '🟢' : '🔴' }}</span>
                            <span class="font-bold text-xs text-slate-900">@{{ ptx.category }}</span>
                        </div>
                        <span
                            class="font-mono font-black text-sm"
                            :class="ptx.type === 'income' ? 'text-emerald-600' : 'text-rose-600'"
                        >
                            @{{ formatNumber(ptx.amount) }} @{{ ptx.currency }}
                        </span>
                    </div>

                    <div class="text-[11px] text-slate-600 space-y-0.5 bg-slate-50 p-2 rounded-xl">
                        <div>🏦 Kassa: <b>@{{ ptx.register }}</b></div>
                        <div>👤 Kiritdi: <b>@{{ ptx.creator }}</b></div>
                        <div>📅 Sana: @{{ ptx.date }}</div>
                        <div v-if="ptx.notes">💬 Izoh: <i>@{{ ptx.notes }}</i></div>
                        <div v-if="ptx.attachment_url" class="pt-1">
                            <a :href="ptx.attachment_url" target="_blank" class="text-blue-600 font-bold flex items-center gap-1">
                                📎 Chek rasmini ko'rish →
                            </a>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 pt-1">
                        <button
                            type="button"
                            @click="handleApproval(ptx.id, 'reject')"
                            :disabled="isProcessingApproval"
                            class="flex-1 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-bold rounded-xl border border-red-200 transition-all cursor-pointer"
                        >
                            ✕ Rad etish
                        </button>
                        <button
                            type="button"
                            @click="handleApproval(ptx.id, 'approve')"
                            :disabled="isProcessingApproval"
                            class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition-all cursor-pointer"
                        >
                            ✓ Tasdiqlash
                        </button>
                    </div>
                </div>
            </div>

        </main>

        <!-- ================= SUCCESS TOAST NOTIFICATION ================= -->
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

                const user = ref(null);
                const registers = ref([]);
                const categories = ref([]);
                const contracts = ref([]);
                const partners = ref([]);
                const buildings = ref([]);
                const suppliers = ref([]);
                const workers = ref([]);
                const brigades = ref([]);
                const machineOwners = ref([]);
                const recentTransactions = ref([]);
                const pendingTransactions = ref([]);
                const pendingCount = ref(0);

                const activeTab = ref('income'); // 'income', 'expense', 'history', 'pending'
                const isSubmitting = ref(false);
                const isProcessingApproval = ref(false);
                const toastMessage = ref(null);
                const toastType = ref('success');
                const attachmentFile = ref(null);
                const attachmentFileName = ref(null);

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

                // Target category tabs
                const targetOptions = [
                    { key: 'contract', label: 'Shartnoma / Mijoz', icon: '📑' },
                    { key: 'building', label: 'Obyekt / Bino', icon: '🏢' },
                    { key: 'partner', label: 'Hamkor', icon: '🤝' },
                    { key: 'supplier', label: "Ta'minotchi", icon: '🚚' },
                    { key: 'worker', label: 'Ishchi / Usta', icon: '👷' },
                    { key: 'brigade', label: 'Brigada', icon: '🏗️' },
                    { key: 'machine_owner', label: 'Texnika Egasi', icon: '🚜' },
                ];

                // Main Form State
                const form = ref({
                    type: 'income',
                    amount: 0,
                    currency: 'UZS',
                    payment_method: 'cash',
                    account_id: null,
                    category_id: null,
                    target_mode: null,
                    contract_id: null,
                    building_id: null,
                    partner_id: null,
                    supplier_id: null,
                    worker_id: null,
                    brigade_id: null,
                    machine_owner_id: null,
                    notes: ''
                });

                // Filter categories based on income / expense
                const filteredCategories = computed(() => {
                    return categories.value.filter(c => c.type === form.value.type || c.type === 'both');
                });

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

                // Switch Tab
                const switchTab = (tab) => {
                    activeTab.value = tab;
                    triggerHaptic('light');
                    if (tab === 'income' || tab === 'expense') {
                        form.value.type = tab;
                        // Select first matching category if available
                        const first = filteredCategories.value[0];
                        if (first) form.value.category_id = first.id;
                    } else if (tab === 'pending') {
                        loadPendingTransactions();
                    }
                };

                // File Upload
                const handleFileUpload = (e) => {
                    const file = e.target.files?.[0];
                    if (file) {
                        attachmentFile.value = file;
                        attachmentFileName.value = file.name;
                        triggerHaptic('medium');
                    }
                };

                const clearAttachment = () => {
                    attachmentFile.value = null;
                    attachmentFileName.value = null;
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
                        partners.value = res.partners || [];
                        buildings.value = res.buildings || [];
                        suppliers.value = res.suppliers || [];
                        workers.value = res.workers || [];
                        brigades.value = res.brigades || [];
                        machineOwners.value = res.machine_owners || [];
                        recentTransactions.value = res.recent_transactions || [];
                        pendingCount.value = res.pending_count || 0;

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

                // Load Pending Transactions
                const loadPendingTransactions = async () => {
                    if (!chatId.value) return;
                    try {
                        const res = await fetch(`/miniapp/api/pending?chat_id=${chatId.value}`);
                        const data = await res.json();
                        if (data.ok) {
                            pendingTransactions.value = data.transactions || [];
                            pendingCount.value = pendingTransactions.value.length;
                        }
                    } catch(err) {}
                };

                // Handle Approval (Approve / Reject)
                const handleApproval = async (id, action) => {
                    if (isProcessingApproval.value) return;
                    isProcessingApproval.value = true;
                    try {
                        const res = await fetch('/miniapp/api/approve', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                chat_id: chatId.value,
                                transaction_id: id,
                                action: action
                            })
                        });
                        const data = await res.json();
                        if (data.ok) {
                            showToast(data.message, 'success');
                            loadPendingTransactions();
                        } else {
                            showToast(data.error || "Xatolik yuz berdi", 'error');
                        }
                    } catch(err) {
                        showToast("Server xatosi", 'error');
                    } finally {
                        isProcessingApproval.value = false;
                    }
                };

                // Submit Transaction
                const submitTransaction = async () => {
                    if (form.value.amount <= 0 || !form.value.account_id || !form.value.category_id) {
                        showToast("Iltimos, summa, kassa va kategoriyani tanlang", 'warning');
                        return;
                    }

                    isSubmitting.value = true;

                    try {
                        const formData = new FormData();
                        formData.append('chat_id', chatId.value);
                        formData.append('type', form.value.type);
                        formData.append('amount', form.value.amount);
                        formData.append('currency', form.value.currency);
                        formData.append('payment_method', form.value.payment_method);
                        formData.append('account_id', form.value.account_id);
                        formData.append('category_id', form.value.category_id);
                        
                        if (form.value.target_mode === 'contract' && form.value.contract_id) {
                            formData.append('contract_id', form.value.contract_id);
                        } else if (form.value.target_mode === 'building' && form.value.building_id) {
                            formData.append('building_id', form.value.building_id);
                        } else if (form.value.target_mode === 'partner' && form.value.partner_id) {
                            formData.append('partner_id', form.value.partner_id);
                        } else if (form.value.target_mode === 'supplier' && form.value.supplier_id) {
                            formData.append('supplier_id', form.value.supplier_id);
                        } else if (form.value.target_mode === 'worker' && form.value.worker_id) {
                            formData.append('worker_id', form.value.worker_id);
                        } else if (form.value.target_mode === 'brigade' && form.value.brigade_id) {
                            formData.append('brigade_id', form.value.brigade_id);
                        } else if (form.value.target_mode === 'machine_owner' && form.value.machine_owner_id) {
                            formData.append('machine_owner_id', form.value.machine_owner_id);
                        }

                        if (form.value.notes) {
                            formData.append('notes', form.value.notes);
                        }

                        if (attachmentFile.value) {
                            formData.append('attachment', attachmentFile.value);
                        }

                        const response = await fetch('/miniapp/api/transactions', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        const res = await response.json();

                        if (res.ok) {
                            showToast(res.message, 'success');
                            
                            // Reset amount and notes
                            form.value.amount = 0;
                            form.value.notes = '';
                            form.value.target_mode = null;
                            clearAttachment();

                            // Refresh history
                            initApp();
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

                    initApp();
                });

                return {
                    initialized,
                    loading,
                    errorMessage,
                    chatId,
                    user,
                    registers,
                    categories,
                    filteredCategories,
                    contracts,
                    partners,
                    buildings,
                    suppliers,
                    workers,
                    brigades,
                    machineOwners,
                    recentTransactions,
                    pendingTransactions,
                    pendingCount,
                    activeTab,
                    quickAmounts,
                    targetOptions,
                    form,
                    isSubmitting,
                    isProcessingApproval,
                    toastMessage,
                    toastType,
                    attachmentFileName,
                    formatNumber,
                    addAmount,
                    switchTab,
                    handleFileUpload,
                    clearAttachment,
                    initApp,
                    handleApproval,
                    submitTransaction,
                    triggerHaptic
                };
            }
        }).mount('#app');
    </script>
</body>
</html>
