<template>
    <div class="row mt-5">
        <!-- Left column with T-shirt images -->
        <div class="col-md-6 mb-4">
            <div
                v-for="(img, i) in images_tshirt"
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
                    <h1 class="card-title text-primary">Order T-Shirts</h1>
                </div>
                <div class="card-body">
                    <div class="text-primary display-1 text-center">$25</div>
                    <form @submit.prevent="submitOrder" v-if="tshirtsAvailable">
                        <h4 class="text-warning text-center mb-3">T-shirt orders must be placed by February 16 at 12:00pm (CDT)!</h4>

                        <div class="mb-3" v-if="!loggedInUser">
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


                            <div class="mt-2">
                                <label for="schoolSelect" class="form-label">Select School</label>
                                <select id="schoolSelect" class="form-select" v-model="selectedSchool">
                                    <option :value="null" disabled>Select a school</option>
                                    <option v-for="(school, id) in allSchools" :value="school.id" :key="id">{{ school.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 fw-bold fs-5">
                            Total: ${{ finalPrice.toFixed(2) }}
                        </div>

                        <div v-if="!loggedInUser" class="mt-2">
                            <p class="text-info">If you are already a registered user of the Chung Do Association portal, <a href="/login">please login before you order!</a></p>
                        </div>

                        <button type="submit" class="btn btn-primary mt-3">
                            Submit Order
                        </button>
                    </form>

                    <div v-else>
                        <p class="text-danger">T-shirt cut-off date has passed.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'


const props = defineProps({
    loggedInUser: Boolean,
    tshirtsAvailable: Boolean,
    allSchools: Array,
});

const images_tshirt = [
    '/img/2026/project_united/DeepHeatherTshirt.jpg',
]


const sizes = {
    'adult_xs': 'Adult XS',
    'adult_s': 'Adult S',
    'adult_m': 'Adult M',
    'adult_l': 'Adult L',
    'adult_xl': 'Adult XL',
    'adult_2xl': 'Adult 2XL (+$2)',
    'adult_3xl': 'Adult 3XL (+$3)',
    'adult_4xl': 'Adult 4XL (+$4)',
    'adult_5xl': 'Adult 5XL (+$4)',
    'kids_s': 'Kids S',
    'kids_m': 'Kids M',
    'kids_l': 'Kids L',
    'kids_xl': 'Kids XL',
}


const basePrice = 25
const upchargeMap = {
    'adult_2xl': 2,
    'adult_3xl': 3,
    'adult_4xl': 4,
    'adult_5xl': 5,
}


const totalQuantity = computed(() => {
    return Object.values(quantities).reduce((sum, qty) => sum + qty, 0)
})

const finalPrice = computed(() => {
    return totalPrice.value;
})

const totalPrice = computed(() => {
    return Object.entries(quantities).reduce((total, [size, qty]) => {
        if (qty > 0) {
            const upcharge = upchargeMap[size] || 0
            total += (basePrice + upcharge) * qty
        }
        return total
    }, 0)
})

async function submitOrder() {
    const items = Object.entries(quantities)
        .filter(([_, qty]) => qty > 0)
        .map(([size, qty]) => ({ size, quantity: qty }))

    if (!items.length) {
        alert('Please select at least one shirt.')
        return
    }

    if (!selectedSchool.value) {
        alert('Please select a school for drop-off.')
        return
    }

    if(!props.loggedInUser) {
        if (!tshirtEmail.value.trim()) {
            alert('Please enter your email address.')
            return
        }
    }

    try {
        const response = await axios.post('/project-united/tshirt', {
            items,
            school_id: selectedSchool.value,
            email: tshirtEmail.value,
        })

        window.location.href = response.data.url
    } catch (error) {
        alert('There was an error creating your checkout session.')
        console.error(error)
    }
}


const selectedSchool = ref(null)
const tshirtEmail = ref('')

const quantities = reactive(
    Object.keys(sizes).reduce((acc, size) => {
        acc[size] = 0
        return acc
    }, {})
)

function openFullImage(mdPath) {
    const fullSizePath = mdPath.replace('_md', '')
    window.open(fullSizePath, '_blank')
}

</script>
