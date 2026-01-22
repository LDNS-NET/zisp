<script setup>
import { useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'

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
    installation_type: props.installation.installation_type,
    service_type: props.installation.service_type,
    status: props.installation.status,
    priority: props.installation.priority,
    scheduled_date: props.installation.scheduled_date,
    scheduled_time: props.installation.scheduled_time,
    estimated_duration: props.installation.estimated_duration,
    installation_notes: props.installation.installation_notes,
})

function submit() {
    form.put(route('tenant.installations.update', props.installation.id))
}
</script>

<template>
    <Head title="Edit Installation" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
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
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Customer Information -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Customer Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                    <select
                                        id="network_user_id"
                                        v-model="form.network_user_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option :value="null">Select Network User</option>
                                        <option v-for="user in networkUsers" :key="user.id" :value="user.id">
                                            {{ user.username }} - {{ user.full_name }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.network_user_id" class="mt-2" />
                                </div>
                            </div>

                            <div class="mt-4">
                                <InputLabel for="installation_address" value="Installation Address *" />
                                <textarea
                                    id="installation_address"
                                    v-model="form.installation_address"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    required
                                ></textarea>
                                <InputError :message="form.errors.installation_address" class="mt-2" />
                            </div>
                        </div>

                        <!-- Installation Details -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Installation Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="installation_type" value="Installation Type *" />
                                    <select
                                        id="installation_type"
                                        v-model="form.installation_type"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    >
                                        <option value="new">New</option>
                                        <option value="relocation">Relocation</option>
                                        <option value="upgrade">Upgrade</option>
                                        <option value="repair">Repair</option>
                                        <option value="maintenance">Maintenance</option>
                                    </select>
                                    <InputError :message="form.errors.installation_type" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="service_type" value="Service Type *" />
                                    <select
                                        id="service_type"
                                        v-model="form.service_type"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    >
                                        <option value="fiber">Fiber</option>
                                        <option value="wireless">Wireless</option>
                                        <option value="hybrid">Hybrid</option>
                                    </select>
                                    <InputError :message="form.errors.service_type" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="status" value="Status *" />
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    >
                                        <option value="new">New</option>
                                        <option value="pending">Pending</option>
                                        <option value="scheduled">Scheduled</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="on_hold">On Hold</option>
                                    </select>
                                    <InputError :message="form.errors.status" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="priority" value="Priority *" />
                                    <select
                                        id="priority"
                                        v-model="form.priority"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    >
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                    <InputError :message="form.errors.priority" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="technician_id" value="Assigned Technician *" />
                                    <select
                                        id="technician_id"
                                        v-model="form.technician_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    >
                                        <option :value="null">Select Technician</option>
                                        <option v-for="tech in technicians" :key="tech.id" :value="tech.id">
                                            {{ tech.name }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.technician_id" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="equipment_id" value="Equipment" />
                                    <select
                                        id="equipment_id"
                                        v-model="form.equipment_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    >
                                        <option :value="null">Select Equipment</option>
                                        <option v-for="eq in equipment" :key="eq.id" :value="eq.id">
                                            {{ eq.name }} ({{ eq.type }})
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.equipment_id" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Schedule -->
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Schedule</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <InputLabel for="scheduled_date" value="Scheduled Date" />
                                    <input
                                        id="scheduled_date"
                                        v-model="form.scheduled_date"
                                        type="date"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    />
                                    <InputError :message="form.errors.scheduled_date" class="mt-2" />
                                </div>

                                <div>
                                    <InputLabel for="scheduled_time" value="Scheduled Time" />
                                    <input
                                        id="scheduled_time"
                                        v-model="form.scheduled_time"
                                        type="time"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
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
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <InputLabel for="installation_notes" value="Installation Notes" />
                            <textarea
                                id="installation_notes"
                                v-model="form.installation_notes"
                                rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            ></textarea>
                            <InputError :message="form.errors.installation_notes" class="mt-2" />
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3">
                            <Link
                                :href="route('tenant.installations.show', installation.id)"
                                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600"
                            >
                                Cancel
                            </Link>
                            <PrimaryButton :disabled="form.processing">
                                {{ form.processing ? 'Updating...' : 'Update Installation' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
