<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SellerLayout from '@/Layouts/SellerLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();
const CurrentLayout = computed(() => {
    const roles = page.props.auth.user?.roles || [];
    const roleNames = roles.map(r => typeof r === 'string' ? r : r.name);
    if (roleNames.includes('Sotuvchi') || roleNames.includes('Operator')) {
        return SellerLayout;
    }
    return AdminLayout;
});

const props = defineProps({
    campaigns: Array,
    eskizSetting: Object,
    templates: Array,
    automations: Array,
    audiences: Array
});

const activeTab = ref('config'); // 'config', 'templates', 'automations', 'audiences'

// ----- TAB 1: Config -----
const eskizEmail = ref(props.eskizSetting?.email || '');
const eskizPassword = ref('');

// ----- TAB 2: Templates -----
const newTemplateContent = ref('');
const maxChars = 160;
const charCount = computed(() => newTemplateContent.value.length);
const isOverLimit = computed(() => charCount.value > maxChars);

function insertVariable(variable) {
    newTemplateContent.value += ` {${variable}} `;
}

// ----- Mock AI Logs -----
const aiLogs = ref([
    { id: 1, time: '10:05:22', type: 'INFO', msg: 'log_msg_1' },
    { id: 2, time: '10:06:14', type: 'ACTION', msg: 'log_msg_2' },
    { id: 3, time: '10:12:05', type: 'WARN', msg: 'log_msg_3' }
]);
</script>

