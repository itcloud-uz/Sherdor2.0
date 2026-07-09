<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section>
            <h2 class="text-lg font-bold font-display text-gray-900">
                Parolni yangilash
            </h2>

            <p class="mt-1 text-sm text-gray-500 font-mono">
                Hisobingiz xavfsizligini ta'minlash uchun uzun va tasodifiy paroldan foydalaning.
            </p>

        <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
            <div>
                <InputLabel for="current_password" value="Joriy parol" class="font-mono text-xs uppercase tracking-widest text-gray-500" />

                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-2 block w-full border-gray-200 rounded-xl focus:border-blue-500 focus:ring-blue-500"
                    autocomplete="current-password"
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-2"
                />
            </div>

            <div>
                <InputLabel for="password" value="Yangi parol" class="font-mono text-xs uppercase tracking-widest text-gray-500" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-2 block w-full border-gray-200 rounded-xl focus:border-blue-500 focus:ring-blue-500"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Parolni tasdiqlang"
                    class="font-mono text-xs uppercase tracking-widest text-gray-500"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-2 block w-full border-gray-200 rounded-xl focus:border-blue-500 focus:ring-blue-500"
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-2"
                />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing" class="rounded-xl px-6 py-2.5 bg-blue-600 hover:bg-blue-700 shadow-sm text-xs tracking-wider">Saqlash</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out duration-300"
                    enter-from-class="opacity-0 -translate-y-2"
                    leave-active-class="transition ease-in-out duration-300"
                    leave-to-class="opacity-0 translate-y-2"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm font-medium text-emerald-600 bg-emerald-50 px-3 py-1 rounded-lg"
                    >
                        Saqlandi.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
