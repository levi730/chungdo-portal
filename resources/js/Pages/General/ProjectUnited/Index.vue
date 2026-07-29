<template>
    <div class="container py-5">
        <div class="row mb-4 text-center">
            <h2>Join us as we unite and lend our support to Miss Tammy in her recovery.</h2>
        </div>

        <!-- Donation Form -->
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title text-success">Make a One-Time Donation</h2>
                    </div>
                    <div class="card-body">
                        <form @submit.prevent="submitDonation">
                            <div class="mb-3" v-if="!logged_in_user">
                                <label for="donationEmail" class="form-label">Your Email Address</label>
                                <input
                                    type="email"
                                    class="form-control"
                                    id="donationEmail"
                                    v-model="donationEmail"
                                    required
                                />
                            </div>
                            <div class="mb-3">
                                <label for="donationAmount" class="form-label">Donation Amount ($)</label>
                                <input
                                    type="number"
                                    min="1"
                                    step="0.01"
                                    class="form-control"
                                    id="donationAmount"
                                    v-model.number="donationAmount"
                                />
                            </div>
                            <button type="submit" class="btn btn-success">
                                Donate Now
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center my-4">
            <hr class="flex-grow-1 m-0">
            <span class="mx-3 text-muted fw-bold text-uppercase">or</span>
            <hr class="flex-grow-1 m-0">
        </div>

        <div class="row mt-5">
            <!-- Left column with T-shirt images -->
            <div class="col-md-6 mb-4">
                <div
                    v-for="(img, i) in images_hoodie"
                    :key="i"
                    class="card mb-3 overflow-hidden"
                    style="cursor: zoom-in;"
                >
                    <img
                        :src="img"
                        class="card-img-top zoomable-img"
                        :alt="`Project United Image ${i + 1}`"
                        @click="openFullImage(img)"
                    />
                </div>
            </div>


            <!-- Right column with form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h1 class="card-title text-primary">Order Hoodies</h1>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col">
                                <div class="text-primary display-1">$55</div>
                                <div class="fs-3">Adults</div>
                            </div>
                            <div class="col">
                                <div class="text-primary display-1">$42</div>
                                <div class="fs-3">Kids</div>
                            </div>
                        </div>

                        <form @submit.prevent="submitOrder" v-if="hoodies_available">
                            <h4 class="text-warning text-center mb-3">Hoodie orders must be placed by February 20, 2026, at 12:00pm (CDT)!</h4>

                            <div class="mb-3" v-if="!logged_in_user">
                                <label for="tshirtEmail" class="form-label">Your Email Address</label>
                                <input
                                    type="email"
                                    class="form-control"
                                    id="tshirtEmail"
                                    v-model="tshirtEmail"
                                    required
                                />
                            </div>

                            <div class="row g-3">
                                <div
                                    class="col-12 d-flex justify-content-between align-items-center"
                                    v-for="(label, size) in sizes"
                                    :key="size"
                                >
                                    <label :for="size" class="form-label mb-0">{{ label }}</label>
                                    <input
                                        type="number"
                                        min="0"
                                        class="form-control w-auto ms-2"
                                        v-model.number="quantities[size]"
                                    />
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="form-label fw-bold">Hoodies will be sent to the school of your choice for pickup:</label>


                                <div v-if="fulfillmentOption === 'mail'" class="mt-3">
                                    <label for="mailingAddress" class="form-label">Mailing Address</label>
                                    <textarea
                                        id="mailingAddress"
                                        class="form-control"
                                        v-model="mailingAddress"
                                        rows="3"
                                        placeholder="Enter your full mailing address"
                                    ></textarea>
                                </div>

                                <div v-if="fulfillmentOption === 'school'" class="mt-2">
                                    <label for="schoolSelect" class="form-label">Select School</label>
                                    <select id="schoolSelect" class="form-select" v-model="selectedSchool">
                                        <option :value="null" disabled>Select a school</option>
                                        <option v-for="(school, id) in all_schools" :value="school.id" :key="id">{{ school.name }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 fw-bold fs-5">
                                Total: ${{ finalPrice.toFixed(2) }}
                            </div>

                            <div v-if="!logged_in_user" class="mt-2">
                                <p class="text-info">If you are already a registered user of the Chung Do Association portal, <a href="/login">please login before you order!</a></p>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3">
                                Submit Order
                            </button>
                        </form>

                        <div v-else>
                            <p class="text-danger">Hoodie cut-off date has passed.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="d-flex align-items-center my-4">
            <hr class="flex-grow-1 m-0">
            <span class="mx-3 text-muted fw-bold text-uppercase">or</span>
            <hr class="flex-grow-1 m-0">
        </div>

        <TshirtOrder :tshirts-available="tshirts_available" :logged-in-user="logged_in_user" :all-schools="all_schools" />

    </div>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import TshirtOrder from './TshirtOrder.vue'

const props = defineProps({
    success: String,
    tshirts_available: Boolean,
    hoodies_available: Boolean,
    def_school: Number,
    logged_in_user: Boolean,
    all_schools: Array,
})

const sizes = {
    'adult_xs': 'Adult XS',
    'adult_s': 'Adult S',
    'adult_m': 'Adult M',
    'adult_l': 'Adult L',
    'adult_xl': 'Adult XL',
    'adult_2xl': 'Adult 2XL (+$2)',
    'adult_3xl': 'Adult 3XL (+$3)',
    'kids_s': 'Kids S',
    'kids_m': 'Kids M',
    'kids_l': 'Kids L',
}


const basePriceAdult = 55
const basePriceKids = 42
const upchargeMap = {
    'adult_2xl': 2,
    'adult_3xl': 3,
    'adult_4xl': 4,
}

const fulfillmentOption = ref('school')
const selectedSchool = ref(null)
const mailingAddress = ref('')
const tshirtEmail = ref('')
const donationEmail = ref('')

const quantities = reactive(
    Object.keys(sizes).reduce((acc, size) => {
        acc[size] = 0
        return acc
    }, {})
)

const totalQuantity = computed(() => {
    return Object.values(quantities).reduce((sum, qty) => sum + qty, 0)
})

const totalPrice = computed(() => {
    return Object.entries(quantities).reduce((total, [size, qty]) => {
        if (qty > 0) {
            const isAdult = size.startsWith('adult')
            const basePrice = isAdult ? basePriceAdult : basePriceKids
            const upcharge = upchargeMap[size] || 0
            total += (basePrice + upcharge) * qty
        }
        return total
    }, 0)
})

const finalPrice = computed(() => {
    const mailingFee = fulfillmentOption.value === 'mail' ? totalQuantity.value * 5 : 0
    return totalPrice.value + mailingFee
})

async function submitOrder() {
    const items = Object.entries(quantities)
        .filter(([_, qty]) => qty > 0)
        .map(([size, qty]) => ({ size, quantity: qty }))

    if (!items.length) {
        alert('Please select at least one shirt.')
        return
    }

    if (!fulfillmentOption.value) {
        alert('Please select a fulfillment option.')
        return
    }

    if (fulfillmentOption.value === 'school' && !selectedSchool.value) {
        alert('Please select a school for drop-off.')
        return
    }

    if (fulfillmentOption.value === 'mail' && mailingAddress.value.trim() === '') {
        alert('Please enter your mailing address.')
        return
    }

    if(!props.logged_in_user) {
        if (!tshirtEmail.value.trim()) {
            alert('Please enter your email address.')
            return
        }
    }

    try {
        const response = await axios.post('/project-united/hoodie', {
            items,
            fulfillment: fulfillmentOption.value,
            school_id: selectedSchool.value,
            mailing_address: mailingAddress.value,
            email: tshirtEmail.value,
        })

        window.location.href = response.data.url
    } catch (error) {
        alert('There was an error creating your checkout session.')
        console.error(error)
    }
}

const donationAmount = ref(0)

async function submitDonation() {
    if(!props.logged_in_user) {
        if (!donationEmail.value.trim()) {
            alert('Please enter your email address.')
            return
        }
    }

    if (donationAmount.value > 0) {
        try {
            const response = await axios.post('/project-united/donate', {
                amount: donationAmount.value,
                email: donationEmail.value,
            })
            window.location.href = response.data.url
        } catch (e) {
            alert('Something went wrong. Please try again.')
        }
    } else {
        alert('Please enter a valid donation amount.')
    }
}

const images_hoodie = [
    '/img/2026/project_united/DeepHeatherLongSleeveNoFrog_md.jpg',
]


function openFullImage(mdPath) {
    const fullSizePath = mdPath.replace('_md', '')
    window.open(fullSizePath, '_blank')
}
</script>

<style scoped>
.zoomable-img {
    transition: transform 0.3s ease;
}

.zoomable-img:hover {
    transform: scale(1.5);
}
</style>
