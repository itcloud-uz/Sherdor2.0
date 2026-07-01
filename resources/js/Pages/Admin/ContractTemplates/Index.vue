<template>
    <AdminLayout>
        <Head title="Shartnoma Shablonlari (DOCX)" />

        <div class="p-6 min-h-screen flex flex-col bg-slate-50 overflow-y-auto custom-scrollbar">
            <!-- Full Page Overlay Loader -->
            <div v-if="isProcessing" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-md flex flex-col items-center justify-center transition-all animate-in fade-in duration-300">
                <div class="bg-white border border-gray-200 shadow-2xl rounded-2xl p-8 flex flex-col items-center justify-center space-y-6 max-w-sm mx-4 transform scale-95 transition-transform duration-300 animate-in zoom-in-95">
                    <!-- Spinning Rings -->
                    <div class="relative w-16 h-16">
                        <div class="absolute inset-0 border-4 border-blue-600/20 rounded-full"></div>
                        <div class="absolute inset-0 border-4 border-t-blue-600 rounded-full animate-spin"></div>
                    </div>
                    <div class="text-center space-y-2">
                        <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider font-mono">Tahlil qilinmoqda</h4>
                        <p class="text-[10px] text-gray-500 font-sans leading-relaxed">Hujjat matni, jadvallari va o'zgaruvchan kalit so'zlari skanerlanmoqda. Iltimos kuting...</p>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">📝 Shartnoma Shablonlari (DOCX)</h1>
                    <p class="text-xs text-gray-500 font-mono mt-1">Tayyor Word (.docx) shablonlar va o'zgaruvchan bandlar tizimi</p>
                </div>
                <button v-if="!showWizard" @click="openCreateWizard" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm">
                    + Yangi DOCX Shablon
                </button>
            </div>

            <!-- List View (shown when wizard is closed) -->
            <div v-if="!showWizard" class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden flex flex-col">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase">Shablon nomi</th>
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase">Turi</th>
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase">O'zgaruvchan bandlar</th>
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase">Yaratilgan sana</th>
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="tpl in templates" :key="tpl.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="p-4">
                                    <div class="text-sm font-semibold text-gray-900">{{ tpl.name }}</div>
                                    <div class="text-xs text-gray-500 font-mono mt-0.5">{{ tpl.description || 'Tavsif yo\'q' }}</div>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                          :class="tpl.type === 'residential' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : (tpl.type === 'commercial' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-blue-50 text-blue-700 border border-blue-100')">
                                        {{ tpl.type === 'residential' ? 'Turar-joy' : (tpl.type === 'commercial' ? 'No-turar' : 'Boshqa') }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="group in tpl.variant_groups" :key="group.id" class="px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-700 rounded text-[9px] font-mono">
                                            {{ group.label }} ({{ group.variants.length }} ta variant)
                                        </span>
                                        <span v-if="!tpl.variant_groups || tpl.variant_groups.length === 0" class="text-xs text-gray-400 font-mono">
                                            Mavjud emas
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 text-xs text-gray-500 font-mono">
                                    {{ new Date(tpl.created_at).toLocaleDateString() }}
                                </td>
                                <td class="p-4 text-right flex justify-end gap-2">
                                    <button @click="openEditWizard(tpl)" class="p-1 px-2 border border-gray-200 rounded text-xs hover:bg-gray-100 transition-colors text-gray-700">
                                        ✏️ Tahrirlash
                                    </button>
                                    <button @click="deleteTemplate(tpl)" class="p-1 px-2 border border-red-200 rounded text-xs bg-red-50 text-red-700 hover:bg-red-100 transition-colors">
                                        🗑️ O'chirish
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="templates.length === 0">
                                <td colspan="5" class="p-8 text-center text-sm text-gray-500">
                                    Hali hech qanday shartnoma shabloni kiritilmagan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Create / Edit Wizard View -->
            <div v-if="showWizard" class="bg-white border border-gray-200 shadow-md rounded-xl p-6 flex flex-col space-y-6 animate-in fade-in duration-200">
                <!-- Wizard Navigation Steps -->
                <div class="flex items-center justify-between border-b border-gray-200 pb-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-lg font-bold text-gray-900">{{ isEditing ? 'Shablonni tahrirlash' : 'Yangi shablon qo\'shish' }}</h2>
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-[10px] font-bold font-mono">Qadam {{ step }} / 3</span>
                    </div>
                    <div class="flex gap-2">
                        <button @click="showWizard = false" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-100 transition-colors">
                            Bekor qilish
                        </button>
                    </div>
                </div>

                <!-- Step Indicator Header -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="border-b-4 pb-2 text-xs font-mono font-bold tracking-wider" 
                         :class="step >= 1 ? 'border-blue-600 text-blue-600' : 'border-gray-200 text-gray-400'">
                        1. FAYL YUKLASH & SOZLAMALAR
                    </div>
                    <div class="border-b-4 pb-2 text-xs font-mono font-bold tracking-wider" 
                         :class="step >= 2 ? 'border-blue-600 text-blue-600' : 'border-gray-200 text-gray-400'">
                        2. O'ZGARUVCHILAR & PREVIEW
                    </div>
                    <div class="border-b-4 pb-2 text-xs font-mono font-bold tracking-wider" 
                         :class="step >= 3 ? 'border-blue-600 text-blue-600' : 'border-gray-200 text-gray-400'">
                        3. O'ZGARUVCHAN BANDLAR
                    </div>
                </div>

                <!-- STEP 1: Upload and Details -->
                <div v-show="step === 1" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Shablon nomi *</label>
                                <input v-model="wizardForm.name" type="text" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-2.5 px-3" placeholder="Masalan: Uy-joy Hissadorlik Shartnomasi v1" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Mulk turi *</label>
                                <select v-model="wizardForm.type" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-2.5 px-3">
                                    <option value="residential">Turar-joy (Uy)</option>
                                    <option value="commercial">No-turar joy (Do'kon)</option>
                                    <option value="custom">Boshqa (Custom)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Qisqacha tavsifi</label>
                                <textarea v-model="wizardForm.description" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-2 px-3 h-20 resize-none" placeholder="Ushbu shablon qanday holatlarda ishlatilishi haqida..."></textarea>
                            </div>
                        </div>

                        <!-- DOCX Upload Dropzone -->
                        <div class="flex flex-col justify-between">
                            <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Word (.docx) Fayl namunasi *</label>
                            
                            <div v-if="!isEditing || wizardForm.newFileUploaded || true"
                                 @dragover.prevent="" 
                                 @drop.prevent="handleDocxDrop"
                                 @click="triggerDocxSelect"
                                 class="flex-1 border-2 border-dashed border-gray-300 rounded-xl flex flex-col items-center justify-center p-6 bg-slate-50 hover:bg-slate-100/50 cursor-pointer transition-all group min-h-[160px]">
                                <input type="file" ref="docxInput" class="hidden" accept=".docx" @change="handleDocxSelect">
                                <div class="text-3xl mb-2 text-gray-400 group-hover:text-blue-500 transition-colors">📄</div>
                                <span class="text-xs font-semibold text-gray-700">{{ selectedFileName || 'Word (.docx) faylini sudrab o\'ting yoki tanlang' }}</span>
                                <span class="text-[10px] text-gray-400 mt-1 font-mono">Maksimal o\'lcham: 10MB</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <button @click="goToStep2" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm" :disabled="!wizardForm.name || (!isEditing && !wizardForm.file) || isProcessing">
                            <span v-if="isProcessing" class="animate-spin mr-1">🌀</span> Tahlil qilish & Keyingi
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Placeholders & Live Preview -->
                <div v-show="step === 2" class="space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 h-[55vh]">
                        <!-- Left Pane: Live HTML Preview -->
                        <div class="border border-gray-200 rounded-xl bg-slate-100 p-4 flex flex-col overflow-hidden">
                            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-2 flex items-center gap-2">
                                👁️ Hujjat namunasi (Preview)
                            </h4>
                            <div class="flex-1 bg-white rounded-lg p-6 overflow-y-auto custom-scrollbar border border-gray-200 text-xs text-gray-900 docx-preview-content" v-html="previewHtml">
                            </div>
                        </div>

                        <!-- Right Pane: Auto-detected Placeholders table -->
                        <div class="border border-gray-200 rounded-xl bg-slate-100 p-4 flex flex-col overflow-hidden">
                            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">🔍 Matnda aniqlangan o'zgaruvchilar</h4>
                            <p class="text-[9px] text-gray-500 font-mono mb-2">Tizim namunaviy ma'lumotlarni topdi. Ularni shablon o'zgaruvchilariga almashtirishni tasdiqlang:</p>
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar bg-white rounded-lg border border-gray-200">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-100">
                                            <th class="p-2 text-[10px] font-mono text-gray-500 uppercase">Matndagi qiymat</th>
                                            <th class="p-2 text-[10px] font-mono text-gray-500 uppercase">Placeholder kaliti</th>
                                            <th class="p-2 text-[10px] font-mono text-gray-500 uppercase text-center">Tasdiq</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="(p, idx) in detectedPlaceholders" :key="idx" class="hover:bg-gray-50">
                                            <td class="p-2 font-mono text-[10px] text-gray-900 truncate max-w-[140px]" :title="p.text">
                                                <input v-if="p.is_custom" v-model="p.text" type="text" class="bg-white border border-gray-300 text-gray-800 rounded px-1 py-0.5 text-[10px] font-mono w-full" placeholder="Matndagi qiymat..." required>
                                                <span v-else>{{ p.text }}</span>
                                            </td>
                                            <td class="p-2">
                                                <input v-model="p.key" type="text" class="bg-slate-50 border border-gray-200 text-blue-600 rounded px-1.5 py-0.5 text-[10px] font-mono font-bold w-full focus:ring-1 focus:ring-blue-500 focus:bg-white" required>
                                            </td>
                                            <td class="p-2 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <input v-model="p.confirmed" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500 border-gray-300">
                                                    <button v-if="p.is_custom" type="button" @click="removeCustomPlaceholder(idx)" class="text-red-500 hover:text-red-700 font-bold text-sm leading-none">&times;</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="detectedPlaceholders.length === 0">
                                            <td colspan="3" class="p-4 text-center text-gray-400 font-mono text-[10px]">
                                                O'zgaruvchilar topilmadi yoki fayl tahlil qilinmoqda...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-2 bg-gray-50 border-t border-gray-200 flex justify-between items-center rounded-b-lg">
                                <span class="text-[8px] text-gray-400 font-mono">* Namunadagi matnni va unga mos kalitni kiriting</span>
                                <button type="button" @click="addNewPlaceholder" class="px-2 py-0.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-[10px] font-bold rounded font-mono">
                                    + Yangi o'zgaruvchi
                                </button>
                            </div>

                            <!-- Grouped Variables Helper -->
                            <div class="mt-3 p-3 bg-white rounded-lg border border-gray-200 shadow-inner relative">
                                <div class="flex justify-between items-center mb-2 pb-1.5 border-b border-gray-100">
                                    <span class="text-[10px] font-bold text-gray-800 uppercase tracking-wider flex items-center gap-1">
                                        📋 O'zgaruvchilar Ma'lumotnomasi
                                    </span>
                                    <div class="flex bg-gray-100 p-0.5 rounded-lg border border-gray-200">
                                        <button type="button" @click="activeHelperGroup = 'zamon'" :class="activeHelperGroup === 'zamon' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'" class="px-2 py-0.5 rounded-md text-[9px] font-bold transition-all">
                                            Zamon Sifat Servis
                                        </button>
                                        <button type="button" @click="activeHelperGroup = 'sherdor'" :class="activeHelperGroup === 'sherdor' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'" class="px-2 py-0.5 rounded-md text-[9px] font-bold transition-all">
                                            Eski (Sher-Dor)
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-1.5 max-h-[14vh] overflow-y-auto custom-scrollbar pr-1">
                                    <div v-for="item in helperVariables" :key="item.key" @click="copyPlaceholder(item.key)" class="p-1.5 bg-slate-50 hover:bg-blue-50 border border-slate-150 hover:border-blue-300 rounded cursor-pointer transition-all flex flex-col group relative">
                                        <span class="font-mono text-[9px] font-bold text-blue-600 flex justify-between items-center">
                                            <span>&lbrace;&lbrace; {{ item.key }} &rbrace;&rbrace;</span>
                                            <span class="text-[8px] text-gray-400 group-hover:text-blue-500 font-sans">Nusxa 📋</span>
                                        </span>
                                        <span class="text-[8px] text-gray-500 mt-0.5 leading-tight">{{ item.desc }}</span>
                                    </div>
                                </div>
                                
                                <!-- Floating Toast for copy confirmation -->
                                <div v-if="toastMsg" class="absolute bottom-2 right-2 bg-slate-900 text-white text-[9px] font-mono font-bold px-2 py-1 rounded shadow-md animate-in fade-in zoom-in duration-200">
                                    {{ toastMsg }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between pt-4 border-t border-gray-100">
                        <button @click="step = 1" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-100 transition-colors">
                            Orqaga
                        </button>
                        <button @click="step = 3" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm">
                            Keyingi (Bandlar va Variantlar)
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Clause Variant Groups -->
                <div v-show="step === 3" class="space-y-4">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Left 2 Columns: Variant Groups List -->
                        <div class="lg:col-span-2 border border-gray-200 rounded-xl bg-slate-50 p-4 flex flex-col h-[65vh]">
                            <div class="flex justify-between items-center mb-3 pb-2 border-b border-gray-200">
                                <div>
                                    <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">📦 O'zgaruvchan bandlar (Shartnoma variantlari)</h4>
                                    <p class="text-[9px] text-gray-500 font-mono mt-0.5">Masalan: to'lov shartlari (Naqt, Grafik, Ipoteka) kabi bandlarni qo'shing</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button type="button" @click="addVariantGroup" class="px-3 py-1 bg-slate-200 hover:bg-slate-300 text-gray-900 border border-gray-300 text-[10px] font-bold rounded font-mono">
                                        + Yangi band guruhi
                                    </button>
                                    <button type="button" @click="loadStandardPaymentVariants" class="px-3 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-[10px] font-bold rounded font-mono">
                                        ⚡ Standart to'lov shartlarini yuklash (Naqd, Grafik, Ipoteka)
                                    </button>
                                </div>
                            </div>

                            <!-- Scrollable Variant Groups List -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-4">
                                <div v-for="(g, gIdx) in wizardForm.variant_groups" :key="gIdx" class="bg-white border border-gray-200 rounded-lg p-4 relative space-y-3">
                                    <!-- Remove Group Button -->
                                    <button type="button" @click="removeVariantGroup(gIdx)" class="absolute right-3 top-3 text-red-500 hover:text-red-700 font-bold text-sm">&times;</button>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-[9px] font-mono text-gray-500 mb-0.5 uppercase">Band nomi *</label>
                                            <input v-model="g.label" type="text" class="w-full bg-slate-50 border border-gray-300 text-gray-900 rounded p-1.5 text-xs font-semibold" placeholder="Masalan: To'lov shartlari" required>
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-mono text-gray-500 mb-0.5 uppercase">Matndagi Anchor (Jinja2 tag) *</label>
                                            <input v-model="g.anchor" type="text" class="w-full bg-slate-50 border border-gray-300 text-blue-600 rounded p-1.5 text-xs font-mono font-bold" placeholder="Masalan: TO_LOV_SHARTI" required>
                                        </div>
                                    </div>

                                    <!-- Variants of this group -->
                                    <div class="border-t border-gray-100 pt-2 space-y-2">
                                        <div class="flex justify-between items-center">
                                            <h5 class="text-[10px] font-bold text-gray-700 uppercase tracking-widest">Variantlar:</h5>
                                            <button type="button" @click="addVariant(gIdx)" class="text-[9px] text-blue-600 hover:text-blue-800 font-bold font-mono">+ Variant qo'shish</button>
                                        </div>

                                        <div class="space-y-3">
                                            <div v-for="(v, vIdx) in g.variants" :key="vIdx" class="bg-slate-50 border border-gray-150 rounded p-3 relative space-y-2">
                                                <button type="button" @click="removeVariant(gIdx, vIdx)" class="absolute right-2 top-2 text-gray-400 hover:text-red-500 text-xs">&times;</button>
                                                
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-[8px] font-mono text-gray-400 mb-0.5 uppercase">Variant nomi (Sarlavha)</label>
                                                        <input v-model="v.label" type="text" class="w-full bg-white border border-gray-300 text-gray-900 rounded p-1 text-[10px]" placeholder="Masalan: Grafik bo'yicha" required>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[8px] font-mono text-gray-400 mb-0.5 uppercase">Variant kodi (Key) *</label>
                                                        <input v-model="v.key" type="text" class="w-full bg-white border border-gray-300 text-gray-900 rounded p-1 text-[10px] font-mono" placeholder="Masalan: GRAFIK" required>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] font-mono text-gray-400 mb-0.5 uppercase">Variant matni (Original Word'dagi band matni, sub-o'zgaruvchilar ishlatsa bo'ladi)</label>
                                                    <textarea v-model="v.content" @input="onVariantContentInput(v)" class="w-full bg-white border border-gray-300 text-gray-900 rounded p-1.5 text-[10px] h-12 font-mono" placeholder="Masalan: 3.1-bandda ko'rsatilgan summaning {{ BOSHLANGICH_FOIZ }}% to'lanadi..." required></textarea>
                                                </div>
                                                <div>
                                                    <label class="block text-[8px] font-mono text-gray-400 mb-0.5 uppercase">Variant o'zgaruvchilari (Vergul bilan ajrating)</label>
                                                    <input v-model="v.placeholdersString" @input="v.placeholders = v.placeholdersString.split(',').map(s => s.trim()).filter(Boolean)" type="text" class="w-full bg-white border border-gray-300 text-gray-900 rounded p-1 text-[10px] font-mono" placeholder="Masalan: BOSHLANGICH_FOIZ, MUDDAT_OY">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="wizardForm.variant_groups.length === 0" class="p-8 text-center text-xs text-gray-400 font-mono bg-white rounded border border-gray-200">
                                    Hali o'zgaruvchan bandlar qo'shilmadi (Ixtiyoriy)
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Variables Helper Reference -->
                        <div class="lg:col-span-1 border border-gray-200 rounded-xl bg-slate-100 p-4 flex flex-col h-[65vh] overflow-hidden relative">
                            <div class="flex justify-between items-center mb-3 pb-2 border-b border-gray-200">
                                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-1">
                                    📋 O'zgaruvchilar ro'yxati
                                </h4>
                                <div class="flex bg-gray-100 p-0.5 rounded-lg border border-gray-200">
                                    <button type="button" @click="activeHelperGroup = 'zamon'" :class="activeHelperGroup === 'zamon' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'" class="px-2 py-1 rounded-md text-[9px] font-bold transition-all">
                                        Zamon Sifat
                                    </button>
                                    <button type="button" @click="activeHelperGroup = 'sherdor'" :class="activeHelperGroup === 'sherdor' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'" class="px-2 py-1 rounded-md text-[9px] font-bold transition-all">
                                        Sher-Dor
                                    </button>
                                </div>
                            </div>

                            <p class="text-[9px] text-gray-500 font-mono mb-2">Variant matnida ishlatish uchun o'zgaruvchini tanlang va nusxalab oling:</p>

                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 bg-white rounded-lg border border-gray-200 p-2">
                                <div v-for="item in helperVariables" :key="item.key" @click="copyPlaceholder(item.key)" class="p-2 bg-slate-50 hover:bg-blue-50 border border-slate-150 hover:border-blue-300 rounded cursor-pointer transition-all flex flex-col group relative">
                                    <span class="font-mono text-[9px] font-bold text-blue-600 flex justify-between items-center">
                                        <span>&lbrace;&lbrace; {{ item.key }} &rbrace;&rbrace;</span>
                                        <span class="text-[8px] text-gray-400 group-hover:text-blue-500 font-sans">Nusxa 📋</span>
                                    </span>
                                    <span class="text-[8px] text-gray-500 mt-1 leading-normal">{{ item.desc }}</span>
                                </div>
                            </div>
                            
                            <!-- Floating Toast inside Helper -->
                            <div v-if="toastMsg" class="absolute bottom-4 left-4 right-4 bg-slate-900 text-white text-xs font-mono font-bold px-3 py-2 rounded-lg text-center shadow-lg animate-in fade-in zoom-in duration-200">
                                {{ toastMsg }}
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between pt-4 border-t border-gray-100">
                        <button @click="step = 2" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-100 transition-colors">
                            Orqaga
                        </button>
                        <button @click="saveTemplate" class="px-8 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm" :disabled="isProcessing">
                            <span v-if="isProcessing" class="animate-spin mr-1">🌀</span> Shablonni Saqlash
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

const props = defineProps({
    templates: Array
});

const showWizard = ref(false);
const isEditing = ref(false);
const editingTemplateId = ref(null);
const isProcessing = ref(false);
const step = ref(1);

const docxInput = ref(null);
const selectedFileName = ref('');
const previewHtml = ref('');
const detectedPlaceholders = ref([]);

const activeHelperGroup = ref('zamon');
const toastMsg = ref('');

const zamonVariables = [
    { key: 'ZAMON_BUYURTMACHI_FISH', desc: 'Buyurtmachi To\'liq F.I.Sh (Ariza/Propiska egasi)' },
    { key: 'ZAMON_BUYURTMACHI_PROPISKA', desc: 'Buyurtmachining Doimiy yashash manzili' },
    { key: 'ZAMON_PASPORT_SERIYA', desc: 'Buyurtmachi Pasport Seriyasi (e.g. AD)' },
    { key: 'ZAMON_PASPORT_RAQAM', desc: 'Buyurtmachi Pasport Raqami (e.g. 1234567)' },
    { key: 'ZAMON_PASPORT_JOYI', desc: 'Pasport Berilgan Joy/Organ' },
    { key: 'ZAMON_PASPORT_SANA', desc: 'Pasport Berilgan Sana' },
    { key: 'ZAMON_PODEZD', desc: 'Bino Podezd/Yo\'lak Raqami' },
    { key: 'ZAMON_XONADON', desc: 'Sotilayotgan Xonadon Raqami' },
    { key: 'ZAMON_QAVAT', desc: 'Xonadon Joylashgan Qavat' },
    { key: 'ZAMON_XONALAR', desc: 'Xonadondagi Xonalar Soni' },
    { key: 'ZAMON_MAYDON', desc: 'Xonadon Umumiy Maydoni (Kvadrat metr)' },
    { key: 'ZAMON_KV_METR_NARXI', desc: 'Xonadonning 1 kv.m Narxi' },
    { key: 'ZAMON_KV_METR_NARXI_SOZ', desc: '1 kv.m Narxi so\'z bilan' },
    { key: 'ZAMON_UMUMIY_NARX', desc: 'Xonadonning Umumiy Narxi' },
    { key: 'ZAMON_UMUMIY_NARX_SOZ', desc: 'Umumiy Narxi so\'z bilan' },
    { key: 'ZAMON_BOSHLANGICH_TOLOV', desc: 'Boshlang\'ich (Oldindan) To\'lov Summasi' },
    { key: 'ZAMON_BOSHLANGICH_TOLOV_SOZ', desc: 'Boshlang\'ich To\'lov so\'z bilan' },
    { key: 'ZAMON_BOSHLANGICH_FOIZ', desc: 'Boshlang\'ich To\'lov Foiz nisbati' },
    { key: 'ZAMON_TELEFON', desc: 'Buyurtmachi Telefon Raqamlari' },
];

const sherdorVariables = [
    { key: 'MIJOZ_FISH', desc: 'Mijoz To\'liq F.I.Sh' },
    { key: 'MIJOZ_FISH_QISQA', desc: 'Mijoz F.I.Sh qisqa (A.Mamanov)' },
    { key: 'PASPORT_SERIYA', desc: 'Pasport Seriya va Raqam' },
    { key: 'PASPORT_SANA', desc: 'Pasport Berilgan Sana' },
    { key: 'PASPORT_JOYI', desc: 'Pasport Bergan Organ' },
    { key: 'MIJOZ_MANZILI', desc: 'Mijoz Ro\'yxatdan o\'tgan manzili' },
    { key: 'MIJOZ_JSHSHIR', desc: 'Mijoz JSHSHIR (PINFL)' },
    { key: 'MIJOZ_TELEFON', desc: 'Mijoz Telefon Raqamlari' },
    { key: 'BLOK', desc: 'Blok raqami/nomi' },
    { key: 'QAVAT', desc: 'Qavat' },
    { key: 'XONA_RAQAMI', desc: 'Xonadon/No-turar do\'kon raqami' },
    { key: 'XONALAR_SONI', desc: 'Xonalar soni' },
    { key: 'MAYDON', desc: 'Obyekt Maydoni' },
    { key: 'KV_METR_NARXI', desc: '1 kv.m Narxi' },
    { key: 'UMUMIY_NARX', desc: 'Obyekt Umumiy Narxi' },
    { key: 'UMUMIY_NARX_SOZ_BILAN', desc: 'Umumiy Narx so\'z bilan' },
    { key: 'BOSHLANGICH_TOLOV', desc: 'Boshlang\'ich To\'lov summasi' },
    { key: 'BOSHLANGICH_TOLOV_SOZ_BILAN', desc: 'Boshlang\'ich To\'lov so\'z bilan' },
    { key: 'BOSHLANGICH_TOLOV_FOIZ', desc: 'Boshlang\'ich To\'lov foizi' },
    { key: 'MUDDAT_OY', desc: 'To\'lov Muddati (oylar)' },
    { key: 'MUDDAT_OY_SOZ_BILAN', desc: 'To\'lov muddati so\'z bilan' },
    { key: 'TOLOV_MUDDAT_KUN', desc: 'Dastlabki To\'lov muddati (kunlar)' },
    { key: 'DIREKTOR', desc: 'Kompaniya direktori' },
    { key: 'KORXONA_NOMI', desc: 'Kompaniya nomi' },
    { key: 'KORXONA_MANZILI', desc: 'Kompaniya manzili' },
    { key: 'KORXONA_BANK_ACCOUNT', desc: 'Bank hisob raqami' },
    { key: 'KORXONA_BANK', desc: 'Bank nomi' },
    { key: 'KORXONA_MFO', desc: 'MFO kodi' },
    { key: 'KORXONA_INN', desc: 'INN raqami' },
    { key: 'KORXONA_TELEFON', desc: 'Kompaniya telefoni' }
];

const helperVariables = computed(() => {
    return activeHelperGroup.value === 'zamon' ? zamonVariables : sherdorVariables;
});

function copyPlaceholder(key) {
    navigator.clipboard.writeText('{{ ' + key + ' }}');
    toastMsg.value = '{{ ' + key + ' }} nusxalandi!';
    setTimeout(() => {
        if (toastMsg.value.includes(key)) {
            toastMsg.value = '';
        }
    }, 2000);
}

const wizardForm = ref({
    name: '',
    type: 'residential',
    description: '',
    file: null,
    newFileUploaded: false,
    variant_groups: []
});

function openCreateWizard() {
    isEditing.value = false;
    editingTemplateId.value = null;
    selectedFileName.value = '';
    previewHtml.value = '';
    detectedPlaceholders.value = [];
    step.value = 1;
    wizardForm.value = {
        name: '',
        type: 'residential',
        description: '',
        file: null,
        newFileUploaded: false,
        variant_groups: []
    };
    showWizard.value = true;
}

function openEditWizard(template) {
    isEditing.value = true;
    editingTemplateId.value = template.id;
    selectedFileName.value = template.docx_path ? template.docx_path.split('/').pop() : '';
    previewHtml.value = template.preview_html || '';
    detectedPlaceholders.value = []; // loaded only if new file is uploaded
    step.value = 1;

    // Load variant groups and format placeholders
    const variantGroups = (template.variant_groups || []).map(g => {
        return {
            label: g.label,
            anchor: g.anchor,
            variants: g.variants.map(v => {
                return {
                    key: v.key,
                    label: v.label,
                    content: v.content,
                    placeholders: v.placeholders || [],
                    placeholdersString: (v.placeholders || []).join(', ')
                };
            })
        };
    });

    wizardForm.value = {
        name: template.name,
        type: template.type,
        description: template.description || '',
        file: null,
        newFileUploaded: false,
        variant_groups: variantGroups
    };
    showWizard.value = true;
}

function triggerDocxSelect() {
    docxInput.value.click();
}

function handleDocxSelect(e) {
    const file = e.target.files[0];
    if (file) {
        wizardForm.value.file = file;
        wizardForm.value.newFileUploaded = true;
        selectedFileName.value = file.name;
    }
}

function handleDocxDrop(e) {
    const file = e.dataTransfer.files[0];
    if (file && file.name.endsWith('.docx')) {
        wizardForm.value.file = file;
        wizardForm.value.newFileUploaded = true;
        selectedFileName.value = file.name;
    } else {
        alert("Faqat .docx formatidagi Word fayllarini yuklashingiz mumkin!");
    }
}

function goToStep2() {
    if (isEditing.value && !wizardForm.value.newFileUploaded) {
        // No new file, just proceed with existing previewHtml
        step.value = 2;
        return;
    }

    if (!wizardForm.value.file) {
        alert("Iltimos, avval Word faylini yuklang!");
        return;
    }

    isProcessing.value = true;
    const formData = new FormData();
    formData.append('file', wizardForm.value.file);

    axios.post(route('admin.contract-templates.detect'), formData, {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }).then(res => {
        if (res.data.error || !Array.isArray(res.data.placeholders)) {
            throw new Error(res.data.error || "Fayl tahlil qilinmadi yoki noto'g'ri ma'lumot qaytdi.");
        }
        detectedPlaceholders.value = res.data.placeholders.map(p => {
            return {
                ...p,
                confirmed: true
            };
        });
        previewHtml.value = res.data.preview_html || '';
        isProcessing.value = false;
        step.value = 2;
    }).catch(err => {
        isProcessing.value = false;
        setTimeout(() => {
            alert("Faylni tahlil qilishda xatolik yuz berdi: " + (err.response?.data?.message || err.message));
        }, 50);
    });
}

function addNewPlaceholder() {
    detectedPlaceholders.value.push({
        text: '',
        key: '',
        confirmed: true,
        is_custom: true
    });
}

function removeCustomPlaceholder(idx) {
    detectedPlaceholders.value.splice(idx, 1);
}

// Variant Groups Helpers
function addVariantGroup() {
    wizardForm.value.variant_groups.push({
        label: '',
        anchor: '',
        variants: []
    });
}

function removeVariantGroup(idx) {
    wizardForm.value.variant_groups.splice(idx, 1);
}

function addVariant(gIdx) {
    wizardForm.value.variant_groups[gIdx].variants.push({
        key: '',
        label: '',
        content: '',
        placeholders: [],
        placeholdersString: ''
    });
}

function removeVariant(gIdx, vIdx) {
    wizardForm.value.variant_groups[gIdx].variants.splice(vIdx, 1);
}

function loadStandardPaymentVariants() {
    const exists = wizardForm.value.variant_groups.some(g => g.anchor === 'TOLOV_SHARTLARI');
    if (exists) {
        alert("To'lov shartlari (TOLOV_SHARTLARI) guruhi allaqachon qo'shilgan!");
        return;
    }
    
    wizardForm.value.variant_groups.push({
        label: "To'lov shartlari",
        anchor: "TOLOV_SHARTLARI",
        variants: [
            {
                label: "Naqd to'lov",
                key: "NAQD",
                content: "1. 3.1-bandda ko‘rsatilgan shartnoma summasining kamida {{ BOSHLANGICH_FOIZ }}% , {{ BOSHLANGICH_TOLOV }} ({{ BOSHLANGICH_TOLOV_SOZ_BILAN }}) so‘m miqdoridagi mablag‘ «XARIDOR» tomonidan shartnoma imzolangan kundan boshlab {{ TOLOV_MUDDAT_KUN }} bank ish kuni ichida to‘lanadi.",
                placeholdersString: "BOSHLANGICH_FOIZ, TOLOV_MUDDAT_KUN",
                placeholders: ["BOSHLANGICH_FOIZ", "TOLOV_MUDDAT_KUN"]
            },
            {
                label: "Grafik asosida",
                key: "GRAFIK",
                content: "2. 3.1-bandda ko‘rsatilgan shartnoma summasining kamida {{ BOSHLANGICH_FOIZ }}% , {{ BOSHLANGICH_TOLOV }} ({{ BOSHLANGICH_TOLOV_SOZ_BILAN }}) so‘m miqdoridagi boshlang‘ich mablag‘ni «XARIDOR» tomonidan shartnoma imzolangan kundan boshlab {{ TOLOV_MUDDAT_KUN }} bank ish kuni ichida, qolgan summa boshlang‘ich to‘lov to‘langandan so‘ng {{ MUDDAT_OY }} ({{ MUDDAT_OY_SOZ_BILAN }}) oy ichida ushbu shartnomaning 2-ilovasiga asosan to‘lov grafigi asosida to‘lanadi.",
                placeholdersString: "BOSHLANGICH_FOIZ, TOLOV_MUDDAT_KUN, MUDDAT_OY",
                placeholders: ["BOSHLANGICH_FOIZ", "TOLOV_MUDDAT_KUN", "MUDDAT_OY"]
            },
            {
                label: "Ipoteka asosida",
                key: "IPOTEKA",
                content: "2. 3.1-bandda ko‘rsatilgan shartnoma summasining kamida {{ BOSHLANGICH_FOIZ }}% , {{ BOSHLANGICH_TOLOV }} ({{ BOSHLANGICH_TOLOV_SOZ_BILAN }}) so‘m miqdoridagi boshlang‘ich mablag‘ni «XARIDOR» tomonidan shartnoma imzolangan kundan boshlab {{ TOLOV_MUDDAT_KUN }} bank ish kuni ichida, qolgan summa boshlang‘ich to‘lov to‘langandan so‘ng har oyning 10-sanasiga qadar oyiga {{ OYLIK_TOLOV }} ({{ OYLIK_TOLOV_SOZ_BILAN }}) so‘mdan yoki ushbu shartnomaning 2-ilovasiga asosan to‘lov grafigi asosida to‘lab boriladi va kadastr hujjati tayyor bo‘lgandan so‘ng o‘zaro hisob-kitob qilinib qoldiq summa ipoteka krediti asosida to‘lanadi.",
                placeholdersString: "BOSHLANGICH_FOIZ, TOLOV_MUDDAT_KUN, MUDDAT_OY",
                placeholders: ["BOSHLANGICH_FOIZ", "TOLOV_MUDDAT_KUN", "MUDDAT_OY"]
            },
            {
                label: "Bo'lib to'lash",
                key: "INSTALLMENT",
                content: "2. 3.1-bandda ko‘rsatilgan shartnoma summasining kamida {{ BOSHLANGICH_FOIZ }}% , {{ BOSHLANGICH_TOLOV }} ({{ BOSHLANGICH_TOLOV_SOZ_BILAN }}) so‘m miqdoridagi boshlang‘ich mablag‘ni «XARIDOR» tomonidan shartnoma imzolangan kundan boshlab {{ TOLOV_MUDDAT_KUN }} bank ish kuni ichida, qolgan summa boshlang‘ich to‘lov to‘langandan so‘ng {{ MUDDAT_OY }} ({{ MUDDAT_OY_SOZ_BILAN }}) oy davomida bo‘lib to‘lash shartlari asosida to‘lanadi.",
                placeholdersString: "BOSHLANGICH_FOIZ, TOLOV_MUDDAT_KUN, MUDDAT_OY",
                placeholders: ["BOSHLANGICH_FOIZ", "TOLOV_MUDDAT_KUN", "MUDDAT_OY"]
            }
        ]
    });
}

function onVariantContentInput(v) {
    const text = v.content || '';
    const regex = /\{\{\s*([A-Za-z0-9_]+)\s*\}\}/g;
    const matches = [];
    let match;
    while ((match = regex.exec(text)) !== null) {
        matches.push(match[1]);
    }
    
    // Filter out auto-calculated placeholders
    const autoCalculated = ['BOSHLANGICH_TOLOV', 'BOSHLANGICH_TOLOV_SOZ_BILAN', 'MUDDAT_OY_SOZ_BILAN', 'OYLIK_TOLOV', 'OYLIK_TOLOV_SOZ_BILAN'];
    const uniquePlaceholders = [...new Set(matches)].filter(p => !autoCalculated.includes(p));
    
    v.placeholders = uniquePlaceholders;
    v.placeholdersString = uniquePlaceholders.join(', ');
}

function saveTemplate() {
    isProcessing.value = true;

    // Build form data
    const formData = new FormData();
    formData.append('name', wizardForm.value.name);
    formData.append('type', wizardForm.value.type);
    formData.append('description', wizardForm.value.description || '');

    if (wizardForm.value.file) {
        formData.append('file', wizardForm.value.file);
    }

    // Attach confirmed placeholders mapping
    formData.append('placeholders', JSON.stringify(detectedPlaceholders.value));
    
    // Attach variant groups
    formData.append('variant_groups', JSON.stringify(wizardForm.value.variant_groups));

    if (isEditing.value) {
        // In Laravel, PUT requests with file uploads can be problematic. We use POST with _method=PUT
        formData.append('_method', 'PUT');
        router.post(route('admin.contract-templates.update', editingTemplateId.value), formData, {
            onSuccess: () => {
                showWizard.value = false;
                isProcessing.value = false;
            },
            onError: () => {
                isProcessing.value = false;
            }
        });
    } else {
        router.post(route('admin.contract-templates.store'), formData, {
            onSuccess: () => {
                showWizard.value = false;
                isProcessing.value = false;
            },
            onError: () => {
                isProcessing.value = false;
            }
        });
    }
}

function deleteTemplate(template) {
    if (confirm(`Haqiqatan ham "${template.name}" shablonini o'chirib tashlamoqchimisiz?`)) {
        router.delete(route('admin.contract-templates.destroy', template.id));
    }
}
</script>

<style>
.docx-preview-content table {
    width: 100% !important;
    border-collapse: collapse !important;
    margin: 10px 0 !important;
}
.docx-preview-content th, .docx-preview-content td {
    border: 1px solid #ddd !important;
    padding: 6px 10px !important;
}
.docx-preview-content img {
    max-width: 100% !important;
    height: auto !important;
}
</style>
