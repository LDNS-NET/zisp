<!-- This file (Recentlyexpired.vue)displays recently expired subscriptions in the dashboard view with the following filter:
 Package filtering, Date(Day,Week,Month,Year) also add a tab called expired over 1 month ago(or just in the filters.)
 Use all components, styling,routes etc from dashboard.vue all in revenue intelligence  
 Revenue Intelligence (only tenant_admin) -->

<script>
export default {
    data() {
        return {
            selectedPackage: '',
            selectedDateFilter: 'week',
            packages: [
                { id: 1, name: 'Basic Package' },
                { id: 2, name: 'Premium Package' },
                { id: 3, name: 'Enterprise Package' },
            ],
            subscriptions: [
                {
                    id: 1,
                    user: { name: 'John Doe' },
                    package: { name: 'Basic Package' },
                    start_date: '2023-01-01',
                    end_date: '2023-01-31',
                },
                {
                    id: 2,
                    user: { name: 'Jane Smith' },
                    package: { name: 'Premium Package' },
                    start_date: '2023-02-01',
                    end_date: '2023-02-28',
                },
            ],
        };
    },
    computed() {
        return {
            filteredSubscriptions() {
                let filtered = this.subscriptions;

                if (this.selectedPackage) {
                    filtered = filtered.filter(
                        (sub) =>
                            sub.package.id === parseInt(this.selectedPackage),
                    );
                }

                const now = new Date();
                const oneMonthAgo = new Date(now);
                oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);

                if (this.selectedDateFilter === 'overMonth') {
                    filtered = filtered.filter(
                        (sub) => new Date(sub.end_date) < oneMonthAgo,
                    );
                } else if (this.selectedDateFilter === 'day') {
                    const today = new Date().setHours(0, 0, 0, 0);
                    filtered = filtered.filter(
                        (sub) =>
                            new Date(sub.end_date).setHours(0, 0, 0, 0) ===
                            today,
                    );
                } else if (this.selectedDateFilter === 'week') {
                    const oneWeekAgo = new Date(now);
                    oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
                    filtered = filtered.filter(
                        (sub) => new Date(sub.end_date) >= oneWeekAgo,
                    );
                } else if (this.selectedDateFilter === 'month') {
                    const oneMonthAgo = new Date(now);
                    oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
                    filtered = filtered.filter(
                        (sub) => new Date(sub.end_date) >= oneMonthAgo,
                    );
                } else if (this.selectedDateFilter === 'year') {
                    const oneYearAgo = new Date(now);
                    oneYearAgo.setFullYear(oneYearAgo.getFullYear() - 1);
                    filtered = filtered.filter(
                        (sub) => new Date(sub.end_date) >= oneYearAgo,
                    );
                }

                return filtered;
            },
        };
    },
};
</script>

<template>
    <div class="p-4">
        <h2 class="mb-4 text-2xl font-bold">Recently Expired Subscriptions</h2>
        <div class="mb-4">
            <label for="package" class="block text-sm font-medium text-gray-700"
                >Filter by Package</label
            >
            <select
                id="package"
                v-model="selectedPackage"
                class="mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm"
            >
                <option value="">All Packages</option>
                <option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">
                    {{ pkg.name }}
                </option>
            </select>
        </div>
        <div class="mb-4">
            <label
                for="dateFilter"
                class="block text-sm font-medium text-gray-700"
                >Filter by Date</label
            >
            <select
                id="dateFilter"
                v-model="selectedDateFilter"
                class="mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm"
            >
                <option value="day">Last Day</option>
                <option value="week">Last Week</option>
                <option value="month">Last Month</option>
                <option value="year">Last Year</option>
                <option value="overMonth">Over 1 Month Ago</option>
            </select>
        </div>
        <table class="min-w-full bg-white">
            <thead>
                <tr>
                    <th class="py-2">User</th>
                    <th class="py-2">Package</th>
                    <th class="py-2">Start Date</th>
                    <th class="py-2">End Date</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="subscription in filteredSubscriptions"
                    :key="subscription.id"
                >
                    <td class="py-2">{{ subscription.user.name }}</td>
                    <td class="py-2">{{ subscription.package.name }}</td>
                    <td class="py-2">
                        {{
                            new Date(
                                subscription.start_date,
                            ).toLocaleDateString()
                        }}
                    </td>
                    <td class="py-2">
                        {{
                            new Date(subscription.end_date).toLocaleDateString()
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
