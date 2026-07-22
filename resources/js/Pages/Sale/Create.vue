<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

import AddProductModal from '@/Components/AddProductModal.vue';

const props = defineProps({
  customers: {
    type: Array,
    required: false,
    default: () => [],
  },
  products: {
    type: Array,
    required: true,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  unitTypes: {
    type: Object,
    default: () => ({}),
  },
  gstRates: {
    type: Array,
    required: true,
  },
  referralUsers: {
    type: Array,
    default: () => [],
  },
});

const customers = ref([...props.customers]);
const referralUsers = ref([...props.referralUsers]);
const products = ref([]);

// Registry to store all products we have seen/loaded so far
const productRegistry = ref({});

// Initialize registry with initial products (from props)
if (props.products) {
  props.products.forEach(p => {
    productRegistry.value[p.id] = p;
  });
}

const productSearchQuery = ref('');
const activeSearchIndex = ref(null);
const searchQuery = ref('');
const selectedSidebarProductIndex = ref(0);

const onProductSearch = async (search, loading) => {
  const searchVal = search || '';
  productSearchQuery.value = searchVal;
  if (loading) loading(true);
  try {
    const response = await axios.get(`/product/search?query=${encodeURIComponent(searchVal)}`);
    response.data.forEach(p => {
      productRegistry.value[p.id] = p;
    });

    let results = response.data;
    form.value.sale_items.forEach(item => {
      if (item.product_id && productRegistry.value[item.product_id]) {
        const alreadyInResults = results.some(p => p.id === item.product_id);
        if (!alreadyInResults) {
          results.push(productRegistry.value[item.product_id]);
        }
      }
    });
    products.value = results;
  } catch (error) {
    console.error("Error fetching products:", error);
  } finally {
    if (loading) loading(false);
  }
};

const onProductInputFocus = (index) => {
    activeSearchIndex.value = index;
    const item = form.value.sale_items[index];
    searchQuery.value = item.temp_product_name || '';
    selectedSidebarProductIndex.value = 0;
    onProductSearch(searchQuery.value);
};

const onProductInputChanged = (index, value) => {
    searchQuery.value = value;
    const item = form.value.sale_items[index];
    item.temp_product_name = value;
    selectedSidebarProductIndex.value = 0;
    if (!value) {
        item.product_id = "";
    }
    onProductSearch(value);
};

const navigateSidebar = (direction) => {
    if (products.value.length === 0) return;
    selectedSidebarProductIndex.value = (selectedSidebarProductIndex.value + direction + products.value.length) % products.value.length;
    const el = document.getElementById(`sidebar-item-${selectedSidebarProductIndex.value}`);
    if (el) {
        el.scrollIntoView({ block: 'nearest' });
    }
};

const selectHighlightedProduct = () => {
    if (products.value.length > 0 && selectedSidebarProductIndex.value >= 0 && selectedSidebarProductIndex.value < products.value.length) {
        selectProduct(products.value[selectedSidebarProductIndex.value]);
    }
};

const selectProduct = (product) => {
    if (activeSearchIndex.value !== null) {
        const item = form.value.sale_items[activeSearchIndex.value];
        item.product_id = product.id;
        item.temp_product_name = product.name;
        
        if (page.props.auth?.user?.allow_provide_additional_descriptions) {
            openDescriptionPopup(activeSearchIndex.value);
        }
        
        activeSearchIndex.value = null;
    }
};

const closeSidebar = () => {
    if (activeSearchIndex.value !== null) {
        const item = form.value.sale_items[activeSearchIndex.value];
        if (item.product_id) {
            const selectedProduct = productRegistry.value[item.product_id] || products.value.find(p => p.id === item.product_id);
            if (selectedProduct) {
                item.temp_product_name = selectedProduct.name;
            }
        } else {
            item.temp_product_name = "";
        }
    }
    activeSearchIndex.value = null;
};

const customerSearchQuery = ref("");
const onCustomerSearch = async (search, loading) => {
  customerSearchQuery.value = search;
  if (!search.trim()) {
    const selectedId = form.value?.customer_id;
    if (selectedId) {
      const selected = selectedCustomer.value || customers.value.find(c => c.id == selectedId);
      if (selected) {
        customers.value = [selected];
        return;
      }
    }
    customers.value = [...props.customers];
    return;
  }
  try {
    const response = await axios.get(`/customer/search?query=${encodeURIComponent(search)}`);
    customers.value = response.data;

    // Ensure selected customer is always in options list
    const selected = selectedCustomer.value || props.customers.find(c => c.id == form.value.customer_id) || customers.value.find(c => c.id == form.value.customer_id);
    if (selected && !customers.value.some(c => c.id == selected.id)) {
      customers.value.unshift(selected);
    }
  } catch (error) {
    console.error("Error fetching customers:", error);
  }
};

const referralSearchQuery = ref("");
const onReferralSearch = async (search, loading) => {
  referralSearchQuery.value = search;
  if (!search.trim()) {
    const selectedId = form.value?.referral_user_id;
    if (selectedId) {
      const selected = referralUsers.value.find(r => r.id == selectedId);
      if (selected) {
        referralUsers.value = [selected];
        return;
      }
    }
    referralUsers.value = [...props.referralUsers];
    return;
  }
  try {
    const response = await axios.get(`/referral-user/search?query=${encodeURIComponent(search)}`);
    referralUsers.value = response.data;

    // Ensure selected referral user is always in options list
    const selectedId = form.value?.referral_user_id;
    if (selectedId) {
      const selected = props.referralUsers.find(r => r.id == selectedId) || referralUsers.value.find(r => r.id == selectedId);
      if (selected && !referralUsers.value.some(r => r.id == selected.id)) {
        referralUsers.value.unshift(selected);
      }
    }
  } catch (error) {
    console.error("Error fetching referral users:", error);
  }
};

const form = ref({
    customer_id: "",
    referral_user_id: "",
    sale_date: new Date().toLocaleDateString('en-CA'),
    estimate_id: "",
    grand_total: "",
    GstAmount: "",
    accepted: false,
    total_amount: "",
    paid: 0,
    discount: 0,
    payment_method: '',
    payment_status: "",
    currency: 'INR',
    exchange_rate: 1.0000,
    sale_items: [{
            product_id: "",
            unit_type: "",
            sgst: "",
            cgst: "",
            quantity: "",
            price: "",
            baseAmount: "",
            gst_rate_id: "",
            last_product_id: "",
            width: "",
            height: "",
            alternate_quantity: "",
            alternate_unit_type: "",
            last_width: "",
            last_height: "",
            last_alternate_quantity: "",
            last_quantity: ""
    }],
});

const prefilledFromEstimateNo = ref('');

const clearEstimatePrefill = () => {
    form.value.estimate_id = '';
    prefilledFromEstimateNo.value = '';
};

onMounted(async () => {
    form.value.accepted = !!page.props.auth?.user?.allow_gst_invoice;
    const urlParams = new URLSearchParams(window.location.search);
    const estimateId = urlParams.get('estimate_id');
    if (estimateId) {
        try {
            const response = await axios.get(`/estimate/${estimateId}/get-json`);
            const estimate = response.data;
            if (estimate) {
                if (estimate.status === 'Invoiced') {
                    toast.error("This estimate has already been converted to a sale!");
                    return;
                }

                // Step 1: Load customer into customers array so vSelect shows selection
                if (estimate.customer) {
                    const alreadyExists = customers.value.some(c => c.id == estimate.customer.id);
                    if (!alreadyExists) {
                        customers.value.unshift(estimate.customer);
                    }
                    selectedCustomer.value = estimate.customer;
                }

                form.value.customer_id = estimate.customer_id;
                form.value.estimate_id = estimate.id;
                form.value.discount = parseFloat(estimate.discount) || 0;
                form.value.accepted = estimate.accepted == 1;

                // Step 2: Load products from estimate.items.product relation into registry and products array
                estimate.items.forEach(item => {
                    if (item.product) {
                        productRegistry.value[item.product.id] = item.product;
                        const alreadyInProducts = products.value.some(p => p.id === item.product.id);
                        if (!alreadyInProducts) {
                            products.value.push(item.product);
                        }
                    }
                });

                // Step 3: Determine interstate status based on loaded customer
                const customerState = estimate.customer?.state?.trim().toLowerCase() || '';
                const storeStateVal = storeState.value.trim().toLowerCase();
                const isInterstateNow = customerState && storeStateVal !== customerState;

                // Step 4: Map items with correct GST rate using interstate knowledge
                form.value.sale_items = estimate.items.map(item => {
                    const sgst = parseFloat(item.sgst) || 0;
                    const cgst = parseFloat(item.cgst) || 0;
                    const totalProductRate = sgst + cgst;

                    // Filter rates based on interstate status
                    const applicableRates = isInterstateNow
                        ? props.gstRates.filter(r => r.name.toLowerCase().includes('igst'))
                        : props.gstRates.filter(r => !r.name.toLowerCase().includes('igst'));

                    const matchedRate = applicableRates.find(r => parseFloat(r.rate) === totalProductRate)
                        || props.gstRates.find(r => parseFloat(r.rate) === totalProductRate);

                    let computedCgst = cgst;
                    let computedSgst = sgst;
                    if (matchedRate && parseFloat(matchedRate.igst) > 0) {
                        computedCgst = parseFloat(matchedRate.igst) / 2;
                        computedSgst = parseFloat(matchedRate.igst) / 2;
                    } else if (matchedRate) {
                        computedCgst = parseFloat(matchedRate.cgst) || cgst;
                        computedSgst = parseFloat(matchedRate.sgst) || sgst;
                    }

                    return {
                        product_id: item.product_id,
                        temp_product_name: item.product ? item.product.name : "",
                        unit_type: item.unit_type || "",
                        sgst: computedSgst,
                        cgst: computedCgst,
                        quantity: item.quantity,
                        price: item.price,
                        baseAmount: item.base_price,
                        gst_rate_id: matchedRate ? matchedRate.id : (props.gstRates[0]?.id || ""),
                        last_product_id: item.product_id,
                        description: item.description || "",
                    };
                });

                prefilledFromEstimateNo.value = estimate.estimate_no;
                toast.success(`Loaded Estimate ${estimate.estimate_no}`);
            }
        } catch (error) {
            console.error("Error fetching estimate details:", error);
            toast.error("Failed to load estimate details.");
        }
    }
});

const selectedCustomer = ref(null);
const showPaymentModal = ref(false);
const paymentDiscountInput = ref(null);
const customerData = ref(null);

const showCustomerModal = ref(false);
const newCustomerNameInput = ref(null);
const lastActiveElement = ref(null);
const newCustomer = ref({
  name: '',
  phone: '',
  email: '',
  gst_number: '',
  address: '',
  city: '',
  district: '',
  state: '',
  country: '',
  pin_code: ''
});

const availableCustomerDistricts = computed(() => {
    if (!newCustomer.value.state) return [];
    const stateName = newCustomer.value.state;
    const statesData = usePage().props.state_cities || {};
    const lookupKey = Object.keys(statesData).find(
        key => key.toLowerCase().replace(/[^a-z0-9]/g, '') === stateName.toLowerCase().replace(/[^a-z0-9]/g, '')
    );
    return lookupKey ? statesData[lookupKey] : [];
});

watch(() => newCustomer.value.country, (newVal) => {
    if (newVal && newVal !== 'India') {
        newCustomer.value.state = "";
        newCustomer.value.district = "";
    }
});

watch(() => newCustomer.value.state, (newVal, oldVal) => {
    if (oldVal !== undefined) {
        newCustomer.value.district = "";
        newCustomer.value.city = "";
    }
    if (newVal && (!newCustomer.value.country || newCustomer.value.country === 'India')) {
        newCustomer.value.country = "India";
    }
});

// Move focus to the next logical input/select/button
const moveToNextInput = (event) => {
  const container = document.querySelector('.bg-white.p-8');
  if (!container) return;

  const elements = Array.from(container.querySelectorAll(
    'input:not([disabled]), select:not([disabled]), button:not([disabled]), .vs__search'
  )).filter(el => {
    const rect = el.getBoundingClientRect();
    const isVisible = rect.width > 0 && rect.height > 0;
    const isTrashBtn = el.querySelector('.bi-trash') || el.classList.contains('bg-red-600') || el.closest('button')?.classList.contains('bg-red-600');
    const isAddRowBtn = el.closest('button')?.classList.contains('bg-green-600') || el.classList.contains('bg-green-600');
    return isVisible && !isTrashBtn && !isAddRowBtn;
  });

  const currentIndex = elements.indexOf(event.target);
  if (currentIndex !== -1 && currentIndex < elements.length - 1) {
    event.preventDefault();
    elements[currentIndex + 1].focus();
  }
};

const onEnterKey = (event) => {
  // If there's an active search query and no options match, open modal directly
  if (customerSearchQuery.value && customers.value.length === 0) {
    event.preventDefault();
    openCustomerModalWithName(customerSearchQuery.value);
    return;
  }
  moveToNextInput(event);
};

const onProductEnterKey = (event, index) => {
  const searchVal = productSearchQuery.value || '';
  const matched = products.value.filter(p => p.name.toLowerCase().includes(searchVal.toLowerCase()));
  if (searchVal && matched.length === 0) {
    event.preventDefault();
    openProductModal(index, searchVal);
    return;
  }
  moveToNextInput(event);
};

// Global escape key handler to close modals
const handleGlobalKeydown = (e) => {
  if (e.key === 'Escape') {
    if (showCustomerModal.value) {
      showCustomerModal.value = false;
      e.preventDefault();
    } else if (showPaymentModal.value) {
      showPaymentModal.value = false;
      e.preventDefault();
    }
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown);

  // Auto-focus customer search input on page load
  nextTick(() => {
    const customerSearch = document.querySelector('.vs__search');
    if (customerSearch) {
      customerSearch.focus();
    }
  });
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
});

