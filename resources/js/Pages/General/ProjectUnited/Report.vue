<template>
    <div class="container py-5">
        <h2>Project United Report</h2>

        <h3 class="text-primary">Round: {{ round }}</h3>

        <h3 class="mt-3">As of: {{ now }}</h3>

        <h3 class="mt-4 text-primary">
            Donation Total: <b>${{ donation_total }}</b>
            <br>
            T-Shirt Total: <b>${{ tshirt_total }}</b>
            <br>
            Hoodie Total: <b>${{ hoodie_total }}</b>
            <br>
            Grand Total: <b>${{ grand_total }}</b>
        </h3>

        <div class="row mt-4">
            <div class="col-6">
                <h3 class="text-primary"> T-Shirt Breakdown</h3>

                <div class="overflow-auto inline-block">
                    <table class="table table-vcenter auto-width">
                        <thead>
                        <tr>
                            <th>Size</th>
                            <th>Count</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(count, size) in sizes" :key="size">
                            <td>{{ size_labels[size] }}</td>
                            <td class="text-secondary text-end">{{ count }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-6">
                <h3 class="text-primary"> Hoodie Breakdown</h3>

                <div class="overflow-auto inline-block">
                    <table class="table table-vcenter auto-width">
                        <thead>
                        <tr>
                            <th>Size</th>
                            <th>Count</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(count, size) in sizesHoodieFiltered" :key="size">
                            <td>{{ size_labels[size] }}</td>
                            <td class="text-secondary text-end">{{ count }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="mt-4">
            <h3 class="text-primary"> Transactions</h3>
            <div class="table-responsive">
                <table class="table table-vcenter table-bordered">
                    <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Total Shirts</th>
                        <th>Shirt Details</th>
                        <th>Shirt Dropoff</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(t, index) in trans" :key="t.id">
                        <td v-if="t.user">
                            {{ t.user.firstname }} {{ t.user.lastname }}
                        </td>
                        <td v-else>
                            N/A
                        </td>
                        <td>{{ t.email }}</td>
                        <td>
                            <template v-if="t.trans_type == 'project_united_donation'">
                                Donation
                            </template>
                            <template v-else-if="t.trans_type == 'project_united_2026_tshirt'">
                                T-Shirt (Jan 2026)
                            </template>
                            <template v-else-if="t.trans_type == 'project_united_2026_hoodie'">
                                Hoodie (Jan 2026)
                            </template>
                            <template v-else>
                                Unknown
                            </template>
                        </td>
                        <td class="text-end">${{ t.amount }}</td>
                        <td>
                            <template v-if="t.metadata.raw_items">
                                {{ t.metadata.raw_items.reduce((sum, item) => sum + item.quantity, 0) }}
                            </template>
                        </td>
                        <td>
                            <template v-if="t.metadata.raw_items">
                                <div v-for="(item, index) in t.metadata.raw_items" :key="index">
                                    {{size_labels[item.size] }}: {{item.quantity}}
                                </div>
                            </template>
                        </td>
                        <td>{{ all_schools[t.metadata.school_id] }}</td>

                    </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    now: Date,
    donation_total: Number,
    hoodie_total: Number,
    tshirt_total: Number,
    sizes: Object,
    sizes_hoodie: Object,
    total: Number,
    trans: Array,
    size_labels: Object,
    all_schools: Object,
    grand_total: Number,
    round: Number

})

const hoodieExcludedSizes = new Set(['adult_4xl', 'adult_5xl', 'kids_xl'])
const sizesHoodieFiltered = computed(() => {
    // keep original order as provided by the object
    return Object.fromEntries(
        Object.entries(props.sizes_hoodie).filter(([size]) => !hoodieExcludedSizes.has(size))
    )
})

function formatText(str) {
    if(str == 'null') {
        return '';
    }
    let text = str.replace(/\\n/g, '<br>');
    return text.replace(/\"/g, "");
}

</script>

<style scoped>
    .auto-width {
        width: auto;
    }
</style>
