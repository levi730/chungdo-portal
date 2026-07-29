
<script setup>
import { onMounted, onUnmounted, ref, getCurrentInstance } from 'vue'


const app = getCurrentInstance();

const loading = ref(false);

const props = defineProps({
    event: Object,
    intent: Object,
    regs: Array,
    payment_made: String
})

onMounted(() => {

})


</script>

<template>
    <div class="container">

        <h2>Other Issue for {{ event.name }}</h2>

        <p>Registration for:</p>
        <ul>
            <li v-for="reg in regs" :key="reg.id">
                {{ reg.user.full_name }} - {{ reg.user.rank.rank }}
            </li>
        </ul>

        <p>Payment started: <b>{{ payment_made }}</b></p>
        <p>Payment amount: <b>${{ intent.amount / 100 }}</b></p>

        <div class="text-warning">
            <p>Another issue has been found for your payment.  Payment status code: <b>{{ intent.status }}</b></p>
            <p>Please contact <a href="mailto:support@chungdo.org">support@chungdo.org</a> with the above status code for assistance.</p>
        </div>
    </div>

</template>


<style>


</style>
