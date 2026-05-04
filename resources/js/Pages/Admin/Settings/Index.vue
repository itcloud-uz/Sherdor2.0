<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    settings: Object
});

const form = useForm({
    company_name: props.settings?.company_name || 'ITCloud Sher-Dor ERP',
    primary_theme_color: props.settings?.primary_theme_color || '#00F0FF',
    neon_glow_intensity: props.settings?.neon_glow_intensity || '0 0 15px',
    default_currency: props.settings?.default_currency || 'USD',
    tax_rate: props.settings?.tax_rate || 12.00,
    default_penalty_rate: props.settings?.default_penalty_rate || 0.10,
    logo: null,
});

function submit() {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => alert('Settings updated successfully!')
    });
}
</script>

<template>
    <Head title="System Settings" />

    <AdminLayout>
        <template #header>
            <h2 class="font-display font-semibold text-2xl text-white uppercase tracking-widest text-shadow-glow">
                ⚙️ {{ $t('settings') }}
            </h2>
        </template>

        <div class="p-8 max-w-4xl mx-auto space-y-8 animate-[fadeIn_0.5s_ease-out]">
            <div class="glass-panel p-8 rounded-2xl border border-white/5">
                <form @submit.prevent="submit" class="space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Branding -->
                        <div class="space-y-4">
                            <h3 class="text-neon-cyan font-mono text-sm tracking-widest uppercase border-b border-white/10 pb-2">{{ $t('branding_appearance') }}</h3>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Company Name</label>
                                <input v-model="form.company_name" type="text" class="glass-input w-full">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Primary Theme Color</label>
                                <div class="flex items-center gap-4">
                                    <input v-model="form.primary_theme_color" type="color" class="w-10 h-10 rounded cursor-pointer bg-transparent border-0 p-0">
                                    <input v-model="form.primary_theme_color" type="text" class="glass-input flex-1 font-mono">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">{{ $t('custom_logo_desc') }}</label>
                                <input type="file" @input="form.logo = $event.target.files[0]" class="glass-input w-full text-xs" accept="image/*">
                            </div>
                        </div>

                        <!-- Financials -->
                        <div class="space-y-4">
                            <h3 class="text-neon-cyan font-mono text-sm tracking-widest uppercase border-b border-white/10 pb-2">Financial Defaults</h3>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Default Currency</label>
                                <select v-model="form.default_currency" class="glass-input w-full bg-black/50">
                                    <option value="USD">USD ($)</option>
                                    <option value="UZS">UZS (So'm)</option>
                                    <option value="RUB">RUB (₽)</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Global Tax Rate (%)</label>
                                <input v-model="form.tax_rate" type="number" step="0.01" class="glass-input w-full text-neon-green font-bold">
                            </div>

                            <div>
                                <label class="block text-[10px] font-mono text-text-muted mb-1 uppercase">Default Daily Penalty Rate (%)</label>
                                <input v-model="form.default_penalty_rate" type="number" step="0.01" class="glass-input w-full text-neon-magenta font-bold">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-6 border-t border-white/10">
                        <button type="submit" :disabled="form.processing" class="px-8 py-3 bg-neon-cyan text-black font-bold uppercase tracking-widest rounded hover:shadow-[0_0_20px_#00F0FF] transition-all disabled:opacity-50 flex items-center gap-2">
                            <span v-if="form.processing" class="animate-spin">🌀</span>
                            {{ $t('save_settings') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </AdminLayout>
</template>
