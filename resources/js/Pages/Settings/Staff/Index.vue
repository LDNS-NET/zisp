<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { 
    Users, 
    UserPlus, 
    Shield, 
    Mail, 
    Phone, 
    Trash2, 
    UserX, 
    UserCheck,
    Edit2,
    Lock,
    Clock,
    Globe,
    Smartphone,
    Activity,
    Save,
    ChevronRight,
    Search,
    ShieldAlert
} from 'lucide-vue-next';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const toast = useToast();
const props = defineProps({
    users: Array,
    roles: Array,
    permissions: Array,
    activities: Array,
});

const showModal = ref(false);
const showSecurityModal = ref(false);
const securityTab = ref('hours');
const editingUser = ref(null);

const staffForm = useForm({
    name: '',
    email: '',
    username: '',
    phone: '',
    role: '',
    password: '',
    password_confirmation: '',
});

const securityForm = useForm({
    working_hours: {},
    allowed_ips: [],
    is_device_lock_enabled: false,
    max_devices: 1,
    permissions: [],
});

const newIp = ref('');
const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

const openCreateModal = () => {
    editingUser.value = null;
    staffForm.reset();
    showModal.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user;
    staffForm.name = user.name;
    staffForm.email = user.email;
    staffForm.username = user.username;
    staffForm.phone = user.phone || '';
    staffForm.role = user.role;
    staffForm.password = '';
    staffForm.password_confirmation = '';
    showModal.value = true;
};

const openSecurityModal = (user) => {
    editingUser.value = user;
    
    // Initialize working hours with defaults if empty
    const hours = user.working_hours || {};
    days.forEach(day => {
        if (!hours[day]) {
            hours[day] = { start: '08:00', end: '17:00' };
        }
    });

    securityForm.working_hours = hours;
    securityForm.allowed_ips = user.allowed_ips || [];
    securityForm.is_device_lock_enabled = user.is_device_lock_enabled || false;
    securityForm.max_devices = user.security_config?.max_devices || 1;
    securityForm.permissions = user.permissions || [];
    showSecurityModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    showSecurityModal.value = false;
    staffForm.reset();
    securityForm.reset();
};

const addIp = () => {
    if (newIp.value && !securityForm.allowed_ips.includes(newIp.value)) {
        securityForm.allowed_ips.push(newIp.value);
        newIp.value = '';
    }
};

const removeIp = (ip) => {
    securityForm.allowed_ips = securityForm.allowed_ips.filter(i => i !== ip);
};

const submitStaff = () => {
    if (editingUser.value) {
        staffForm.put(route('settings.staff.update', editingUser.value.id), {
            onSuccess: () => {
                toast.success('Staff member updated successfully');
                closeModal();
            },
        });
    } else {
        staffForm.post(route('settings.staff.store'), {
            onSuccess: () => {
                toast.success('Staff member created successfully');
                closeModal();
            },
        });
    }
};

const submitSecurity = () => {
    securityForm.put(route('settings.staff.update-security', editingUser.value.id), {
        onSuccess: () => {
            toast.success('Security settings updated');
            closeModal();
        },
    });
};

const deleteStaff = (user) => {
    if (confirm(`Are you sure you want to remove ${user.name}?`)) {
        staffForm.delete(route('settings.staff.destroy', user.id), {
            onSuccess: () => toast.success('Staff member removed'),
        });
    }
};

const toggleStatus = (user) => {
    staffForm.post(route('settings.staff.toggle-status', user.id), {
        onSuccess: () => toast.success('Status updated'),
    });
};
</script>

