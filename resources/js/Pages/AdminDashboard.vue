<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Admin Dashboard" />
    <AdminLayout>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="glass-card p-6">
                <h3 class="text-sm font-display text-text-muted">{{ $t('total_revenue') }}</h3>
                <p class="text-3xl font-mono text-neon-green mt-2">$2,450,000</p>
            </div>
            <div class="glass-card p-6">
                <h3 class="text-sm font-display text-text-muted">{{ $t('pending_approvals') }}</h3>
                <p class="text-3xl font-mono text-neon-yellow mt-2">14</p>
            </div>
            <div class="glass-card p-6">
                <h3 class="text-sm font-display text-text-muted">{{ $t('active_users') }}</h3>
                <p class="text-3xl font-mono text-neon-cyan mt-2">1,204</p>
            </div>
        </div>

        <div class="mt-8 glass-panel p-6 rounded-2xl">
            <h3 class="text-lg font-display text-white mb-4">{{ $t('live_system_metrics') }}</h3>
            <div class="h-64 border border-white/5 rounded-xl flex items-center justify-center relative overflow-hidden bg-black/20">
                <!-- Mock wave chart -->
                <div class="absolute inset-0 bg-gradient-to-t from-neon-purple/20 to-transparent opacity-50"></div>
                <p class="text-neon-purple font-mono animate-pulse">{{ $t('chart_render_awaiting_data') }}</p>
            </div>
        </div>
    </AdminLayout>
</template>
