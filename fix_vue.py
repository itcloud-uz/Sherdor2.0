import re

file_path = 'resources/js/Pages/Admin/CRM/Index.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

replacement = '''<div v-if="selectedContract.payment_type !== 'full'" class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 bg-gray-500/50">
                            <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">{{ ('monthly_schedule_payment') }}</p>
                            <p class="text-lg font-bold text-blue-600 font-mono">{{ selectedContract.payment_schedules?.length ? (selectedContract.payment_schedules[0].expected_amount) : '0' }}</p>
                        </div>
                        <div v-else class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 bg-gray-500/50">
                            <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">To'lov turi</p>
                            <p class="text-lg font-bold text-emerald-600 font-mono">Bir martalik (100%)</p>
                        </div>'''

target = '''<div class="bg-white border border-gray-200 shadow-sm rounded-xl p-4 rounded-xl border border-gray-200 bg-gray-500/50">
                            <p class="text-[10px] font-mono text-gray-500 uppercase tracking-widest mb-1">{{ ('monthly_schedule_payment') }}</p>
                            <p class="text-lg font-bold text-blue-600 font-mono">{{ selectedContract.payment_schedules?.length ? (selectedContract.payment_schedules[0].expected_amount) : '0' }}</p>
                        </div>'''

if target in content:
    content = content.replace(target, replacement)
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(content)
    print("Updated Index.vue")
else:
    print("Target not found in Index.vue")
