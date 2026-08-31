<template>
    <div class="container">

        <div v-if="successMsg" class="alert alert-success" role="alert">
            <h4 class="alert-title">Success!</h4>
            <div class="text-secondary">{{ successMsg }}</div>
        </div>

        <div v-if="errMsg" class="alert alert-danger" role="alert">
            <h4 class="alert-title">Error</h4>
            <div class="text-secondary">{{ errMsg }}</div>
        </div>


        <div class="row">
            <div class="col-12  col-md-6">
                <h2>Payment completion for {{ event.name }}</h2>

                <h4 class="text-warning" v-if="!successMsg">Due to a processing error, your payment still needs to be completed.</h4>

                <p>Registration for:</p>
                <ul>
                    <li v-for="reg in regs" :key="reg.id">
                        {{ reg.user.full_name }} - {{ reg.user.rank.rank }}
                    </li>
                </ul>

                <p>Payment started: <b>{{ payment_made }}</b></p>
                <p>Payment amount: <b>${{ intent.amount / 100 }}</b></p>

                <div v-if="successMsg" class="text-success">
                    Payment completed!
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div v-if="!successMsg">
                    <form @submit.prevent="handleSubmit">
                        <h3>Payment Details</h3>
                        <div class="row">
                            <div class="col">
                                <div id="card-number-element" class="stripe-element"></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <div id="card-expiry-element" class="stripe-element"></div>
                            </div>
                            <div class="col">
                                <div id="card-cvc-element" class="stripe-element"></div>
                            </div>
                        </div>

                        <button type="submit" :disabled="loading" class="btn btn-primary">
                            Pay ${{ intent.amount / 100 }}
                            <span v-if="loading" class="spinner-border spinner-border-sm mx-2" role="status"></span>
                        </button>

                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { loadStripe } from '@stripe/stripe-js';

const successMsg = ref(null);
const errMsg = ref(null);

const stripe = ref(null);
const elements = ref(null);
const cardNumber = ref(null);
const cardExpiry = ref(null);
const cardCvc = ref(null);
const loading = ref(false);
const props = defineProps({
    event: Object,
    intent: Object,
    regs: Array,
    stripe_key: String,
    payment_made: String
})

onMounted(async () => {
    // The publishable key comes from the server, because it must match the
    // Stripe account this event's PaymentIntent was created on. The build-time
    // VITE_STRIPE_KEY is the association's and would be wrong for an event
    // routed elsewhere. Fall back to it only if the prop is missing.
    stripe.value = await loadStripe(props.stripe_key || import.meta.env.VITE_STRIPE_KEY);
    elements.value = stripe.value.elements();

    // Create separate card fields
    cardNumber.value = elements.value.create('cardNumber');
    cardNumber.value.mount('#card-number-element');

    cardExpiry.value = elements.value.create('cardExpiry');
    cardExpiry.value.mount('#card-expiry-element');

    cardCvc.value = elements.value.create('cardCvc');
    cardCvc.value.mount('#card-cvc-element');
});

onUnmounted(() => {
    // Cleanup Stripe elements on component unmount
    if (cardNumber.value) cardNumber.value.unmount();
    if (cardExpiry.value) cardExpiry.value.unmount();
    if (cardCvc.value) cardCvc.value.unmount();
});

const handleSubmit = async () => {
    successMsg.value = null;
    errMsg.value = null;

    loading.value = true;
    try {

        // Confirm the payment
        console.log(props.intent.client_secret);
        const result = await stripe.value.confirmCardPayment(props.intent.client_secret, {
            payment_method: {
                card: cardNumber.value,
            },
        });

        if (result.error) {
            //alert(result.error.message);
            errMsg.value = result.error.message;
        } else if (result.paymentIntent.status === 'succeeded') {
            //alert('Payment successful!');
            successMsg.value = 'Payment Successful!';
        }
    } catch (error) {
        //console.error(error);
        errMsg.value = error.message;
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.stripe-element {
    border: 1px solid #ccc;
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 5px;
}
</style>
