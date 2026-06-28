<template>
    <AdminLayout>
        <Head title="Shartnoma Shablonlari" />

        <div class="p-6 h-screen flex flex-col bg-slate-50 overflow-y-auto custom-scrollbar">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">📝 Shartnoma Shablonlari</h1>
                    <p class="text-xs text-gray-500 font-mono mt-1">Mijozlar bilan tuziladigan shartnoma turlari uchun shablonlarni boshqarish</p>
                </div>
                <button @click="openCreateModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm">
                    + Yangi Shablon
                </button>
            </div>

            <!-- Templates List -->
            <div class="bg-white border border-gray-200 shadow-sm rounded-xl overflow-hidden flex flex-col">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase">Shablon nomi</th>
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase">Turi</th>
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase">Tavsifi</th>
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase">Yaratilgan sana</th>
                                <th class="p-4 text-xs font-mono text-gray-500 uppercase text-right">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="tpl in templates" :key="tpl.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="p-4">
                                    <div class="text-sm font-semibold text-gray-900">{{ tpl.name }}</div>
                                </td>
                                <td class="p-4">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                          :class="tpl.type === 'residential' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : (tpl.type === 'commercial' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-blue-50 text-blue-700 border border-blue-100')">
                                        {{ tpl.type === 'residential' ? 'Turar-joy (Uy)' : (tpl.type === 'commercial' ? 'No-turar joy (Do\'kon)' : 'Boshqa') }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="text-xs text-gray-600 font-mono line-clamp-1 max-w-sm">{{ tpl.description || 'Kiritilmagan' }}</div>
                                </td>
                                <td class="p-4 text-xs text-gray-500 font-mono">
                                    {{ new Date(tpl.created_at).toLocaleDateString() }}
                                </td>
                                <td class="p-4 text-right flex justify-end gap-2">
                                    <button @click="openEditModal(tpl)" class="p-1 px-2 border border-gray-200 rounded text-xs hover:bg-gray-100 transition-colors text-gray-700">
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

            <!-- Create / Edit Modal -->
            <div v-if="showModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-5xl h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <!-- Modal Header -->
                    <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ isEditing ? 'Shablonni Tahrirlash' : 'Yangi Shablon Yaratish' }}</h3>
                            <p class="text-xs text-gray-500 font-mono mt-0.5">Shablon ma'lumotlari va HTML tarkibini kiriting</p>
                        </div>
                        <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
                    </div>

                    <!-- Modal Body (Two columns) -->
                    <div class="flex-1 flex overflow-hidden min-h-0">
                        <!-- Left Side: Editor Form -->
                        <div class="flex-1 p-6 overflow-y-auto custom-scrollbar space-y-4">
                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Shablon nomi *</label>
                                <input v-model="form.name" type="text" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-2 px-3" placeholder="Masalan: Uy-joy shartnomasi (Hissadorlik)" required>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Mulk turi *</label>
                                    <select v-model="form.type" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-2 px-3">
                                        <option value="residential">Turar-joy (Uy)</option>
                                        <option value="commercial">No-turar joy (Do'kon)</option>
                                        <option value="custom">Boshqa (Custom)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">HTML Fayl Yuklash (Avtomatik to'ldirish)</label>
                                    <button type="button" @click="triggerFileUpload" class="w-full text-left bg-gray-50 hover:bg-gray-100 border border-gray-300 border-dashed rounded-lg text-xs py-2 px-3 text-gray-700 transition-colors flex items-center justify-between">
                                        <span>📂 Faylni tanlash...</span>
                                        <span class="text-[10px] text-gray-400">.html yoki .txt</span>
                                    </button>
                                    <input type="file" ref="fileUploadInput" class="hidden" accept=".html,.txt" @change="handleHtmlFileUpload">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">Shablon haqida tavsif</label>
                                <textarea v-model="form.description" class="w-full bg-white border border-gray-300 text-gray-900 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-xs py-2 px-3 h-16 resize-none" placeholder="Ushbu shablonning maqsadi haqida qisqacha izoh kiriting..."></textarea>
                            </div>

                            <div class="flex-1 flex flex-col min-h-[300px]">
                                <label class="block text-[10px] font-mono text-gray-500 mb-1 uppercase">HTML Tarkibi (Shartnoma matni) *</label>
                                <textarea v-model="form.content" class="flex-1 w-full bg-slate-900 text-slate-100 font-mono text-xs rounded-lg shadow-sm p-4 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none h-[300px]" placeholder="<html><body>Shartnoma matni...</body></html>" required></textarea>
                            </div>
                        </div>

                        <!-- Right Side: Placeholders Cheat Sheet -->
                        <div class="w-72 border-l border-gray-200 bg-gray-50 p-5 overflow-y-auto custom-scrollbar flex flex-col">
                            <h4 class="text-xs font-bold text-gray-900 uppercase tracking-wider mb-3">📋 Mavjud o'zgaruvchilar (Placeholders)</h4>
                            <p class="text-[10px] text-gray-500 mb-4">Shartnoma matniga quyidagi o'zgaruvchilarni kiriting. Tizim ularni shartnoma qilingan vaqtda mijoz ma'lumotlariga almashtiradi. (Nusxa olish uchun bosing)</p>
                            
                            <div class="space-y-4 flex-1">
                                <div v-for="(group, gName) in placeholderGroups" :key="gName" class="space-y-1.5">
                                    <h5 class="text-[10px] font-bold text-gray-700 uppercase tracking-widest">{{ gName }}</h5>
                                    <div class="space-y-1">
                                        <div v-for="(desc, code) in group" :key="code" 
                                             @click="copyPlaceholder(code)"
                                             class="flex items-center justify-between p-1 px-2 bg-white border border-gray-200 rounded cursor-pointer hover:border-blue-500 hover:bg-blue-50/30 transition-all group">
                                            <span class="text-[10px] font-mono text-blue-600 font-bold select-all">{{ code }}</span>
                                            <span class="text-[9px] text-gray-500 text-right truncate max-w-[120px]">{{ desc }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="p-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
                        <button @click="showModal = false" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-medium hover:bg-gray-100 text-gray-700 transition-colors">
                            Bekor qilish
                        </button>
                        <button @click="saveTemplate" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-all shadow-sm" :disabled="isProcessing">
                            {{ isEditing ? 'Yangilash' : 'Saqlash' }}
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

const props = defineProps({
    templates: Array
});

const showModal = ref(false);
const isEditing = ref(false);
const editingTemplateId = ref(null);
const isProcessing = ref(false);
const fileUploadInput = ref(null);

const form = ref({
    name: '',
    type: 'residential',
    description: '',
    content: ''
});

const placeholderGroups = {
    "Mijoz ma'lumotlari": {
        "{{MIJOZ_FISH}}": "Mijozning to'liq F.I.Sh.",
        "{{MIJOZ_MANZILI}}": "Mijozning yashash manzili",
        "{{MIJOZ_JSHSHIR}}": "JShShIR (PINFL)",
        "{{PASPORT_SERIYA}}": "Pasport seriyasi va raqami",
        "{{PASPORT_SANA}}": "Pasport berilgan sana",
        "{{PASPORT_JOYI}}": "Pasport bergan I.I.B",
        "{{MIJOZ_TELEFON}}": "Mijozning telefoni(lari)"
    },
    "O'b'yekt ma'lumotlari": {
        "{{BLOK}}": "Bino bloki/korpusi",
        "{{QAVAT}}": "Xonadon/Do'kon qavati",
        "{{XONA_RAQAMI}}": "Xonadon/Do'kon raqami",
        "{{XONALAR_SONI}}": "Xonalar soni",
        "{{MAYDON}}": "Umumiy maydoni (kv.m)"
    },
    "Moliyaviy shartlar": {
        "{{KV_METR_NARXI}}": "1 kv.m narxi (QQS bilan)",
        "{{UMUMIY_NARX}}": "Shartnomaning umumiy summasi",
        "{{UMUMIY_NARX_SOZ_BILAN}}": "Umumiy summa so'z bilan",
        "{{BOSHLANGICH_TOLOV}}": "Boshlang'ich to'lov",
        "{{BOSHLANGICH_TOLOV_SOZ_BILAN}}": "Boshlang'ich to'lov so'z bilan",
        "{{BOSHLANGICH_TOLOV_FOIZ}}": "Boshlang'ich to'lov foizi",
        "{{MUDDAT_OY}}": "Bo'lib to'lash muddati",
        "{{MUDDAT_OY_SOZ_BILAN}}": "To'lov muddati so'z bilan"
    },
    "Sana va Korxona": {
        "{{KUN}}": "Shartnoma kuni",
        "{{OY}}": "Shartnoma oyi (nom bilan)",
        "{{YIL}}": "Shartnoma yili",
        "{{SHARTNOMA_RAQAMI}}": "Shartnoma kodi/raqami",
        "{{DIREKTOR}}": "Direktorning ismi",
        "{{KORXONA_NOMI}}": "Korxona nomi"
    }
};

function openCreateModal() {
    isEditing.value = false;
    editingTemplateId.value = null;
    form.value = {
        name: '',
        type: 'residential',
        description: '',
        content: ''
    };
    showModal.value = true;
}

function openEditModal(template) {
    isEditing.value = true;
    editingTemplateId.value = template.id;
    form.value = {
        name: template.name,
        type: template.type,
        description: template.description || '',
        content: template.content
    };
    showModal.value = true;
}

function triggerFileUpload() {
    fileUploadInput.value.click();
}

function handleHtmlFileUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (event) => {
        form.value.content = event.target.result;
    };
    reader.readAsText(file);
}

function saveTemplate() {
    if (!form.value.name || !form.value.content) {
        alert("Iltimos, barcha majburiy maydonlarni to'ldiring!");
        return;
    }

    isProcessing.value = true;

    if (isEditing.value) {
        router.put(route('contract-templates.update', editingTemplateId.value), form.value, {
            onSuccess: () => {
                showModal.value = false;
                isProcessing.value = false;
            },
            onError: () => {
                isProcessing.value = false;
            }
        });
    } else {
        router.post(route('contract-templates.store'), form.value, {
            onSuccess: () => {
                showModal.value = false;
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
        router.delete(route('contract-templates.destroy', template.id));
    }
}

function copyPlaceholder(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Simple micro notification/alert
    }).catch(err => {
        console.error('Nusxalashda xatolik:', err);
    });
}
</script>
