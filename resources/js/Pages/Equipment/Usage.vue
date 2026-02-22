<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { 
    Plus, Trash2, ArrowLeft, Save, 
    ShoppingCart, Calculator, Package, 
    AlertTriangle, History
} from 'lucide-vue-next';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    equipment: Array
});

const form = useForm({
    items: [
        { equipment_id: '', quantity: 1, details: '' }
    ],
    details: ''
});

const addItem = () => {
    form.items.push({ equipment_id: '', quantity: 1, details: '' });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const getEquipment = (id) => {
    return props.equipment.find(e => e.id == id);
};

const calculateItemTotal = (item) => {
    const equip = getEquipment(item.equipment_id);
    if (!equip) return 0;
    return (equip.price || 0) * item.quantity;
};

const grandTotal = computed(() => {
    return form.items.reduce((acc, item) => acc + calculateItemTotal(item), 0);
});

const submit = () => {
    form.post(route('equipment.usage.store'));
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES',
    }).format(value);
};
</script>

<template>
    <Head title="Log Equipment Usage" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <Link :href="route('equipment.index')" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition-colors">
                        <ArrowLeft class="w-5 h-5 text-gray-500" />
                    </Link>
                    <div>
                        <h2 class="font-bold text-2xl text-gray-800 dark:text-white leading-tight">
                            Log Equipment Usage
                        </h2>
                        <p class="text-sm text-gray-500">Record equipment consumption by technicians or staff</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-xl sm:rounded-2xl overflow-hidden">
                    <form @submit.prevent="submit" class="p-8">
                        <div class="flex items-center justify-between mb-8 border-b dark:border-gray-800 pb-6">
                            <div class="flex items-center gap-4">
                                <div class="bg-blue-50 dark:bg-blue-900/20 p-3 rounded-xl">
                                    <ShoppingCart class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <h3 class="text-xl font-bold dark:text-white">Usage Report Form</h3>
                            </div>
                            <div class="text-right">
                                <span class="text-xs uppercase text-gray-400 font-bold block mb-1">Total Valuation</span>
                                <span class="text-3xl font-black text-blue-600 dark:text-blue-400">{{ formatCurrency(grandTotal) }}</span>
                            </div>
                        </div>

                        <!-- Global Details -->
                        <div class="mb-10">
                            <InputLabel for="details" value="General Usage Details / Project Name" />
                            <textarea
                                v-model="form.details"
                                id="details"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-xl shadow-sm h-24 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="e.g. Main Street Fiber Installation Phase 2"
                            ></textarea>
                            <InputError :message="form.errors.details" class="mt-2" />
                        </div>

                        <!-- Items List -->
                        <div class="space-y-6 mb-10">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-gray-700 dark:text-gray-300">Items Used</h4>
                                <button type="button" @click="addItem" class="flex items-center gap-2 text-sm font-bold text-blue-600 hover:text-blue-700 transition-colors">
                                    <Plus class="w-4 h-4" />
                                    Add Another Item
                                </button>
                            </div>

                            <div v-for="(item, index) in form.items" :key="index" class="relative group">
                                <div class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 transition-all hover:bg-gray-100/50 dark:hover:bg-gray-800">
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                                        <!-- Equipment Selection -->
                                        <div class="md:col-span-5">
                                            <InputLabel :for="'item_' + index" value="Select Equipment" />
                                            <select
                                                v-model="item.equipment_id"
                                                :id="'item_' + index"
                                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded-lg focus:ring-blue-500 focus:border-blue-500"
                                            >
                                                <option disabled value="">Select an item</option>
                                                <option v-for="equip in equipment" :key="equip.id" :value="equip.id">
                                                    {{ equip.name }} (Stock: {{ equip.quantity }} {{ equip.unit }})
                                                </option>
                                            </select>
                                            <InputError :message="form.errors[`items.${index}.equipment_id`]" class="mt-2" />
                                        </div>

                                        <!-- Quantity -->
                                        <div class="md:col-span-3">
                                            <InputLabel :for="'qty_' + index" :value="`Quantity Used (${getEquipment(item.equipment_id)?.unit || 'unit'})`" />
                                            <TextInput
                                                v-model="item.quantity"
                                                :id="'qty_' + index"
                                                type="number"
                                                step="0.01"
                                                class="mt-1 block w-full"
                                                :max="getEquipment(item.equipment_id)?.quantity"
                                                min="0.01"
                                            />
                                            <InputError :message="form.errors[`items.${index}.quantity`]" class="mt-2" />
                                        </div>

                                        <!-- Item Value (Calculated) -->
                                        <div class="md:col-span-3 flex flex-col justify-end">
                                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5">
                                                <span class="text-xs text-gray-400 block mb-0.5">Item Value</span>
                                                <span class="font-mono font-bold">{{ formatCurrency(calculateItemTotal(item)) }}</span>
                                            </div>
                                        </div>

                                        <!-- Remove Button -->
                                        <div class="md:col-span-1 flex items-center justify-end">
                                            <button 
                                                type="button" 
                                                @click="removeItem(index)" 
                                                class="p-2 text-gray-400 hover:text-red-600 transition-colors"
                                                v-if="form.items.length > 1"
                                            >
                                                <Trash2 class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Item Specific Details -->
                                    <div class="mt-4">
                                        <TextInput
                                            v-model="item.details"
                                            class="w-full text-sm placeholder:text-gray-400 border-dashed border-gray-200 focus:border-solid focus:border-blue-500"
                                            placeholder="Specific details for this item (optional)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-between pt-8 border-t dark:border-gray-800">
                            <div class="flex items-center gap-6 text-sm text-gray-500">
                                <div class="flex items-center gap-2">
                                    <Package class="w-4 h-4" />
                                    <span>{{ form.items.length }} Items selected</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Calculator class="w-4 h-4" />
                                    <span>Live Valuation Active</span>
                                </div>
                            </div>
                            <div class="flex gap-4">
                                <SecondaryButton type="button" @click="$inertia.visit(route('equipment.index'))">Cancel</SecondaryButton>
                                <PrimaryButton :disabled="form.processing" class="flex items-center gap-2">
                                    <Save class="w-4 h-4" />
                                    Submit Usage Report
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
