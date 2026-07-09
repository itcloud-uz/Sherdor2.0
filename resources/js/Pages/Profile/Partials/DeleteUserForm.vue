<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-bold font-display text-red-600">
                Hisobni o'chirish
            </h2>

            <p class="mt-1 text-sm text-gray-500 font-mono">
                Hisobingiz o'chirilgach, uning barcha ma'lumotlari butunlay o'chiriladi.
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion" class="rounded-xl px-6 py-2.5 bg-red-600 hover:bg-red-700 shadow-sm text-xs tracking-wider">Hisobni o'chirish</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2
                    class="text-lg font-bold font-display text-gray-900"
                >
                    Hisobingizni o'chirishga ishonchingiz komilmi?
                </h2>

                <p class="mt-1 text-sm text-gray-500 font-mono">
                    Hisobingiz o'chirilgach, uning barcha ma'lumotlari butunlay o'chiriladi. Hisobingizni o'chirishni tasdiqlash uchun parolingizni kiriting.
                </p>

                <div class="mt-6">
                    <InputLabel
                        for="password"
                        value="Parol"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-2 block w-3/4 border-gray-200 rounded-xl focus:border-red-500 focus:ring-red-500"
                        placeholder="Parolingizni kiriting"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal" class="rounded-xl">
                        Bekor qilish
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3 rounded-xl bg-red-600 hover:bg-red-700"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        O'chirish
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