<template>
    <Head title="AI Agents & SMS" />
    <component :is="CurrentLayout">
        <div class="grid grid-cols-1 xl:grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 h-[calc(100vh-10rem)]">
            
            <!-- LEFT: Advanced SMS Hub -->
            <div class="xl:col-span-2 flex flex-col gap-6">
                <!-- Tab Navigation -->
                <div class="flex border-b border-gray-200 overflow-x-auto hide-scrollbar">
                    <button 
                        @click="activeTab = 'config'"
                        :class="['px-6 py-3 text-xs font-display font-bold uppercase tracking-wider transition-all whitespace-nowrap', activeTab === 'config' ? 'text-blue-600 border-b-2 border-blue-500 ' : 'text-gray-500 hover:text-gray-900']"
                    >
                        📡 {{ $t('eskiz_config') }}
                    </button>
                    <button 
                        @click="activeTab = 'templates'"
                        :class="['px-6 py-3 text-xs font-display font-bold uppercase tracking-wider transition-all whitespace-nowrap', activeTab === 'templates' ? 'text-neon-purple border-b-2 border-neon-purple ' : 'text-gray-500 hover:text-gray-900']"
                    >
                        📝 {{ $t('templates') }}
                    </button>
                    <button 
                        @click="activeTab = 'automations'"
                        :class="['px-6 py-3 text-xs font-display font-bold uppercase tracking-wider transition-all whitespace-nowrap', activeTab === 'automations' ? 'text-amber-600 border-b-2 border-amber-500 ' : 'text-gray-500 hover:text-gray-900']"
                    >
                        ⚙️ {{ $t('automations') }}
                    </button>
                    <button 
                        @click="activeTab = 'audiences'"
                        :class="['px-6 py-3 text-xs font-display font-bold uppercase tracking-wider transition-all whitespace-nowrap', activeTab === 'audiences' ? 'text-emerald-600 border-b-2 border-emerald-500 ' : 'text-gray-500 hover:text-gray-900']"
                    >
                        👥 {{ $t('audience_broadcast') }}
                    </button>
                </div>

                <!-- Tab Content Area -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 rounded-b-2xl rounded-tr-2xl flex-1 flex flex-col border border-gray-200 relative overflow-y-auto">
                    
                    <!-- TAB 1: Config -->
                    <div v-if="activeTab === 'config'" class="space-y-8 animate-fade-in">
                        <div class="flex flex-col md:flex-row gap-6">
                            <!-- Balance Widget -->
                            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 flex-1 relative overflow-hidden border border-gray-200 group">
                                <div :class="['absolute -right-10 -bottom-10 w-32 h-32 blur-[50px] rounded-full transition-all duration-1000', props.eskizSetting?.bearer_token ? 'bg-emerald-50 group-hover:bg-emerald-50' : 'bg-indigo-50']"></div>
                                <h3 class="text-sm font-mono text-gray-500 uppercase tracking-widest mb-2">{{ $t('live_eskiz_balance') }}</h3>
                                <div class="flex items-baseline gap-2">
                                    <span :class="['text-4xl font-display font-bold drop-shadow-sm', props.eskizSetting?.bearer_token ? 'text-emerald-600' : 'text-indigo-600']">
                                        {{ props.eskizSetting?.current_balance ? Number(props.eskizSetting.current_balance).toLocaleString() : '0' }}
                                    </span>
                                    <span class="text-gray-900/50 text-sm font-mono tracking-widest uppercase">UZS</span>
                                </div>
                                <div class="mt-4 flex items-center gap-2">
                                    <span :class="['w-2 h-2 rounded-full animate-pulse', props.eskizSetting?.bearer_token ? 'bg-emerald-600' : 'bg-indigo-600']"></span>
                                    <span class="text-xs font-mono text-gray-900/70">{{ props.eskizSetting?.bearer_token ? $t('connected') : $t('offline') }}</span>
                                </div>
                            </div>
                            
                            <!-- Auth Form -->
                            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 flex-1 border border-gray-200">
                                <h3 class="text-sm font-display text-gray-900 mb-4">{{ $t('api_credentials') }}</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1">{{ $t('email') }}</label>
                                        <input v-model="eskizEmail" type="email" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan/50 outline-none" placeholder="eskiz@domain.uz" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1">{{ $t('password') }}</label>
                                        <input v-model="eskizPassword" type="password" class="w-full border border-gray-200 rounded-lg px-4 py-2 text-gray-900 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-neon-cyan/50 outline-none" placeholder="••••••••" />
                                    </div>
                                    <button class="w-full px-6 py-2 bg-blue-50 border border-blue-200 text-blue-600 text-sm font-mono font-bold rounded-lg hover:bg-blue-600 hover:text-black transition-all shadow-sm hover:shadow-sm">
                                        {{ $t('connect_get_token') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: Templates -->
                    <div v-if="activeTab === 'templates'" class="space-y-6 animate-fade-in h-full flex flex-col">
                        <div class="flex-1 flex flex-col">
                            <label class="text-xs font-mono text-gray-500 mb-2 uppercase tracking-widest">{{ $t('create_new_template') }}</label>
                            
                            <!-- Variables Toolbar -->
                            <div class="flex gap-2 mb-2 overflow-x-auto hide-scrollbar pb-1">
                                <button @click="insertVariable('client_name')" class="px-2 py-1 border border-gray-200 text-gray-900/80 text-[10px] font-mono rounded hover:border-neon-purple hover:text-neon-purple transition-colors">{client_name}</button>
                                <button @click="insertVariable('debt_amount')" class="px-2 py-1 border border-gray-200 text-gray-900/80 text-[10px] font-mono rounded hover:border-neon-purple hover:text-neon-purple transition-colors">{debt_amount}</button>
                                <button @click="insertVariable('date')" class="px-2 py-1 border border-gray-200 text-gray-900/80 text-[10px] font-mono rounded hover:border-neon-purple hover:text-neon-purple transition-colors">{date}</button>
                            </div>

                            <div class="relative flex-1 flex flex-col max-h-[150px] mb-6">
                                <textarea 
                                    v-model="newTemplateContent"
                                    class="w-full flex-1 border border-gray-200 rounded-xl p-4 text-gray-900 font-mono text-sm focus:border-neon-purple focus:ring-1 focus:ring-neon-purple/50 transition-all resize-none shadow-inner"
                                    placeholder="..."
                                ></textarea>
                                <div class="absolute bottom-4 right-4 flex items-center gap-4">
                                    <span :class="['text-xs font-mono font-bold', isOverLimit ? 'text-indigo-600 animate-pulse' : 'text-neon-purple']">
                                        [ {{ charCount }} / {{ maxChars }} ]
                                    </span>
                                    <button 
                                        :disabled="isOverLimit || charCount === 0"
                                        class="px-4 py-1.5 bg-neon-purple/20 border border-neon-purple/50 text-neon-purple font-mono text-xs font-bold rounded shadow-sm hover:bg-neon-purple hover:text-gray-900 transition-all disabled:opacity-50"
                                    >
                                        {{ $t('save_template') }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto">
                                <h3 class="text-xs font-mono text-gray-500 mb-4 uppercase tracking-widest">{{ $t('saved_templates') }}</h3>
                                <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div v-for="tpl in templates" :key="tpl.id" class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-200 hover:border-neon-purple/30 group">
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="text-sm font-bold text-gray-900">{{ tpl.name }}</h4>
                                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 border border-emerald-200">{{ $t('active') }}</span>
                                        </div>
                                        <p class="text-xs text-gray-500 font-mono">{{ tpl.content }}</p>
                                    </div>
                                    <div v-if="templates?.length === 0" class="col-span-full py-8 text-center border border-dashed border-gray-200 rounded-xl text-gray-500 font-mono text-xs">
                                        {{ $t('no_templates_found') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: Automations -->
                    <div v-if="activeTab === 'automations'" class="space-y-6 animate-fade-in">
                        <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-amber-200 bg-amber-50">
                            <h3 class="text-sm font-display text-amber-600 mb-4">{{ $t('create_automation_rule') }}</h3>
                            <div class="flex flex-col lg:flex-row items-center gap-4 text-sm font-mono text-gray-900/80">
                                <span>{{ $t('when') }}</span>
                                <select class="border border-gray-300 rounded px-3 py-2 text-gray-900 outline-none focus:border-amber-500 flex-1 lg:flex-none min-w-[200px]">
                                    <option value="new_contract">{{ $t('rule_new_contract') }}</option>
                                    <option value="payment_overdue">{{ $t('rule_payment_overdue') }}</option>
                                    <option value="birthday">{{ $t('rule_birthday') }}</option>
                                </select>
                                <span>{{ $t('happens_send') }}</span>
                                <select class="border border-gray-300 rounded px-3 py-2 text-gray-900 outline-none focus:border-amber-500 flex-1 lg:flex-none min-w-[200px]">
                                    <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">{{ tpl.name }}</option>
                                </select>
                                <span>{{ $t('at') }}</span>
                                <select class="border border-gray-300 rounded px-3 py-2 text-gray-900 outline-none focus:border-amber-500 flex-1 lg:flex-none min-w-[150px]">
                                    <option value="immediate">{{ $t('time_immediate') }}</option>
                                    <option value="3_days_before">{{ $t('time_3_days_before') }}</option>
                                    <option value="daily_0900">{{ $t('time_daily_0900') }}</option>
                                </select>
                                <button class="w-full lg:w-auto px-6 py-2 bg-amber-500 text-white font-bold border border-amber-500 rounded hover:bg-white transition-all shadow-sm">
                                    {{ $t('add_rule') }}
                                </button>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-xs font-mono text-gray-500 mb-4 uppercase tracking-widest">{{ $t('active_automation_engine') }}</h3>
                            <div class="space-y-3">
                                <div v-for="rule in automations" :key="rule.id" class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 border border-gray-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="w-2 h-2 rounded-full bg-amber-500 shadow-sm"></span>
                                            <h4 class="text-sm font-bold text-gray-900">{{ rule.name }}</h4>
                                        </div>
                                        <p class="text-xs font-mono text-gray-500">{{ $t('trigger') }}: <span class="text-blue-600">{{ rule.event_trigger }}</span> | {{ $t('template') }}: <span class="text-neon-purple">{{ rule.template?.name }}</span></p>
                                    </div>
                                    <div class="text-right flex items-center gap-4">
                                        <span class="text-xs font-mono text-gray-900/50 bg-gray-500/50 px-2 py-1 rounded border border-gray-100">{{ rule.schedule_time }}</span>
                                        <button class="text-xs font-mono text-indigo-600 hover:text-gray-900 transition-colors border border-neon-magenta/30 px-3 py-1 rounded bg-indigo-50">{{ $t('pause') }}</button>
                                    </div>
                                </div>
                                <div v-if="automations?.length === 0" class="py-8 text-center border border-dashed border-gray-200 rounded-xl text-gray-500 font-mono text-xs">
                                    {{ $t('no_automation_rules') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: Audience & Broadcast -->
                    <div v-if="activeTab === 'audiences'" class="space-y-6 animate-fade-in">
                        <div class="grid grid-cols-1 md:grid-cols-1 sm:grid-cols-2 gap-6">
                            <!-- Mass Broadcast -->
                            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-emerald-200 relative overflow-hidden group">
                                <div class="absolute -right-10 -top-10 w-32 h-32 bg-emerald-50 blur-[50px] rounded-full group-hover:bg-emerald-50 transition-colors"></div>
                                <h3 class="text-sm font-display text-emerald-600 mb-4 border-b border-emerald-200 pb-2">{{ $t('launch_manual_broadcast') }}</h3>
                                <div class="space-y-4 relative z-10">
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1">{{ $t('select_target_audience') }}</label>
                                        <select class="w-full border border-gray-200 rounded p-2 text-gray-900 text-sm font-mono focus:border-emerald-500 outline-none">
                                            <option>{{ $t('aud_all_clients') }}</option>
                                            <option>{{ $t('aud_active_debtors') }}</option>
                                            <option>{{ $t('aud_custom_list') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-mono text-gray-500 mb-1">{{ $t('select_template') }}</label>
                                        <select class="w-full border border-gray-200 rounded p-2 text-gray-900 text-sm font-mono focus:border-emerald-500 outline-none">
                                            <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">{{ tpl.name }}</option>
                                        </select>
                                    </div>
                                    <button class="w-full px-6 py-3 bg-emerald-50 border border-emerald-500 text-emerald-600 font-mono font-bold rounded shadow-sm hover:bg-emerald-600 hover:text-black transition-all">
                                        {{ $t('transmit_to_audience') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Upload CSV -->
                            <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 border border-gray-200 flex flex-col justify-center items-center text-center border-dashed hover:border-white/30 transition-colors cursor-pointer">
                                <span class="text-4xl mb-4 opacity-50">📁</span>
                                <h3 class="text-gray-900 font-bold mb-1">{{ $t('upload_csv_target_list') }}</h3>
                                <p class="text-xs text-gray-500 font-mono max-w-[200px]">{{ $t('format_csv') }}</p>
                                <button class="mt-4 px-4 py-2 border border-gray-300 text-gray-900 text-xs font-mono rounded hover:bg-white hover:text-black transition-colors">
                                    {{ $t('browse_file') }}
                                </button>
                            </div>
                        </div>

                        <!-- Recent Campaigns -->
                        <div class="mt-8">
                            <h3 class="text-xs font-mono text-gray-500 uppercase tracking-widest mb-4">{{ $t('recent_transmissions') }}</h3>
                            <div class="space-y-3">
                                <div v-for="campaign in campaigns" :key="campaign.id" class="bg-white border border-gray-200 shadow-sm rounded-xl p-3 border border-gray-100 flex justify-between items-center">
                                    <p class="text-xs text-gray-900/80 font-mono truncate max-w-[300px]">{{ campaign.message }}</p>
                                    <div class="flex gap-4 text-[10px] font-mono items-center">
                                        <span class="text-gray-500">{{ campaign.recipients_count }} {{ $t('rcpts') }}</span>
                                        <span :class="['px-2 py-0.5 rounded border', campaign.status === 'sent' ? 'bg-emerald-50 border-emerald-200 text-emerald-600' : 'bg-amber-50 border-amber-200 text-amber-600']">
                                            {{ campaign.status.toUpperCase() }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT: AI Terminal Emulator (Sidebar on large screens) -->
            <div class="bg-white border border-slate-200 shadow-sm rounded-2xl flex flex-col overflow-hidden relative h-full min-h-[400px]">
                <div class="h-10 border-b border-slate-200 bg-slate-50 flex items-center px-4 justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-slate-300"></span>
                        <span class="w-3 h-3 rounded-full bg-slate-300"></span>
                        <span class="w-3 h-3 rounded-full bg-slate-300"></span>
                    </div>
                    <span class="text-[10px] font-mono text-slate-500 tracking-widest uppercase opacity-70">{{ $t('gemini_sys_log') }}</span>
                </div>
                
                <div class="flex-1 p-4 font-mono text-xs overflow-y-auto space-y-3">
                    <div class="text-slate-400 mb-4">
                        > {{ $t('log_init_ai') }}<br>
                        > {{ $t('log_conn_eskiz') }}<br>
                        > {{ $t('log_await_telemetry') }}
                    </div>

                    <div v-for="log in aiLogs" :key="log.id" class="flex flex-col gap-1 border-b border-slate-100 pb-2">
                        <div class="flex justify-between text-slate-400 text-[10px]">
                            <span>[{{ log.time }}]</span>
                            <span :class="{ 'text-blue-600': log.type === 'INFO', 'text-amber-600': log.type === 'WARN', 'text-indigo-600': log.type === 'ACTION', 'text-emerald-600': log.type === 'SUCCESS' }">[{{ log.type }}]</span>
                        </div>
                        <span class="text-slate-700 leading-relaxed">{{ $t(log.msg) }}</span>
                    </div>
                    
                    <div class="mt-4 flex items-center gap-2 text-slate-400 animate-pulse">
                        <span>></span>
                        <span class="w-2 h-4 bg-slate-400 inline-block"></span>
                    </div>
                </div>
            </div>

        </div>
    </component>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-in-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