// Watch showCustomerModal to manage focus
watch(showCustomerModal, async (isOpen) => {
  if (isOpen) {
    lastActiveElement.value = document.activeElement;
    await nextTick();
    if (newCustomerNameInput.value) {
      newCustomerNameInput.value.focus();
    }
  } else {
    if (lastActiveElement.value) {
      await nextTick();
      lastActiveElement.value.focus();
      lastActiveElement.value = null;
    }
  }
});

const openCustomerModalWithName = (name) => {
  newCustomer.value = {
    name: name || '',
    phone: '',
    email: '',
    gst_number: '',
    address: '',
    city: '',
    district: '',
    state: '',
    country: '',
    pin_code: ''
  };
  showCustomerModal.value = true;
};

const submitCustomer = async () => {
  try {
    if (!newCustomer.value.name) {
      toast.error("Customer name is required!");
      return;
    }
    // if (!newCustomer.value.phone) {
    //   toast.error("Customer phone is required!");
    //   return;
    // }
    // if (!newCustomer.value.email) {
    //   toast.error("Customer email is required!");
    //   return;
    // }

    const response = await axios.post('/customer/store', newCustomer.value);
    const createdCustomer = response.data;
    customers.value.push(createdCustomer);

    form.value.customer_id = createdCustomer.id;
    selectedCustomer.value = createdCustomer;
    showCustomerModal.value = false;

    newCustomer.value = {
      name: '',
      phone: '',
      email: '',
      gst_number: '',
      address: '',
      city: '',
      district: '',
      state: '',
      country: '',
      pin_code: ''
    };

    toast.success("Customer added successfully!");
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

// Watch Customer
watch(() => form.value.customer_id, (newVal) => {
  if (!newVal || !Array.isArray(customers.value)) {
    selectedCustomer.value = null;
    return;
  }
  selectedCustomer.value = customers.value.find(s => s.id == newVal);
});

const isInternationalCustomer = computed(() => {
  return selectedCustomer.value && selectedCustomer.value.country && selectedCustomer.value.country.toLowerCase() !== 'india';
});

const currencySymbol = computed(() => {
  if (!isInternationalCustomer.value) return '₹';
  switch (form.value.currency) {
    case 'USD': return '$';
    case 'GBP': return '£';
    case 'EUR': return '€';
    case 'SGD': return 'S$';
    case 'SAR': return 'SR';
    case 'CAD': return 'C$';
    case 'AUD': return 'A$';
    case 'AED': return 'AED';
    case 'INR': return '₹';
    default: return form.value.currency || '₹';
  }
});

watch(isInternationalCustomer, (isInternational) => {
  if (isInternational) {
    form.value.accepted = false;
    if (form.value.currency === 'INR') {
      form.value.currency = 'USD';
    }
  } else {
    form.value.currency = 'INR';
  }
  form.value.exchange_rate = 1.0000;
});

const page = usePage();
const storeState = computed(() => page.props.auth?.user?.state || '');

const useAlternateUnits = ref(!!page.props.auth?.user?.allow_alternate_units);
watch(useAlternateUnits, (newVal) => {
  if (!newVal) {
    form.value.sale_items.forEach(item => {
      item.alternate_quantity = "";
      item.width = "";
      item.height = "";
    });
  }
});

const isInterstate = computed(() => {
  if (!selectedCustomer.value || !selectedCustomer.value.state) return false;
  return storeState.value.trim().toLowerCase() !== selectedCustomer.value.state.trim().toLowerCase();
});

const filteredGstRates = computed(() => {
  if (isInterstate.value) {
    return props.gstRates.filter(r => r.name.toLowerCase().includes('igst'));
  } else {
    return props.gstRates.filter(r => !r.name.toLowerCase().includes('igst'));
  }
});

// Watch isInterstate to update selected GST rates when customer changes
watch(isInterstate, (newVal) => {
  form.value.sale_items.forEach(item => {
    if (!item.gst_rate_id) return;
    const currentRate = props.gstRates.find(r => r.id === item.gst_rate_id);
    if (currentRate) {
      const targetRate = props.gstRates.find(r =>
        parseFloat(r.rate) === parseFloat(currentRate.rate) &&
        (newVal ? r.name.toLowerCase().includes('igst') : !r.name.toLowerCase().includes('igst'))
      );
      if (targetRate) {
        item.gst_rate_id = targetRate.id;
        if (parseFloat(targetRate.igst) > 0) {
          item.cgst = parseFloat(targetRate.igst) / 2;
          item.sgst = parseFloat(targetRate.igst) / 2;
        } else {
          item.cgst = parseFloat(targetRate.cgst) || 0;
          item.sgst = parseFloat(targetRate.sgst) || 0;
        }
      }
    }
  });
});

const hasGstSelected = computed(() => {
  return form.value.sale_items.some(item => !!item.gst_rate_id);
});

watch(hasGstSelected, (newVal) => {
  if (newVal) {
    form.value.accepted = true;
  }
});

watch(() => form.value.accepted, (newVal) => {
  if (!newVal) {
    form.value.sale_items.forEach(item => {
      item.gst_rate_id = null;
      item.cgst = 0;
      item.sgst = 0;
    });
  }
});

// Watch sale items
watch(() => form.value.sale_items, (newSaleItems) => {
  newSaleItems.forEach(item => {
    const selectedProduct = productRegistry.value[item.product_id] || products.value.find(product => product.id === item.product_id);
    if (selectedProduct) {
      if (!item.last_product_id || item.last_product_id !== item.product_id) {
        item.unit_type = selectedProduct.unit_type;
        item.width = selectedProduct.width || "";
        item.height = selectedProduct.height || "";
        item.alternate_unit_type = selectedProduct.alternate_unit_type || "";
        item.alternate_quantity = "";
        item.quantity = "";
        item.last_product_id = item.product_id;
        item.gst_rate_id = "";
        item.cgst = 0;
        item.sgst = 0;
        item.price = selectedProduct.price || "";
        item.description = selectedProduct.description || "";
        
        item.last_width = item.width;
        item.last_height = item.height;
        item.last_alternate_quantity = item.alternate_quantity;
        item.last_quantity = item.quantity;
      }

      // Check what changed and recalculate accordingly
      const currentWidth = parseFloat(item.width) || 0;
      const currentHeight = parseFloat(item.height) || 0;
      const currentAltQty = parseFloat(item.alternate_quantity) || 0;
      const currentQty = parseFloat(item.quantity) || 0;

      if (item.width !== item.last_width || item.height !== item.last_height || item.alternate_quantity !== item.last_alternate_quantity) {
        if (currentWidth > 0 && currentHeight > 0) {
          const calculatedQty = currentAltQty * currentWidth * currentHeight;
          item.quantity = calculatedQty % 1 === 0 ? calculatedQty.toString() : calculatedQty.toFixed(2);
        }
        item.last_width = item.width;
        item.last_height = item.height;
        item.last_alternate_quantity = item.alternate_quantity;
        item.last_quantity = item.quantity;
      } else if (item.quantity !== item.last_quantity) {
        if (currentWidth > 0 && currentHeight > 0) {
          const calculatedAltQty = currentQty / (currentWidth * currentHeight);
          item.alternate_quantity = calculatedAltQty % 1 === 0 ? calculatedAltQty.toString() : calculatedAltQty.toFixed(2);
        }
        item.last_quantity = item.quantity;
        item.last_alternate_quantity = item.alternate_quantity;
      }

      const quantity = parseFloat(item.quantity) || 0;
      const price = parseFloat(item.price) || 0;
      const baseAmount = quantity * price;
      item.baseAmount = baseAmount;
    }
  });
}, { deep: true });

const onGstRateChange = (item) => {
  const selectedRate = props.gstRates.find(r => r.id === item.gst_rate_id);
  if (selectedRate) {
    if (parseFloat(selectedRate.igst) > 0) {
      item.cgst = parseFloat(selectedRate.igst) / 2;
      item.sgst = parseFloat(selectedRate.igst) / 2;
    } else {
      item.cgst = parseFloat(selectedRate.cgst) || 0;
      item.sgst = parseFloat(selectedRate.sgst) || 0;
    }
  } else {
    item.cgst = 0;
    item.sgst = 0;
  }
};

// Watcher to fetch supplier data when selected supplier changes
watch(
  () => selectedCustomer.value, // watch the entire object
  async (customer) => {
    const newId = customer?.id;

    if (newId) {
      try {
        const response = await axios.get(`/sale/payment/${newId}`);
        customerData.value = response.data;
        console.log('Fetched customer data:', customerData.value);
      } catch (error) {
        console.error('Error fetching customer data:', error);
        customerData.value = null;
      }
    } else {
      customerData.value = null;
    }
  },
  { immediate: true }
);

const addRow = () => {
    // Add a new row to the sale_items array
    form.value.sale_items.push({
        product_id: "",
        temp_product_name: "",
        unit_type: "",
        quantity: "",
        price: "",
        sgst: 0,
        cgst: 0,
        gst_rate_id: "",
        last_product_id: "",
        width: "",
        height: "",
        alternate_quantity: "",
        alternate_unit_type: "",
        last_width: "",
        last_height: "",
        last_alternate_quantity: "",
        last_quantity: "",
        description: ""
    });
};

const removeRow = (index) => {
    if (form.value.sale_items.length > 1) {
        form.value.sale_items.splice(index, 1);
    } else {
        toast.error("At least one row is required.");
    }
};

const totalGST = computed(() => {
    if (!form.value.accepted) return 0;
    return form.value.sale_items.reduce((sum, item) => {
        const quantity = parseFloat(item.quantity) || 0;
        const price = parseFloat(item.price) || 0;
        const sgst = parseFloat(item.sgst) || 0;
        const cgst = parseFloat(item.cgst) || 0;
        const gst = sgst + cgst;
        const baseAmount = quantity * price;
        return sum + (baseAmount * gst / 100);
    }, 0);
});

const totalAmount = computed(() => {
    return form.value.sale_items.reduce((sum, item) => {
        const quantity = parseFloat(item.quantity) || 0;
        const price = parseFloat(item.price) || 0;
        const baseAmount = quantity * price;
        return sum + baseAmount;

    }, 0);
});

const grandTotal = computed(() => {

    let  total = totalAmount.value + totalGST.value;
     total -= form.value.discount || 0;
    return total;

});

const paymentStatus = computed(() => {
  if (!customerData.value) return 'Unpaid';

  const paidNow = parseFloat(form.value.paid) || 0;
  const currentNet = paidNow - grandTotal.value;

  if (paidNow === 0) return 'Unpaid';
  else if (currentNet < 0) return 'Partial';
  else if (currentNet > 0) return 'Advance';
  else return 'Paid';
});

// Show modal first, then submit all
const openPaymentModal = () => {
    const { customer_id, sale_items } = form.value;

    if (!customer_id) {
        toast.error("Please select a customer.");
        return;
    }

    for (let i = 0; i < sale_items.length; i++) {
        const item = sale_items[i];
        const quantity = parseFloat(item.quantity);
        const price = parseFloat(item.price);

        if (!item.product_id) {
            toast.error(`Item ${i + 1}: Product is required.`);
            return;
        }

        if (isNaN(quantity) || quantity <= 0) {
            toast.error(`Item ${i + 1}: Quantity is required.`);
            return;
        }

        if (isNaN(price) || price <= 0) {
            toast.error(`Item ${i + 1}: Price is required.`);
            return;
        }

        const selectedProduct = products.value.find(p => p.id === item.product_id);
        if (!selectedProduct) {
            toast.error(`Item ${i + 1}: Product not found.`);
            return;
        }

        // if (quantity > selectedProduct.stock_quantity) {
        //     toast.error(`Item ${i + 1}: Only ${selectedProduct.stock_quantity} quantity are available in stock.`);
        //     return;
        // }

    }

    lastActiveElement.value = document.activeElement;
    showPaymentModal.value = true;
};

// Watch showPaymentModal to manage focus
watch(showPaymentModal, async (isOpen) => {
  if (isOpen) {
    await nextTick();
    if (paymentDiscountInput.value) {
      paymentDiscountInput.value.focus();
    }
  } else {
    if (lastActiveElement.value) {
      await nextTick();
      lastActiveElement.value.focus();
      lastActiveElement.value = null;
    }
  }
});

//add 225/08/25

const finalBalance = computed(() => {
  if (!customerData.value) return null;

  const paidNow = parseFloat(form.value.paid) || 0;
  const currentNet = paidNow - grandTotal.value;

  if (currentNet > 0) {
    return { type: 'advance', amount: currentNet };
  } else if (currentNet < 0) {
    return { type: 'due', amount: Math.abs(currentNet) };
  } else {
    return { type: 'none', amount: 0 };
  }
});

const submitForm = async () => {
  try {
    const rate = parseFloat(form.value.exchange_rate) || 1.0;
    const payload = {
      ...form.value,
      exchange_rate: rate,
      discount: (parseFloat(form.value.discount) || 0) * rate,
      paid: (parseFloat(form.value.paid) || 0) * rate,
      grand_total: grandTotal.value * rate,
      total_amount: totalAmount.value * rate,
      GstAmount: totalGST.value * rate,
      payment_status: paymentStatus.value,
      sale_items: form.value.sale_items.map(item => ({
        ...item,
        price: (parseFloat(item.price) || 0) * rate,
        baseAmount: (parseFloat(item.baseAmount) || 0) * rate
      }))
    };

    const response = await axios.post(`/sale/store`, payload);
    toast.success(response.data.message);
    showPaymentModal.value = false;

    console.log('response', response.data);
    const invoiceUrl = response.data.invoice_url;
    window.open(invoiceUrl, '_blank');

    form.value = {
      customer_id: "",
      referral_user_id: "",
      sale_date: new Date().toLocaleDateString('en-CA'),
      grand_total: "",
      GstAmount: "",
      accepted: false,
      total_amount: "",
      paid: 0,
      discount: 0,
      payment_method: '',
      payment_status: "",
      currency: 'INR',
      exchange_rate: 1.0000,
      sale_items: [{
        product_id: "",
        temp_product_name: "",
        unit_type: "",
        sgst: "",
        cgst: "",
        quantity: "",
        price: "",
        baseAmount: "",
        gst_rate_id: "",
        last_product_id: "",
        width: "",
        height: "",
        alternate_quantity: "",
        alternate_unit_type: "",
        last_width: "",
        last_height: "",
        last_alternate_quantity: "",
        last_quantity: "",
        description: ""
      }],
    };
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

const showProductModal = ref(false);
const activeProductRowIndex = ref(null);

const openProductModal = (rowIndex, search) => {
  lastActiveElement.value = document.activeElement;
  activeProductRowIndex.value = rowIndex;
  productSearchQuery.value = search || '';
  showProductModal.value = true;
};

watch(showProductModal, async (isOpen) => {
  if (!isOpen && lastActiveElement.value) {
    await nextTick();
    lastActiveElement.value.focus();
    lastActiveElement.value = null;
  }
});

const handleProductSuccess = (createdProduct) => {
  productRegistry.value[createdProduct.id] = createdProduct;
  products.value.push(createdProduct);
  if (activeProductRowIndex.value !== null) {
    form.value.sale_items[activeProductRowIndex.value].product_id = createdProduct.id;
  }
  showProductModal.value = false;
};

const showDescriptionPopup = ref(false);
const activeDescriptionIndex = ref(null);
const tempDescription = ref("");
const descriptionTextarea = ref(null);

const openDescriptionPopup = (index) => {
    activeDescriptionIndex.value = index;
    const items = form.value.sale_items;
    tempDescription.value = items[index]?.description || "";
    showDescriptionPopup.value = true;
    nextTick(() => {
        if (descriptionTextarea.value) {
            descriptionTextarea.value.focus();
        }
    });
};

const closeDescriptionPopup = () => {
    const index = activeDescriptionIndex.value;
    showDescriptionPopup.value = false;
    activeDescriptionIndex.value = null;
    if (index !== null) {
        nextTick(() => {
            let inputToFocus = null;
            const items = form.value.sale_items;
            if (useAlternateUnits.value && items[index] && items[index].alternate_unit_type) {
                inputToFocus = document.getElementById(`alt-qty-input-${index}`);
            }
            if (!inputToFocus) {
                inputToFocus = document.getElementById(`qty-input-${index}`);
            }
            if (inputToFocus) {
                inputToFocus.focus();
                if (typeof inputToFocus.select === 'function') {
                    inputToFocus.select();
                }
            }
        });
    }
};

const saveDescriptionPopup = () => {
    const items = form.value.sale_items;
    if (activeDescriptionIndex.value !== null && items[activeDescriptionIndex.value]) {
        items[activeDescriptionIndex.value].description = tempDescription.value;
    }
    closeDescriptionPopup();
};


const activeEditingAltRow = ref(null);
const startEditingAlt = (index) => {
    activeEditingAltRow.value = index;
    nextTick(() => {
        const input = document.getElementById(`width-input-${index}`);
        if (input) input.focus();
    });
};
const handleAltFocusOut = (event, index) => {
    if (!event.currentTarget.contains(event.relatedTarget)) {
        activeEditingAltRow.value = null;
    }
};
</script>
<template>
    <Head title="Sale">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>
    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-2xl border border-slate-100 shadow-sm space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-5">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-medium mb-1">
                    <a :href="route('sale')" class="hover:text-indigo-600 transition">Sales</a>
                    <span>/</span>
                    <span class="text-slate-600">New Sale</span>
                </div>
                <h2 class="text-2xl font-bold text-[#2E2C92] tracking-tight">Create New Sale</h2>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                    Draft Sale
                </span>
            </div>
        </div>
        
        <div v-if="prefilledFromEstimateNo" class="p-4 mb-6 bg-purple-50 border border-purple-200 text-purple-800 rounded-lg flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <span class="text-lg">⚡</span>
                <span>Prefilled from <strong>Estimate #{{ prefilledFromEstimateNo }}</strong>. Stock levels will be deducted upon saving this sale.</span>
            </div>
            <button @click="clearEstimatePrefill" class="text-purple-600 hover:text-purple-900 font-semibold text-xs bg-purple-100 px-2.5 py-1 rounded-md transition duration-150">
                Clear Prefill
            </button>
        </div>

        <div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-2">Customer <span class="text-red-500">*</span></label>
                    <vSelect
                        v-model="form.customer_id"
                        :options="customers"
                        label="name"
                        :reduce="customer => customer.id"
                        placeholder="Search or select customer"
                        class="w-full text-black bg-white"
                        @keydown.enter="onEnterKey"
                        @search="onCustomerSearch"
                    >
                        <template #no-options>
                            <div class="px-3 py-2 text-gray-500">
                                <span v-if="!customerSearchQuery">Type to search customer name...</span>
                                <span v-else class="flex justify-between"><span>No customers found.</span>
                                    <button
                                        id="add-customer-btn"
                                        @click.stop="showCustomerModal = true"
                                        :class="customerSearchQuery
                                                ? 'mt-2 inline-flex items-center text-blue-600 text-sm font-semibold border border-blue-300 rounded-lg px-3 py-1.5'
                                                : 'mt-2 inline-flex items-center text-blue-600 text-sm font-semibold'"
                                    >
                                        ➕ Add New Customer
                                    </button>
                                </span>
                            </div>
                        </template>
                    </vSelect>
                </div>
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-2">Sales Date <span class="text-red-500">*</span></label>
                    <input
                        type="date"
                        v-model="form.sale_date"
                        required
                        class="w-full border border-slate-200 px-3 py-2 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none transition bg-white text-black text-sm shadow-sm"
                    />
                </div>
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-2">Referral User (Optional)</label>
                    <vSelect
                        v-model="form.referral_user_id"
                        :options="referralUsers"
                        label="name"
                        :reduce="user => user.id"
                        placeholder="Search or select referral user"
                        class="w-full text-black bg-white"
                        @search="onReferralSearch"
                    >
                        <template #no-options>
                            <div class="px-3 py-2 text-gray-500">
                                <span v-if="!referralSearchQuery">Type to search referral user...</span>
                                <span v-else>No referral users found.</span>
                            </div>
                        </template>
                    </vSelect>
                </div>
            </div>
            

            <div v-if="isInternationalCustomer" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4 p-4 bg-purple-50/30 border border-purple-100 rounded-xl">
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-2">Currency</label>
                    <select v-model="form.currency" class="w-full border border-slate-200 px-3 py-2 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none transition bg-white text-black text-sm shadow-sm">
                        <option value="USD">USD ($)</option>
                        <option value="AED">AED (AED)</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="SGD">SGD (S$)</option>
                        <option value="SAR">SAR (SR)</option>
                        <option value="CAD">CAD (C$)</option>
                        <option value="AUD">AUD (A$)</option>
                        <option value="INR">INR (₹)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-2">Exchange Rate (1 {{ form.currency }} = ? INR)</label>
                    <input type="number" v-model="form.exchange_rate" step="any" min="0.0001" class="w-full border border-slate-200 px-3 py-2 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none transition bg-white text-black text-sm shadow-sm" />
                </div>
            </div>
            
            <div v-if="selectedCustomer" class="mt-6 p-5 rounded-2xl bg-indigo-50/30 border border-indigo-100/50 shadow-sm transition-all duration-300">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm text-slate-700">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-white border border-indigo-100/80 text-indigo-600 shadow-sm">
                            <i class="bi bi-telephone text-base leading-none"></i>
                        </div>
                        <div>
                            <span class="block text-xxs font-semibold text-slate-400 uppercase tracking-wider">Phone</span>
                            <span class="font-medium text-slate-800">{{ selectedCustomer.phone || 'Not Provided' }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-white border border-indigo-100/80 text-indigo-600 shadow-sm">
                            <i class="bi bi-envelope text-base leading-none"></i>
                        </div>
                        <div>
                            <span class="block text-xxs font-semibold text-slate-400 uppercase tracking-wider">Email</span>
                            <span class="font-medium text-slate-800 truncate max-w-[200px]" :title="selectedCustomer.email">{{ selectedCustomer.email || 'Not Provided' }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-white border border-indigo-100/80 text-indigo-600 shadow-sm">
                            <i class="bi bi-geo-alt text-base leading-none"></i>
                        </div>
                        <div>
                            <span class="block text-xxs font-semibold text-slate-400 uppercase tracking-wider">Address</span>
                            <span class="font-medium text-slate-800 truncate max-w-[250px]" :title="selectedCustomer.address">{{ selectedCustomer.address || 'Not Provided' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                <h3 class="text-lg font-bold text-[#2E2C92] flex items-center gap-2">
                    <i class="bi bi-bag-check text-indigo-600"></i>
                    Sale Items
                </h3>
            </div>
            
            <div class="hidden md:block overflow-hidden border border-slate-100 rounded-2xl shadow-sm bg-white mt-4">
                <table class="w-full table-auto border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-100 text-slate-500 text-xs font-semibold tracking-wider uppercase">
                        <tr>
                            <th class="px-4 py-3.5 text-left">Product <span class="text-red-500">*</span></th>
                            <th v-if="form.accepted" class="px-4 py-3.5 text-left">GST</th>
                            <th v-if="useAlternateUnits" class="px-4 py-3.5 text-left" style="width: 18%;">Alt Qty / Size</th>
                            <th class="px-4 py-3.5 text-left">Quantity <span class="text-red-500">*</span></th>
                            <th class="px-4 py-3.5 text-left">Unit Type</th>
                            <th class="px-4 py-3.5 text-left">Price <span class="text-red-500">*</span></th>
                            <th class="px-4 py-3.5 text-left">Net Amount</th>
                            <th class="px-4 py-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in form.sale_items" :key="index" class="hover:bg-slate-50/40 transition">
                            <td class="border-t border-slate-100 px-4 py-4 min-w-[220px]">
                                <div class="relative w-full">
                                    <input
                                        type="text"
                                        v-model="item.temp_product_name"
                                        @focus="onProductInputFocus(index)"
                                        @input="onProductInputChanged(index, $event.target.value)"
                                        @keydown.down.prevent="navigateSidebar(1)"
                                        @keydown.up.prevent="navigateSidebar(-1)"
                                        @keydown.enter.prevent="selectHighlightedProduct"
                                        @keydown.tab="closeSidebar"
                                        @keydown.esc="closeSidebar"
                                        placeholder="Search product..."
                                        class="w-full border border-slate-200 px-3 py-2 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none transition bg-white text-black text-sm shadow-sm"
                                    />
                                    <button 
                                        v-if="!item.product_id"
                                        type="button"
                                        @click.stop="openProductModal(index, item.temp_product_name)"
                                        class="absolute right-3 top-2.5 text-[#2E2C92] hover:text-[#1b1959] text-xs font-bold"
                                        title="Add New Product"
                                    >
                                        ➕ Add
                                    </button>
                                </div>
                                <div v-if="page.props.auth?.user?.allow_provide_additional_descriptions && item.description" class="mt-1.5">
                                    <div class="text-xxs text-slate-500 bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-100 flex items-center justify-between shadow-xxs">
                                        <span class="truncate max-w-[200px]" :title="item.description">{{ item.description }}</span>
                                        <button type="button" @click="openDescriptionPopup(index)" class="text-indigo-600 hover:text-indigo-800 ml-2 shrink-0">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td v-if="form.accepted" class="border-t border-slate-100 px-4 py-4 min-w-[140px]">
                                <select
                                    v-model="item.gst_rate_id"
                                    @change="onGstRateChange(item)"
                                    @keydown.enter.prevent="moveToNextInput"
                                    class="w-full border border-slate-200 px-2 py-2 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none transition bg-white text-black text-sm shadow-sm"
                                >
                                    <option value="" disabled>Select GST</option>
                                    <option v-for="rate in filteredGstRates" :key="rate.id" :value="rate.id">
                                        {{ rate.name }}
                                    </option>
                                </select>
                            </td>
                            <td v-if="useAlternateUnits" class="border-t border-slate-100 px-4 py-4 min-w-[150px]">
                                <div v-if="item.alternate_unit_type" class="flex flex-col gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <input 
                                            :id="'alt-qty-input-' + index"
                                            type="number" 
                                            step="any" 
                                            v-model="item.alternate_quantity" 
                                            class="w-20 px-2.5 py-1.5 border border-slate-200 rounded-xl text-xs text-black bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none shadow-sm transition" 
                                            placeholder="Alt Qty" 
                                        />
                                        <span class="text-xs text-slate-500 font-semibold uppercase">{{ item.alternate_unit_type }}</span>
                                    </div>
                                    <div v-if="activeEditingAltRow === index" @focusout="handleAltFocusOut($event, index)" class="flex items-center gap-1 text-xs text-slate-400">
                                        <input 
                                            :id="`width-input-${index}`"
                                            type="number" 
                                            step="any" 
                                            v-model="item.width" 
                                            class="w-10 px-1 py-1 border border-slate-200 rounded-lg text-center text-black bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none shadow-sm transition placeholder:text-slate-300" 
                                            placeholder="W" 
                                        />
                                        <span class="text-xxs font-bold">×</span>
                                        <input 
                                            type="number" 
                                            step="any" 
                                            v-model="item.height" 
                                            class="w-10 px-1 py-1 border border-slate-200 rounded-lg text-center text-black bg-white focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none shadow-sm transition placeholder:text-slate-300" 
                                            placeholder="H" 
                                        />
                                    </div>
                                    <div 
                                        v-else 
                                        @click="startEditingAlt(index)"
                                        class="cursor-pointer hover:bg-slate-50 px-2 py-1 rounded-lg border border-dashed border-slate-200 hover:border-indigo-300 transition text-xxs text-slate-400 font-medium inline-block align-middle self-start"
                                        title="Click to edit size"
                                    >
                                        Size: {{ item.width || 0 }} × {{ item.height || 0 }}
                                    </div>
                                </div>
                                <div v-else class="text-slate-300 text-xs">-</div>
                            </td>
                            <td class="border-t border-slate-100 px-4 py-4">
                                <input :id="'qty-input-' + index" type="number" name="quantity" v-model="item.quantity" required
                                    @keydown.enter.prevent="moveToNextInput"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none text-sm transition text-black bg-white shadow-sm"
                                    placeholder="Qty" />
                            </td>
                            <td class="border-t border-slate-100 px-4 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200/50 uppercase">{{ item.unit_type || 'pcs' }}</span>
                            </td>
                            <td class="border-t border-slate-100 px-4 py-4">
                                <input type="number" name="price" v-model="item.price" required
                                    @keydown.enter.prevent="moveToNextInput"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 focus:outline-none text-sm transition text-black bg-white shadow-sm"
                                    placeholder="Price" />
                            </td>
                            <td class="border-t border-slate-100 px-4 py-4 font-bold text-slate-800 text-sm">
                                {{ currencySymbol }} {{ ((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0)).toFixed(2) }}
                            </td>
                            <td class="border-t border-slate-100 px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button @click="removeRow(index)" type="button"
                                        class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-100 transition flex items-center justify-center cursor-pointer">
                                        <i class="bi bi-trash text-lg leading-none"></i>
                                    </button>
                                    <button v-if="index === form.sale_items.length - 1" @click="addRow" type="button"
                                        class="p-2 rounded-xl text-indigo-600 hover:text-white bg-indigo-50 hover:bg-indigo-600 border border-indigo-100 hover:border-indigo-600 transition flex items-center justify-center cursor-pointer"
                                    >
                                        <i class="bi bi-plus-lg text-lg leading-none"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="md:hidden space-y-4">
                <div v-for="(item, index) in form.sale_items" :key="'mobile-' + index" class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-4">
                    <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                        <span class="font-bold text-sm text-[#292688]">Item #{{ index + 1 }}</span>
                        <button @click="removeRow(index)" type="button" class="text-red-600 hover:text-red-800 text-sm font-semibold flex items-center gap-1">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Product <span class="text-red-500">*</span></label>
                            <div class="relative w-full">
                                <input
                                    type="text"
                                    v-model="item.temp_product_name"
                                    @focus="onProductInputFocus(index)"
                                    @input="onProductInputChanged(index, $event.target.value)"
                                    @keydown.down.prevent="navigateSidebar(1)"
                                    @keydown.up.prevent="navigateSidebar(-1)"
                                    @keydown.enter.prevent="selectHighlightedProduct"
                                    @keydown.tab="closeSidebar"
                                    @keydown.esc="closeSidebar"
                                    placeholder="Search or select product"
                                    class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] text-black bg-white focus:outline-none"
                                />
                                <button 
                                    v-if="!item.product_id"
                                    type="button"
                                    @click.stop="openProductModal(index, item.temp_product_name)"
                                    class="absolute right-3 top-2.5 text-[#2E2C92] hover:text-[#1b1959] text-xs font-semibold"
                                    title="Add New Product"
                                >
                                    ➕ Add
                                </button>
                            </div>
                            <div v-if="page.props.auth?.user?.allow_provide_additional_descriptions && item.description" class="mt-1.5">
                                <div class="text-xs text-gray-500 bg-gray-50 px-3 py-1.5 rounded-xl border border-gray-100 flex items-center justify-between">
                                    <span class="truncate max-w-[250px]" :title="item.description">{{ item.description }}</span>
                                    <button type="button" @click="openDescriptionPopup(index)" class="text-indigo-600 hover:text-indigo-800 ml-1 shrink-0">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-if="form.accepted" class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-gray-500 mb-1">GST Rate</label>
                                <select
                                    v-model="item.gst_rate_id"
                                    @change="onGstRateChange(item)"
                                    class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm bg-white"
                                >
                                    <option value="" disabled>Select GST</option>
                                    <option v-for="rate in filteredGstRates" :key="rate.id" :value="rate.id">
                                        {{ rate.name }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div v-if="useAlternateUnits && item.alternate_unit_type" class="grid grid-cols-3 gap-2 bg-gray-100 p-2 rounded-lg border border-gray-200">
                            <div>
                                <label class="block text-xxs font-semibold text-gray-500 mb-1">Alt Qty ({{ item.alternate_unit_type }})</label>
                                <input type="number" step="any" v-model="item.alternate_quantity" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white text-black" placeholder="Alt Qty" />
                            </div>
                            <div>
                                <label class="block text-xxs font-semibold text-gray-500 mb-1">Width</label>
                                <input type="number" step="any" v-model="item.width" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white text-black text-center" placeholder="W" />
                            </div>
                            <div>
                                <label class="block text-xxs font-semibold text-gray-500 mb-1">Height</label>
                                <input type="number" step="any" v-model="item.height" class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-white text-black text-center" placeholder="H" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Quantity <span class="text-red-500">*</span></label>
                                <input type="number" v-model="item.quantity" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Qty" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Price <span class="text-red-500">*</span></label>
                                <input type="number" v-model="item.price" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Price" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 bg-white p-3 rounded-lg border border-gray-100 text-xs font-medium text-gray-500">
                            <div v-if="form.accepted">
                                <span class="block text-gray-400">GST</span>
                                <span class="text-gray-800 font-semibold">
                                    <template v-if="item.gst_rate_id">
                                        {{ (parseFloat(item.cgst) || 0) + (parseFloat(item.sgst) || 0) }}%
                                    </template>
                                    <template v-else>-</template>
                                </span>
                            </div>
                            <div>
                                <span class="block text-gray-400">Unit Type</span>
                                <span class="text-gray-800 font-semibold">{{ item.unit_type || 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="block text-gray-400">Net Amount</span>
                                <span class="text-gray-800 font-bold">{{ currencySymbol }} {{ ((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0)).toFixed(2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <button @click="addRow" type="button"
                    class="w-full flex items-center justify-center gap-2 bg-indigo-50/50 hover:bg-indigo-600 text-indigo-600 hover:text-white py-3 rounded-xl font-semibold transition border border-indigo-100/80 hover:border-indigo-600 shadow-sm cursor-pointer">
                    <i class="bi bi-plus-circle-dotted text-lg leading-none"></i> Add New Row
                </button>
            </div>
            <div class="flex justify-end mt-8 border-t border-slate-100 pt-6">
                <button @click="openPaymentModal" class="w-full md:w-auto bg-gradient-to-r from-[#2E2C92] to-[#1c1a6e] hover:from-[#1c1a6e] hover:to-[#2E2C92] text-white px-8 py-3.5 rounded-2xl font-bold transition-all duration-300 shadow-md hover:shadow-lg flex items-center justify-center gap-2 hover:scale-[1.02] cursor-pointer">
                    Submit & Proceed to Payment
                    <i class="bi bi-arrow-right-short text-lg leading-none"></i>
                </button>
            </div>
        </div>
    </div>
    <div v-if="showPaymentModal"
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
         style="z-index: 9999;"
         @click.self="showPaymentModal = false">
        <form @submit.prevent="submitForm" class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-md my-auto transform transition-all duration-300 border border-gray-100 space-y-4">
            <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                <h2 class="text-xl font-bold text-[#292688]">Payment Details</h2>
                <button type="button" @click="showPaymentModal = false" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fa fa-close"></i>
                </button>
            </div>
            <div class="flex justify-between items-center">
                <label class="text-gray-700 font-medium">Discount</label>
                <input type="number" ref="paymentDiscountInput" v-model="form.discount"
                    class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                    :placeholder="currencySymbol + '0.00'" min="0" step="any" />
            </div>
            <div class="space-y-4 border-t pt-4">
                <div v-if="!isInternationalCustomer" class="flex justify-between items-center">
                    <label class="inline-flex items-center space-x-2">
                        <input type="checkbox" v-model="form.accepted" class="form-checkbox h-5 w-5 text-[#292688]">
                        <span class="text-sm text-gray-700 font-semibold">Apply To GST</span>
                    </label>
                </div>
                <div v-if="form.accepted" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">GST</span>
                    <span class="text-gray-800 font-bold">{{ currencySymbol }} {{ totalGST.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Total Net Amount</span>
                    <span class="text-gray-800 font-bold">{{ currencySymbol }} {{ totalAmount.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Grand Total</span>
                    <span class="text-black font-bold text-lg">{{ currencySymbol }} {{ grandTotal.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Payment Method</label>
                    <select v-model="form.payment_method"
                            class="w-40 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Paid Amount</label>
                    <input type="number" v-model.number="form.paid"
                        class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Amount" min="0" step="any" />
                </div>
                <div v-if="finalBalance?.type === 'advance'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Advance Amount</span>
                    <span class="text-green-600 font-bold">{{ currencySymbol }} {{ typeof finalBalance.amount === 'number' ? finalBalance.amount.toFixed(2) : finalBalance.amount }}</span>
                </div>
                <div v-if="finalBalance?.type === 'due'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Due Amount</span>
                    <span class="text-red-600 font-bold">{{ currencySymbol }} {{ typeof finalBalance.amount === 'number' ? finalBalance.amount.toFixed(2) : finalBalance.amount }}</span>
                </div>
                <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                    <span class="text-gray-700 font-semibold">Payment Status</span>
                    <span class="text-blue-600 font-bold">{{ paymentStatus }}</span>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showPaymentModal = false" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition cursor-pointer">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow-md transition cursor-pointer">Final Submit</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Add Customer Modal -->
    <div v-if="showCustomerModal"
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
         style="z-index: 99999;"
         @click.self="showCustomerModal = false">
        <form @submit.prevent="submitCustomer" class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-md my-auto transform transition-all duration-300 border border-gray-100 space-y-4">
            <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                <h2 class="text-xl font-bold text-[#2E2C92]">Add New Customer</h2>
                <button type="button" @click="showCustomerModal = false" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fa fa-close"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" ref="newCustomerNameInput" v-model="newCustomer.name" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" v-model="newCustomer.phone" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" v-model="newCustomer.email" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">GST Number</label>
                    <input type="text" v-model="newCustomer.gst_number" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" placeholder="e.g. 22AAAAA1111A1Z1" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea v-model="newCustomer.address" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" rows="2"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <select v-model="newCustomer.country"
                            class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none bg-white text-black">
                            <option value="" disabled>Select Country</option>
                            <option v-for="c in $page.props.countries" :key="c" :value="c">
                                {{ c }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <vSelect
                            v-if="!newCustomer.country || newCustomer.country === 'India'"
                            :options="$page.props.gst_states"
                            label="display"
                            :reduce="state => state.name"
                            v-model="newCustomer.state"
                            placeholder="Search & Select State"
                            class="w-full text-black bg-white"
                        ></vSelect>
                        <input
                            v-else
                            type="text"
                            v-model="newCustomer.state"
                            class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none"
                            placeholder="Enter State"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4" v-if="!newCustomer.country || newCustomer.country === 'India'">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                        <vSelect
                            :options="availableCustomerDistricts"
                            v-model="newCustomer.district"
                            placeholder="Search & Select District"
                            class="w-full text-black bg-white"
                            :disabled="!newCustomer.state"
                        ></vSelect>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PIN Code</label>
                        <input type="text" v-model="newCustomer.pin_code" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-2">
                <button
                    type="button"
                    @click="showCustomerModal = false"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition cursor-pointer font-medium"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl shadow-md transition cursor-pointer font-medium"
                >
                    Save Customer
                </button>
            </div>
        </form>
    </div>
    <!-- Add Product Modal -->
    <AddProductModal
        :show="showProductModal"
        :initialName="productSearchQuery"
        :categories="categories"
        :unitTypes="unitTypes"
        @close="showProductModal = false"
        @success="handleProductSuccess"
    />
    <!-- List of Stock Items Sidebar (Tally style) -->
    <div v-if="activeSearchIndex !== null" @click="closeSidebar" class="fixed inset-0 z-40 bg-black/10 transition-opacity"></div>
    <div v-if="activeSearchIndex !== null" class="fixed right-0 top-0 bottom-0 w-96 bg-slate-50 border-l border-slate-200 shadow-2xl z-50 flex flex-col transition-all duration-300 transform translate-x-0">
        <!-- Sidebar Header -->
        <div class="bg-[#292688] text-white p-4 flex justify-between items-center shadow-md">
            <div>
                <h3 class="font-bold text-sm tracking-wide">List of Stock Items</h3>
                <p class="text-xxs text-slate-300">Use ↑/↓ and Enter to select from keyboard</p>
            </div>
            <button @click="closeSidebar" type="button" class="text-white hover:text-red-200">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>
        <!-- Search Status / Help -->
        <div class="px-4 py-2 bg-slate-100 border-b border-slate-200 text-xxs text-slate-500 flex justify-between">
            <span>Filtering: "{{ searchQuery }}"</span>
            <span>{{ products.length }} items found</span>
        </div>
        <!-- Products List -->
        <div class="flex-1 overflow-y-auto p-2 space-y-1">
            <div 
                v-for="(product, idx) in products" 
                :key="product.id"
                :id="'sidebar-item-' + idx"
                @click="selectProduct(product)"
                @mouseenter="selectedSidebarProductIndex = idx"
                :class="[
                    idx === selectedSidebarProductIndex 
                        ? 'bg-[#292688] text-white shadow-md' 
                        : 'hover:bg-slate-200 text-slate-800'
                ]"
                class="flex justify-between items-center p-3 rounded-lg cursor-pointer transition-all duration-150 text-xs"
            >
                <div class="flex flex-col text-left pr-4">
                    <span class="font-semibold">{{ product.name }}</span>
                    <span class="text-xxs opacity-75 mt-0.5" :class="idx === selectedSidebarProductIndex ? 'text-slate-200' : 'text-slate-500'">
                        SKU: {{ product.sku || '-' }} | Added: {{ new Date(product.created_at).toLocaleDateString() }}
                    </span>
                </div>
                <div class="text-right flex flex-col items-end shrink-0">
                    <span class="font-bold text-sm">
                        {{ product.stock_quantity ?? 0 }} {{ product.unit_type || 'pcs' }}
                    </span>
                    <span class="text-xxs opacity-75" :class="idx === selectedSidebarProductIndex ? 'text-slate-200' : 'text-slate-500'">
                        Stock Qty
                    </span>
                </div>
            </div>
            <div v-if="products.length === 0" class="text-center py-8 text-slate-400 text-xs">
                No matching stock items found.
            </div>
        </div>
    </div>

    <!-- Item Description Modal -->
    <div v-if="showDescriptionPopup" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-slate-100 overflow-hidden transform transition-all">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="bi bi-card-text text-indigo-600"></i>
                    Additional Stock Item Description
                </h3>
                <button @click="closeDescriptionPopup" class="text-slate-400 hover:text-slate-600 transition">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>
            <!-- Modal Body -->
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Item Description</label>
                    <input 
                        type="text"
                        v-model="tempDescription" 
                        placeholder="Enter description, size, or alternate details for this stock item..."
                        class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-black bg-white focus:outline-none placeholder-slate-400 transition"
                        ref="descriptionTextarea"
                        @keydown.enter.prevent="saveDescriptionPopup"
                    />
                </div>
            </div>
            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
                <button 
                    type="button" 
                    @click="closeDescriptionPopup" 
                    class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    @click="saveDescriptionPopup" 
                    class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition"
                >
                    Ok
                </button>
            </div>
        </div>
    </div>
    </AuthenticatedLayout>
</template>