<template>
    <Head title="Staff Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Staff Management & Security
                </h2>
                <div class="flex gap-4">
                    <PrimaryButton @click="openCreateModal" class="flex items-center gap-2">
                        <UserPlus class="h-4 w-4" />
                        Add Staff Member
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Total Staff</p>
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ users.length }}</h3>
                            </div>
                            <div class="h-12 w-12 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center text-blue-600">
                                <Users class="h-6 w-6" />
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Security Active</p>
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ users.filter(u => u.is_device_lock_enabled).length }}</h3>
                            </div>
                            <div class="h-12 w-12 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center text-green-600">
                                <Lock class="h-6 w-6" />
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Recent Activities</p>
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ activities.length }}</h3>
                            </div>
                            <div class="h-12 w-12 bg-purple-100 dark:bg-purple-900/30 rounded-full flex items-center justify-center text-purple-600">
                                <Activity class="h-6 w-6" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg border border-gray-100 dark:border-gray-700">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-6 py-3">Member</th>
                                        <th class="px-6 py-3">Role & Permissions</th>
                                        <th class="px-6 py-3">Security Controls</th>
                                        <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="user in users" :key="user.id" class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 font-bold">
                                                    {{ user.name.charAt(0) }}
                                                </div>
                                                <div>
                                                    <div class="font-medium">{{ user.name }}</div>
                                                    <div class="text-xs text-gray-500 italic">@{{ user.username }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-col gap-1">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900 dark:text-purple-200 w-fit">
                                                    <Shield class="h-3 w-3" />
                                                    {{ user.role }}
                                                </span>
                                                <span v-if="user.permissions.length" class="text-[10px] text-gray-500">
                                                    + {{ user.permissions.length }} Custom Permissions
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex gap-2">
                                                <div v-if="user.working_hours" title="Working Hours Set" class="text-blue-500"><Clock class="h-4 w-4" /></div>
                                                <div v-if="user.allowed_ips?.length" title="IP Whitelist Active" class="text-green-500"><Globe class="h-4 w-4" /></div>
                                                <div v-if="user.is_device_lock_enabled" title="Device Lock Active" class="text-orange-500"><Smartphone class="h-4 w-4" /></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span v-if="user.is_suspended" class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-200">
                                                Suspended
                                            </span>
                                            <span v-else class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Active
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button @click="openSecurityModal(user)" title="Security Settings" class="p-2 text-orange-600 hover:bg-orange-50 rounded-lg transition-colors">
                                                    <Lock class="h-4 w-4" />
                                                </button>
                                                <button @click="openEditModal(user)" title="Edit Staff" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                                    <Edit2 class="h-4 w-4" />
                                                </button>
                                                <button @click="toggleStatus(user)" :title="user.is_suspended ? 'Activate' : 'Suspend'" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                                                    <UserX v-if="!user.is_suspended" class="h-4 w-4" />
                                                    <UserCheck v-else class="h-4 w-4" />
                                                </button>
                                                <button @click="deleteStaff(user)" title="Delete Staff" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                                    <Trash2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!users.length">
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                            <Users class="h-12 w-12 mx-auto mb-4 opacity-20" />
                                            <p>No staff members found.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Feed -->
                <div class="mt-8 bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-lg font-medium flex items-center gap-2">
                            <Activity class="h-5 w-5 text-purple-500" />
                            Platform Audit Trail
                        </h3>
                        <PrimaryButton :href="route('settings.staff.activity')" class="scale-90">View Full Log</PrimaryButton>
                    </div>
                    <div class="p-0 max-h-[400px] overflow-y-auto">
                        <div v-for="activity in activities" :key="activity.id" class="p-4 border-b dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-start gap-3">
                                <div class="mt-1 h-8 w-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                    <Activity class="h-4 w-4 text-gray-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ activity.description }}
                                    </p>
                                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                                        <span>{{ new Date(activity.created_at).toLocaleString() }}</span>
                                        <span>•</span>
                                        <span>IP: {{ activity.ip_address }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Staff Member Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ editingUser ? 'Modify Staff Record' : 'Onboard New Staff Member' }}
                </h3>

                <form @submit.prevent="submitStaff" class="mt-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="name" value="Full Name" />
                            <TextInput id="name" v-model="staffForm.name" type="text" class="mt-1 block w-full" autocomplete="name" placeholder="John Doe" />
                            <InputError :message="staffForm.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="username" value="Username" />
                            <TextInput id="username" v-model="staffForm.username" type="text" class="mt-1 block w-full" autocomplete="username" placeholder="jdoe" />
                            <InputError :message="staffForm.errors.username" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="email" value="Official Email" />
                            <TextInput id="email" v-model="staffForm.email" type="email" class="mt-1 block w-full" autocomplete="email" placeholder="staff@isp.com" />
                            <InputError :message="staffForm.errors.email" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Phone Number" />
                            <TextInput id="phone" v-model="staffForm.phone" type="text" class="mt-1 block w-full" required />
                            <InputError :message="staffForm.errors.phone" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="role" value="Primary Role" />
                        <select id="role" v-model="staffForm.role" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-colors">
                            <option value="">Select Role</option>
                            <option v-for="role in roles" :key="role.id" :value="role.name">
                                {{ role.name.replace('_', ' ').toUpperCase() }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Base permissions will be inherited from the selected role.</p>
                        <InputError :message="staffForm.errors.role" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="password" :value="editingUser ? 'New Password (leave blank to keep)' : 'Access Password'" />
                            <TextInput id="password" v-model="staffForm.password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <InputError :message="staffForm.errors.password" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="password_confirmation" value="Verify Password" />
                            <TextInput id="password_confirmation" v-model="staffForm.password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <InputError :message="staffForm.errors.password_confirmation" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                        <SecondaryButton @click="closeModal">Discard</SecondaryButton>
                        <PrimaryButton :disabled="staffForm.processing" class="flex items-center gap-2">
                            <Save class="h-4 w-4" />
                            {{ editingUser ? 'Apply Changes' : 'Confirm Registration' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Security Modal -->
        <Modal :show="showSecurityModal" @close="closeModal" maxWidth="3xl">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="h-10 w-10 bg-orange-100 dark:bg-orange-900/30 text-orange-600 rounded-lg flex items-center justify-center">
                        <Lock class="h-6 w-6" />
                    </div>
                    <div>
                        <h3 class="text-xl font-bold dark:text-white">Security Control Center</h3>
                        <p class="text-sm text-gray-500">Hardening access for <span class="font-bold text-gray-700 dark:text-gray-300">{{ editingUser?.name }}</span></p>
                    </div>
                </div>

                <div class="flex gap-4 border-b border-gray-100 dark:border-gray-700 mb-6">
                    <button @click="securityTab = 'hours'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors', securityTab === 'hours' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700']">Working Hours</button>
                    <button @click="securityTab = 'network'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors', securityTab === 'network' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700']">Network & IP</button>
                    <button @click="securityTab = 'permissions'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors', securityTab === 'permissions' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700']">Permissions</button>
                    <button @click="securityTab = 'devices'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors', securityTab === 'devices' ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700']">Device Control</button>
                </div>

                <!-- Working Hours Tab -->
                <div v-show="securityTab === 'hours'" class="space-y-4">
                    <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-xl">
                        <h4 class="text-sm font-bold mb-4 flex items-center gap-2 dark:text-white">
                            <Clock class="h-4 w-4 text-blue-500" />
                            Shift Scheduling
                        </h4>
                        <div class="grid grid-cols-1 gap-3">
                            <div v-for="day in days" :key="day" class="flex items-center justify-between bg-white dark:bg-gray-800 p-3 rounded-lg border border-gray-100 dark:border-gray-700">
                                <span class="text-sm font-medium capitalize w-24 dark:text-gray-300">{{ day }}</span>
                                <div class="flex items-center gap-4">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] text-gray-400 uppercase">From</span>
                                        <input type="time" v-model="securityForm.working_hours[day].start" class="rounded border-gray-200 text-sm p-1 dark:bg-black dark:text-white" />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] text-gray-400 uppercase">To</span>
                                        <input type="time" v-model="securityForm.working_hours[day].end" class="rounded border-gray-200 text-sm p-1 dark:bg-black dark:text-white" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Network Tab -->
                <div v-show="securityTab === 'network'" class="space-y-6">
                    <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-xl border border-blue-100 dark:border-blue-900/50">
                        <h4 class="text-sm font-bold text-blue-800 dark:text-blue-300 flex items-center gap-2 mb-2">
                            <ShieldAlert class="h-4 w-4" />
                            IP Whitelisting
                        </h4>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mb-4">Restrict login to specific office IPs only. Leave empty to allow any IP.</p>
                        
                        <div class="flex gap-2">
                            <TextInput v-model="newIp" placeholder="e.g. 192.168.1.100" class="flex-1 text-sm" />
                            <SecondaryButton @click="addIp">Add IP</SecondaryButton>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <div v-for="ip in securityForm.allowed_ips" :key="ip" class="bg-white dark:bg-gray-800 px-3 py-1 rounded-full border border-gray-200 dark:border-gray-700 flex items-center gap-2 text-sm shadow-sm">
                                <span class="dark:text-gray-300">{{ ip }}</span>
                                <button @click="removeIp(ip)" class="text-red-500 hover:text-red-700"><Trash2 class="h-3 w-3" /></button>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-700">
                        <h4 class="text-sm font-bold mb-2 flex items-center gap-2 dark:text-white">
                            <Globe class="h-4 w-4 text-orange-500" />
                            Proxy & VPN Shield
                        </h4>
                        <p class="text-xs text-gray-500 mb-4">Enforce strict network origins. Proxies and VPNs are automatically blocked by the platform.</p>
                        <div class="flex items-center gap-4">
                            <span class="text-sm dark:text-gray-300">Global Enforcement Status:</span>
                            <span class="bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 px-2 py-0.5 rounded text-[10px] font-bold">ACTIVE</span>
                        </div>
                    </div>
                </div>

                <!-- Permissions Tab -->
                <div v-show="securityTab === 'permissions'" class="space-y-4">
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900">
                        <h4 class="text-sm font-bold mb-4 dark:text-white">Granular Access Controls</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <label v-for="perm in permissions" :key="perm.id" class="flex items-center gap-2 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <input type="checkbox" v-model="securityForm.permissions" :value="perm.name" class="rounded text-orange-500 focus:ring-orange-500 bg-transparent" />
                                <span class="text-xs capitalize dark:text-gray-300">{{ perm.name.replace('manage ', '') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Devices Tab -->
                <div v-show="securityTab === 'devices'" class="space-y-6">
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-xl">
                        <div>
                            <h4 class="text-sm font-bold dark:text-white">Hardware Identity Locking</h4>
                            <p class="text-xs text-gray-500">Enable advanced fingerprinting to lock accounts to authorized hardware.</p>
                        </div>
                        <div class="flex items-center">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" v-model="securityForm.is_device_lock_enabled" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-orange-300 dark:peer-focus:ring-orange-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-orange-500"></div>
                            </label>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-700">
                        <h4 class="text-sm font-bold mb-2 dark:text-white">Max Concurrent Devices</h4>
                        <TextInput v-model="securityForm.max_devices" type="number" min="1" class="w-24 text-sm" />
                        <p class="mt-2 text-xs text-gray-500">Limit the number of distinct devices this staff member can register.</p>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-700">
                    <SecondaryButton @click="closeModal">Discard</SecondaryButton>
                    <PrimaryButton :disabled="securityForm.processing" @click="submitSecurity" class="bg-orange-600 hover:bg-orange-700 focus:ring-orange-500 flex items-center gap-2">
                        <Save class="h-4 w-4" />
                        Save Security Policy
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
