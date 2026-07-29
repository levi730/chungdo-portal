
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

        <h2>Already Succeeded for {{ event.name }}</h2>

        <p>Registration for:</p>
        <ul>
            <li v-for="reg in regs" :key="reg.id">
                {{ reg.user.full_name }} - {{ reg.user.rank.rank }}
            </li>
        </ul>

        <p>Payment started: <b>{{ payment_made }}</b></p>
        <p>Payment amount: <b>${{ intent.amount / 100 }}</b></p>

        <div class="text-success">
            Payment completed!
        </div>

    </div>

</template>


<style>


</style>
