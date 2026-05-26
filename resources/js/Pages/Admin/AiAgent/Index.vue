<template>
    <AdminLayout>
        <Head title="AI Agent - Smart SMS" />

        <div class="p-6 h-screen flex flex-col bg-[#020205] overflow-y-auto custom-scrollbar">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 mb-8 relative z-10">
                <div class="relative hidden">
                    <!-- Global header replaces this -->
                </div>
                <div class="flex flex-wrap items-center gap-2 md:gap-4 w-full md:w-auto mt-4 md:mt-0">
                    <div v-if="isConnected" class="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 border border-emerald-200 text-emerald-600 rounded text-xs font-mono">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 shadow-sm animate-pulse"></span>
                        Ulangan (Online)
                    </div>
                    <div v-else class="flex items-center gap-2 px-3 py-1.5 bg-red-500/10 border border-red-200 text-red-500 rounded text-xs font-mono">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        Ulanmagan (Xatolik)
                    </div>

                    <button v-if="isConnected" @click="showTestSmsModal = true" class="px-4 py-2 bg-neon-purple/20 border border-neon-purple/50 text-neon-purple font-mono text-xs rounded shadow-sm hover:bg-neon-purple/40 transition-all">
                        📲 Test SMS Yuborish
                    </button>
                    <button @click="showSettingsModal = true" class="px-4 py-2 bg-gray-900/50 border border-gray-300 text-gray-900 font-mono text-xs rounded shadow-sm hover:border-white transition-all">
                        ⚙️ Eskiz.uz Sozlamalari
                    </button>
                </div>
            </div>

            <!-- Block Selector -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Avto-xabar bloki -->
                <div @click="openRuleModal('auto')" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-blue-200 bg-[#05050A] hover:bg-blue-50 cursor-pointer transition-all group relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-blue-50 blur-[50px] group-hover:bg-blue-50 transition-all"></div>
                    <div class="text-4xl mb-4">⚡</div>
                    <h3 class="text-xl font-display text-blue-600 mb-2">Avto-xabar Bloki</h3>
                    <p class="text-xs text-gray-500 font-mono leading-relaxed">Mijoz kiritilganda yoki shartnoma tuzilganda avtomatik ravishda xush kelibsiz yoki tasdiqlash xabarini yuborish.</p>
                </div>

                <!-- Qarzdorlarga eslatma bloki -->
                <div @click="openRuleModal('debtor')" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-neon-magenta/30 bg-[#05050A] hover:bg-indigo-50 cursor-pointer transition-all group relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-indigo-50 blur-[50px] group-hover:bg-indigo-50 transition-all"></div>
                    <div class="text-4xl mb-4">💰</div>
                    <h3 class="text-xl font-display text-indigo-600 mb-2">Qarzdorlarga Eslatma</h3>
                    <p class="text-xs text-gray-500 font-mono leading-relaxed">To'lov muddati o'tgan mijozlarga rejalashtirilgan vaqtda avtomatik ravishda qarzdorlik eslatmasini yuborish.</p>
                </div>

                <!-- Aksiya/Reklama bloki -->
                <div @click="openPromoModal" class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-amber-200 bg-[#05050A] hover:bg-amber-50 cursor-pointer transition-all group relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-amber-50 blur-[50px] group-hover:bg-amber-50 transition-all"></div>
                    <div class="text-4xl mb-4">📢</div>
                    <h3 class="text-xl font-display text-amber-600 mb-2">Aksiya va Rasilka</h3>
                    <p class="text-xs text-gray-500 font-mono leading-relaxed">Tashqi bazadan Excel/CSV yuklab, ularga ommaviy reklama va aksiya SMS larini yuborish.</p>
                </div>

            </div>

            <!-- Dashboard Content -->
            <div class="flex-1 grid grid-cols-1 lg:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 min-h-0">
                <!-- Shablonlar Listi -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl border border-gray-200 flex flex-col bg-[#05050A]">
                    <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-900/50">
                        <h3 class="text-lg font-display text-gray-900">📝 SMS Shablonlar</h3>
                        <button @click="openTemplateModal" class="text-xs text-neon-purple hover:text-gray-900 transition-colors font-mono">+ Yangi</button>
                    </div>
                    <div class="p-4 flex-1 overflow-y-auto custom-scrollbar space-y-3">
                        <div v-for="t in templates" :key="t.id" class="p-3 bg-gray-900/50 border border-gray-100 rounded-lg group relative">
                            <div class="absolute right-2 top-2 opacity-0 group-hover:opacity-100 flex gap-2 transition-opacity">
                                <button @click="editTemplate(t)" class="text-[10px] bg-white/10 hover:bg-white/20 p-1 rounded">✏️</button>
                                <button @click="deleteTemplate(t.id)" class="text-[10px] bg-indigo-50 hover:bg-indigo-50 text-indigo-600 p-1 rounded">🗑️</button>
                            </div>
                            <div class="text-xs text-gray-900 font-bold mb-1">{{ t.name }}</div>
                            <div class="text-[10px] text-gray-500 font-mono line-clamp-2 pr-8">{{ t.content }}</div>
                        </div>
                    </div>
                </div>

                <!-- Faol Qoidalar (Rules) -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl border border-gray-200 flex flex-col bg-[#05050A]">
                    <div class="p-4 border-b border-gray-200 bg-gray-900/50">
                        <h3 class="text-lg font-display text-gray-900">⚙️ Faol Qoidalar</h3>
                    </div>
                    <div class="p-4 flex-1 overflow-y-auto custom-scrollbar space-y-3">
                        <div v-for="r in rules" :key="r.id" class="p-3 bg-gray-900/50 border border-gray-100 rounded-lg flex items-center justify-between group relative">
                            <div class="absolute right-8 top-2 opacity-0 group-hover:opacity-100 flex gap-2 transition-opacity">
                                <button @click="editRule(r)" class="text-[10px] bg-white/10 hover:bg-white/20 p-1 rounded">✏️</button>
                                <button @click="deleteRule(r.id)" class="text-[10px] bg-indigo-50 hover:bg-indigo-50 text-indigo-600 p-1 rounded">🗑️</button>
                            </div>
                            <div>
                                <div class="text-xs text-gray-900 font-bold mb-1">{{ r.name }}</div>
                                <div class="text-[10px] text-gray-500 font-mono">
                                    Tur: <span class="text-blue-600 uppercase">{{ r.event_trigger }}</span> | Vaqt: {{ r.schedule_time }}
                                </div>
                            </div>
                            <div class="w-2 h-2 rounded-full" :class="r.is_active ? 'bg-emerald-600 shadow-sm' : 'bg-red-500'"></div>
                        </div>
                    </div>
                </div>

                <!-- Jurnal -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl border border-gray-200 flex flex-col bg-[#05050A]">
                    <div class="p-4 border-b border-gray-200 bg-gray-900/50">
                        <h3 class="text-lg font-display text-gray-900">📊 Jo'natish Jurnali</h3>
                    </div>
                    <div class="p-4 flex-1 overflow-y-auto custom-scrollbar space-y-2">
                        <div v-for="log in logs" :key="log.id" class="p-2 border-b border-gray-100 last:border-0 flex justify-between items-center">
                            <div>
                                <div class="text-xs text-gray-900 font-mono">{{ log.phone_number }}</div>
                                <div class="text-[10px] text-gray-500 truncate max-w-[150px]">{{ log.message }}</div>
                            </div>
                            <span :class="{
                                'text-emerald-600': log.status === 'DELIVRD',
                                'text-amber-600': log.status === 'pending' || log.status === 'sent' || log.status === 'pending_batch',
                                'text-indigo-600': log.status === 'UNDELIV' || log.status === 'failed'
                            }" class="text-[10px] font-mono font-bold uppercase">{{ log.status }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Modal -->
        <Modal :show="showSettingsModal" @close="showSettingsModal = false">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-white/30 bg-[#05050A]">
                <h2 class="text-2xl font-display text-gray-900 mb-4">Eskiz.uz Sozlamalari</h2>
                <form @submit.prevent="submitSettings" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">Email (Login)</label>
                        <input v-model="settingsForm.email" type="email" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all" :class="{'border-red-500': settingsForm.errors.email}" required>
                        <div v-if="settingsForm.errors.email" class="text-red-500 text-xs mt-1 font-bold bg-red-500/10 p-2 rounded">{{ settingsForm.errors.email }}</div>
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">Parol</label>
                        <input v-model="settingsForm.password" type="password" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all" :class="{'border-red-500': settingsForm.errors.password}">
                        <div v-if="settingsForm.errors.password" class="text-red-500 text-xs mt-1">{{ settingsForm.errors.password }}</div>
                        <p class="text-[10px] text-gray-500 mt-1">* Parolni faqat o'zgartirmoqchi bo'lsangiz kiriting.</p>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showSettingsModal = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="settingsForm.processing" class="px-4 py-2 bg-white text-black font-bold font-mono text-xs rounded hover:bg-white/80 transition-colors">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Test SMS Modal -->
        <Modal :show="showTestSmsModal" @close="showTestSmsModal = false">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-neon-purple/30 bg-[#05050A]">
                <h2 class="text-2xl font-display text-neon-purple mb-4">Test SMS Yuborish</h2>
                <form @submit.prevent="submitTestSms" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">Telefon Raqam</label>
                        <input v-model="testSmsForm.phone" type="text" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all" placeholder="Masalan: 998901234567" required>
                        <p class="text-[10px] text-gray-500 mt-1">Sms yuboriladigan raqamni kiritng.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">Xabar Matni</label>
                        <textarea v-model="testSmsForm.message" rows="3" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all" placeholder="Test xabari..." required></textarea>
                        <p class="text-[10px] text-amber-600 mt-1 font-bold">⚠️ DIQQAT: Siz hozir Test rejimidasiz! Shartnoma tuzguningizga qadar FAQAT "Bu Eskiz dan test", "Это тест от Eskiz" yoki "This is test from Eskiz" so'zlaridan birini yubora olasiz. Boshqa so'z yozsangiz xatolik beradi!</p>
                    </div>
                    <div v-if="testSmsForm.errors.error" class="text-red-500 text-xs mt-2 font-bold bg-red-500/10 p-2 rounded">
                        Xatolik: {{ testSmsForm.errors.error }}
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showTestSmsModal = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="testSmsForm.processing" class="px-4 py-2 bg-neon-purple text-gray-900 font-bold font-mono text-xs rounded hover:bg-neon-purple/80 transition-colors">Yuborish</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Template Modal -->
        <Modal :show="showTemplateModal" @close="showTemplateModal = false">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-neon-purple/30 bg-[#05050A]">
                <h2 class="text-2xl font-display text-neon-purple mb-4">Yangi Shablon</h2>
                <form @submit.prevent="submitTemplate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">Shablon Nomi</label>
                        <input v-model="templateForm.name" type="text" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all" required>
                    </div>
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1 flex justify-between">
                            <span>SMS Matni</span>
                            <span :class="templateForm.content.length > 160 ? 'text-indigo-600' : 'text-emerald-600'">{{ templateForm.content.length }} / 160 belgi</span>
                        </label>
                        <textarea v-model="templateForm.content" rows="4" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-neon-purple focus:ring-1 focus:ring-neon-purple transition-all" required></textarea>
                        <p class="text-[10px] text-gray-500 mt-1">Dinamik teglardan foydalanishingiz mumkin: <span class="text-blue-600">[Ism]</span>, <span class="text-blue-600">[Familiya]</span>, <span class="text-blue-600">[Qarz]</span></p>
                        <p v-if="templateForm.content.length > 160" class="text-[10px] text-indigo-600 mt-1 font-bold">Diqqat! SMS 160 belgidan oshdi, u 2 ta SMS bo'lib ketishi mumkin va balansdan 2 barobar ko'p pul yechiladi.</p>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showTemplateModal = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="templateForm.processing || templateForm.content.length === 0" class="px-4 py-2 bg-neon-purple text-gray-900 font-bold font-mono text-xs rounded hover:bg-neon-purple/80 transition-colors">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Rule Modal (Auto/Debtor) -->
        <Modal :show="showRuleModal" @close="showRuleModal = false">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-white/30 bg-[#05050A]" :class="{'border-blue-200': ruleType === 'auto', 'border-neon-magenta/50': ruleType === 'debtor'}">
                <h2 class="text-2xl font-display mb-4" :class="{'text-blue-600': ruleType === 'auto', 'text-indigo-600': ruleType === 'debtor'}">
                    {{ ruleType === 'auto' ? 'Avto-xabar Sozlamalari' : 'Qarzdorlarga Eslatma Sozlamalari' }}
                </h2>
                <form @submit.prevent="submitRule" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">Qoida Nomi</label>
                        <input v-model="ruleForm.name" type="text" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all" required placeholder="Masalan: Har Dushanba Eslatma">
                    </div>
                    
                    <div v-if="ruleType === 'debtor'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Hafta kunlari (masalan: 1,3,5)</label>
                            <input v-model="ruleForm.schedule_days" type="text" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all" placeholder="1 = Dushanba, 7 = Yakshanba">
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Yuborish Vaqti</label>
                            <input v-model="ruleForm.schedule_time" type="time" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono text-gray-500 mb-1">SMS Shablonni Tanlang</label>
                        <select v-model="ruleForm.template_id" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-white focus:ring-1 focus:ring-white transition-all" required>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" @click="showRuleModal = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('cancel') }}</button>
                        <button type="submit" :disabled="ruleForm.processing" class="px-4 py-2 text-black font-bold font-mono text-xs rounded hover:opacity-80 transition-all" :class="{'bg-blue-600 shadow-sm': ruleType === 'auto', 'bg-indigo-600 shadow-sm': ruleType === 'debtor'}">{{ $t('save') }}</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Promo/Audience Modal -->
        <Modal :show="showPromoModal" @close="showPromoModal = false">
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-amber-200 bg-[#05050A]">
                <h2 class="text-2xl font-display text-amber-600 mb-4">Aksiya va Ommaviy Rasilka</h2>
                
                <div class="space-y-6">
                    <!-- Setup Rule Section -->
                    <form @submit.prevent="submitPromoRule" class="space-y-4">
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-2">Auditoriyani Tanlang</label>
                            <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-all" :class="{'bg-amber-50 border-amber-200': promoRuleForm.audience_type === 'system_clients'}">
                                    <input type="radio" v-model="promoRuleForm.audience_type" value="system_clients" class="text-amber-600 focus:ring-neon-yellow bg-black border-gray-300">
                                    <span class="text-xs text-gray-900">Tizimdagi Mijozlar</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-all" :class="{'bg-amber-50 border-amber-200': promoRuleForm.audience_type === 'upload_file'}">
                                    <input type="radio" v-model="promoRuleForm.audience_type" value="upload_file" class="text-amber-600 focus:ring-neon-yellow bg-black border-gray-300">
                                    <span class="text-xs text-gray-900">Fayl Yuklash</span>
                                </label>
                                <label class="flex items-center gap-2 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-all" :class="{'bg-amber-50 border-amber-200': promoRuleForm.audience_type === 'manual_number'}">
                                    <input type="radio" v-model="promoRuleForm.audience_type" value="manual_number" class="text-amber-600 focus:ring-neon-yellow bg-black border-gray-300">
                                    <span class="text-xs text-gray-900">Raqam Kiritish</span>
                                </label>
                            </div>
                        </div>

                        <div v-if="promoRuleForm.audience_type === 'upload_file'" class="p-4 border border-dashed border-gray-300 rounded-xl bg-white/5 text-center">
                            <h3 class="text-sm font-mono text-gray-900 mb-2">Mijozlar Bazasini Yuklash (CSV)</h3>
                            <p class="text-xs text-gray-500 mb-4">Faqat bitta "Telefon Raqam" (998901234567) ustuni bo'lishi kerak.</p>
                            <input type="file" @change="promoRuleForm.file = $event.target.files[0]" accept=".csv" class="mx-auto block w-full max-w-xs text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-xs file:font-mono file:bg-white/10 file:text-gray-900 hover:file:bg-white/20 cursor-pointer">
                        </div>

                        <div v-if="promoRuleForm.audience_type === 'manual_number'">
                            <label class="block text-xs font-mono text-gray-500 mb-1">Telefon Raqam(lar)</label>
                            <input v-model="promoRuleForm.manual_phone" type="text" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-neon-yellow transition-all" placeholder="Masalan: 998901234567, 998912345678">
                        </div>

                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">Rasilka Nomi</label>
                            <input v-model="promoRuleForm.name" type="text" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-neon-yellow transition-all" required placeholder="Masalan: Yangi Yil Aksiyasi">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-1">Hafta kunlari (masalan: 1,3,5)</label>
                                <input v-model="promoRuleForm.schedule_days" type="text" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-neon-yellow transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-mono text-gray-500 mb-1">Yuborish Vaqti (Eng faol vaqt)</label>
                                <input v-model="promoRuleForm.schedule_time" type="time" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-neon-yellow transition-all" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-mono text-gray-500 mb-1">SMS Shablonni Tanlang</label>
                            <select v-model="promoRuleForm.template_id" class="w-full bg-gray-900/50 border border-gray-200 rounded px-3 py-2 text-gray-900 focus:border-amber-500 focus:ring-1 focus:ring-neon-yellow transition-all" required>
                                <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="showPromoModal = false" class="px-4 py-2 text-xs font-mono text-gray-500 hover:text-gray-900 transition-colors">{{ $t('close_button') }}</button>
                            <button type="submit" :disabled="promoRuleForm.processing" class="px-4 py-2 bg-amber-500 text-white font-bold font-mono text-xs rounded hover:bg-white transition-colors shadow-sm">Rasilkani Saqlash</button>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>

    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    templates: Array,
    rules: Array,
    campaigns: Array,
    settings: Object,
    logs: Array,
    isConnected: Boolean
});

const showSettingsModal = ref(false);
const settingsForm = useForm({
    email: props.settings?.email || '',
    password: ''
});

function submitSettings() {
    settingsForm.post(route('admin.ai_agents.settings'), {
        onSuccess: () => {
            showSettingsModal.value = false;
        }
    });
}

const showTestSmsModal = ref(false);
const testSmsForm = useForm({
    phone: '',
    message: 'Bu Eskiz dan test'
});

function submitTestSms() {
    testSmsForm.post(route('admin.ai_agents.test_sms'), {
        onSuccess: () => {
            showTestSmsModal.value = false;
            testSmsForm.reset('phone');
        }
    });
}

const showTemplateModal = ref(false);
const templateForm = useForm({
    id: null,
    name: '',
    content: ''
});

function openTemplateModal() {
    templateForm.reset();
    templateForm.id = null;
    showTemplateModal.value = true;
}

function editTemplate(t) {
    templateForm.id = t.id;
    templateForm.name = t.name;
    templateForm.content = t.content;
    showTemplateModal.value = true;
}

function deleteTemplate(id) {
    if (confirm("Rostdan ham o'chirmoqchimisiz?")) {
        router.delete(route('admin.ai_agents.template.delete', id));
    }
}

function submitTemplate() {
    templateForm.post(route('admin.ai_agents.template.save'), {
        onSuccess: () => {
            showTemplateModal.value = false;
            templateForm.reset();
        }
    });
}

const showRuleModal = ref(false);
const ruleType = ref('auto');
const ruleForm = useForm({
    id: null,
    name: '',
    event_trigger: '',
    template_id: '',
    schedule_time: '',
    schedule_days: ''
});

function openRuleModal(type) {
    ruleForm.reset();
    ruleForm.id = null;
    ruleType.value = type;
    ruleForm.event_trigger = type;
    if (type === 'auto') {
        ruleForm.schedule_time = 'immediate';
        ruleForm.schedule_days = '';
    } else {
        ruleForm.schedule_time = '10:00';
    }
    showRuleModal.value = true;
}

function editRule(r) {
    if (r.event_trigger === 'promo') {
        promoRuleForm.id = r.id;
        promoRuleForm.name = r.name;
        promoRuleForm.event_trigger = r.event_trigger;
        promoRuleForm.template_id = r.template_id;
        promoRuleForm.schedule_time = r.schedule_time;
        promoRuleForm.schedule_days = r.schedule_days;
        promoRuleForm.audience_type = r.audience_type;
        promoRuleForm.manual_phone = r.manual_phone;
        showPromoModal.value = true;
    } else {
        ruleType.value = r.event_trigger;
        ruleForm.id = r.id;
        ruleForm.name = r.name;
        ruleForm.event_trigger = r.event_trigger;
        ruleForm.template_id = r.template_id;
        ruleForm.schedule_time = r.schedule_time;
        ruleForm.schedule_days = r.schedule_days;
        showRuleModal.value = true;
    }
}

function deleteRule(id) {
    if (confirm("Rostdan ham o'chirmoqchimisiz?")) {
        router.delete(route('admin.ai_agents.rule.delete', id));
    }
}

function submitRule() {
    ruleForm.post(route('admin.ai_agents.rule.save'), {
        onSuccess: () => {
            showRuleModal.value = false;
            ruleForm.reset();
        }
    });
}

const showPromoModal = ref(false);
const promoRuleForm = useForm({
    id: null,
    name: '',
    event_trigger: 'promo',
    template_id: '',
    schedule_time: '18:00',
    schedule_days: '',
    audience_type: 'system_clients',
    manual_phone: '',
    file: null
});

function openPromoModal() {
    promoRuleForm.reset();
    promoRuleForm.id = null;
    showPromoModal.value = true;
}

function submitPromoRule() {
    promoRuleForm.post(route('admin.ai_agents.rule.save'), {
        onSuccess: () => {
            showPromoModal.value = false;
            promoRuleForm.reset();
        }
    });
}

const showEtlModal = ref(false);
function openEtlModal() {
    showEtlModal.value = true;
}
</script>
