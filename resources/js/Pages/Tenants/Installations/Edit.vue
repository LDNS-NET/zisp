<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TextInput from '@/Components/TextInput.vue'
import SelectInput from '@/Components/SelectInput.vue'
import InputLabel from '@/Components/InputLabel.vue'
import InputError from '@/Components/InputError.vue'
import { ArrowLeft, Save } from 'lucide-vue-next'

const props = defineProps({
    installation: Object,
    technicians: Array,
    equipment: Array,
    networkUsers: Array,
})

const form = useForm({
    network_user_id: props.installation.network_user_id,
    technician_id: props.installation.technician_id,
    equipment_id: props.installation.equipment_id,
    customer_name: props.installation.customer_name,
    customer_phone: props.installation.customer_phone,
    customer_email: props.installation.customer_email,
    installation_address: props.installation.installation_address,
    latitude: props.installation.latitude,
    longitude: props.installation.longitude,
    installation_type: props.installation.installation_type,
    service_type: props.installation.service_type,
    status: props.installation.status,
    priority: props.installation.priority,
    scheduled_date: props.installation.scheduled_date,
    scheduled_time: props.installation.scheduled_time,
    estimated_duration: props.installation.estimated_duration,
    installation_notes: props.installation.installation_notes,
    technician_notes: props.installation.technician_notes,
    installation_cost: props.installation.installation_cost,
    payment_collected: props.installation.payment_collected || false,
})

function submit() {
    form.put(route('tenant.installations.update', props.installation.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="`Edit Installation #${installation.installation_number}`" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center gap-4 mb-4">
                        <Link
                            :href="route('tenant.installations.show', installation.id)"
                            class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                        >
                            <ArrowLeft class="w-5 h-5" />
                        </Link>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                Edit Installation #{{ installation.installation_number }}
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Update installation details
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Customer Information -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Customer Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="customer_name" value="Customer Name *" />
                                <TextInput
                                    id="customer_name"
                                    v-model="form.customer_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.customer_name" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="customer_phone" value="Customer Phone *" />
                                <TextInput
                                    id="customer_phone"
                                    v-model="form.customer_phone"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.customer_phone" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="customer_email" value="Customer Email" />
                                <TextInput
                                    id="customer_email"
                                    v-model="form.customer_email"
                                    type="email"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.customer_email" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="network_user_id" value="Network User" />
                                <SelectInput
                                    id="network_user_id"
                                    v-model="form.network_user_id"
                                    class="mt-1 block w-full"
                                >
                                    <option :value="null">Select Network User</option>
                                    <option v-for="user in networkUsers" :key="user.id" :value="user.id">
                                        {{ user.name }} ({{ user.username }})
                                    </option>
                                </SelectInput>
                                <InputError :message="form.errors.network_user_id" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <InputLabel for="installation_address" value="Installation Address *" />
                                <textarea
                                    id="installation_address"
                                    v-model="form.installation_address"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    rows="2"
                                    required
                                ></textarea>
                                <InputError :message="form.errors.installation_address" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="latitude" value="Latitude" />
                                <TextInput
                                    id="latitude"
                                    v-model="form.latitude"
                                    type="number"
                                    step="any"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.latitude" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="longitude" value="Longitude" />
                                <TextInput
                                    id="longitude"
                                    v-model="form.longitude"
                                    type="number"
                                    step="any"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.longitude" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- Installation Details -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Installation Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="technician_id" value="Assigned Technician *" />
                                <SelectInput
                                    id="technician_id"
                                    v-model="form.technician_id"
                                    class="mt-1 block w-full"
                                    required
                                >
                                    <option :value="null">Select Technician</option>
                                    <option v-for="tech in technicians" :key="tech.id" :value="tech.id">
                                        {{ tech.name }}
                                    </option>
                                </SelectInput>
                                <InputError :message="form.errors.technician_id" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="equipment_id" value="Equipment" />
                                <SelectInput
                                    id="equipment_id"
                                    v-model="form.equipment_id"
                                    class="mt-1 block w-full"
                                >
                                    <option :value="null">Select Equipment</option>
                                    <option v-for="equip in equipment" :key="equip.id" :value="equip.id">
                                        {{ equip.name }} ({{ equip.type }})
                                    </option>
                                </SelectInput>
                                <InputError :message="form.errors.equipment_id" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="installation_type" value="Installation Type *" />
                                <SelectInput
                                    id="installation_type"
                                    v-model="form.installation_type"
                                    class="mt-1 block w-full"
                                    required
                                >
                                    <option value="new">New Installation</option>
                                    <option value="relocation">Relocation</option>
                                    <option value="upgrade">Upgrade</option>
                                    <option value="repair">Repair</option>
                                    <option value="maintenance">Maintenance</option>
                                </SelectInput>
                                <InputError :message="form.errors.installation_type" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="service_type" value="Service Type *" />
                                <SelectInput
                                    id="service_type"
                                    v-model="form.service_type"
                                    class="mt-1 block w-full"
                                    required
                                >
                                    <option value="fiber">Fiber</option>
                                    <option value="wireless">Wireless</option>
                                    <option value="hybrid">Hybrid</option>
                                </SelectInput>
                                <InputError :message="form.errors.service_type" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="status" value="Status *" />
                                <SelectInput
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full"
                                    required
                                >
                                    <option value="scheduled">Scheduled</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="on_hold">On Hold</option>
                                </SelectInput>
                                <InputError :message="form.errors.status" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="priority" value="Priority *" />
                                <SelectInput
                                    id="priority"
                                    v-model="form.priority"
                                    class="mt-1 block w-full"
                                    required
                                >
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </SelectInput>
                                <InputError :message="form.errors.priority" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="scheduled_date" value="Scheduled Date *" />
                                <TextInput
                                    id="scheduled_date"
                                    v-model="form.scheduled_date"
                                    type="date"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.scheduled_date" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="scheduled_time" value="Scheduled Time" />
                                <TextInput
                                    id="scheduled_time"
                                    v-model="form.scheduled_time"
                                    type="time"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.scheduled_time" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="estimated_duration" value="Estimated Duration (minutes)" />
                                <TextInput
                                    id="estimated_duration"
                                    v-model="form.estimated_duration"
                                    type="number"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.estimated_duration" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="installation_cost" value="Installation Cost" />
                                <TextInput
                                    id="installation_cost"
                                    v-model="form.installation_cost"
                                    type="number"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.installation_cost" class="mt-2" />
                            </div>

                            <div class="flex items-center">
                                <input
                                    id="payment_collected"
                                    v-model="form.payment_collected"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <label for="payment_collected" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                    Payment Collected
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Notes</h3>
                        <div class="space-y-6">
                            <div>
                                <InputLabel for="installation_notes" value="Installation Notes" />
                                <textarea
                                    id="installation_notes"
                                    v-model="form.installation_notes"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    rows="3"
                                ></textarea>
                                <InputError :message="form.errors.installation_notes" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="technician_notes" value="Technician Notes" />
                                <textarea
                                    id="technician_notes"
                                    v-model="form.technician_notes"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    rows="3"
                                ></textarea>
                                <InputError :message="form.errors.technician_notes" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-3">
                        <Link
                            :href="route('tenant.installations.show', installation.id)"
                            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                        >
                            <Save class="w-4 h-4 mr-2" />
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
