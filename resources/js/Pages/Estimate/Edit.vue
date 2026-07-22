<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";
import AddProductModal from '@/Components/AddProductModal.vue';

const props = defineProps({
  estimate: {
    type: Object,
    required: true,
  },
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
    required: true,
  },
  unitTypes: {
    type: Object,
    required: true,
  },
  gstRates: {
    type: Array,
    required: true,
  }
});

const customers = ref([...props.customers]);
const products = ref([]);

// Registry to store all products we have seen/loaded so far
const productRegistry = ref({});

// Initialize registry with initial products (from props)
if (props.products) {
  props.products.forEach(p => {
    productRegistry.value[p.id] = p;
  });
  // Since this is edit mode, start products list with the pre-loaded products (currently selected items)
  products.value = [...props.products];
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
    form.value.estimate_items.forEach(item => {
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
    const item = form.value.estimate_items[index];
    searchQuery.value = item.temp_product_name || '';
    selectedSidebarProductIndex.value = 0;
    onProductSearch(searchQuery.value);
};

const onProductInputChanged = (index, value) => {
    searchQuery.value = value;
    const item = form.value.estimate_items[index];
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
        const item = form.value.estimate_items[activeSearchIndex.value];
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
        const item = form.value.estimate_items[activeSearchIndex.value];
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

const categories = props.categories;
const unitTypes = props.unitTypes;
const estimate = props.estimate;

const form = ref({
    customer_id: estimate.customer_id,
    estimate_date: estimate.estimate_date,
    expiry_date: estimate.expiry_date || "",
    grand_total: estimate.grand_total,
    GstAmount: estimate.gst_amount,
    accepted: estimate.accepted == 1,
    total_amount: estimate.total_amount,
    discount: (parseFloat(estimate.discount) || 0) / (parseFloat(estimate.exchange_rate) || 1.0),
    notes: estimate.notes || "",
    currency: estimate.currency || 'INR',
    exchange_rate: parseFloat(estimate.exchange_rate) || 1.0000,
    estimate_items: estimate.items.map(item => {
        const sgst = parseFloat(item.sgst) || 0;
        const cgst = parseFloat(item.cgst) || 0;
        const totalProductRate = sgst + cgst;
        const matchedRate = props.gstRates.find(r => {
          const isIgst = r.name.toLowerCase().includes('igst');
          const databaseIsIgst = totalProductRate > 0 && cgst === 0 && sgst === 0;
          return parseFloat(r.rate) === totalProductRate && (databaseIsIgst ? isIgst : !isIgst);
        });
        const computedId = matchedRate ? matchedRate.id : (props.gstRates[0]?.id || "");
        // If matched rate is IGST type, split evenly
        const rateObj = props.gstRates.find(r => r.id === computedId);
        let computedCgst = cgst;
        let computedSgst = sgst;
        if (rateObj && parseFloat(rateObj.igst) > 0) {
            computedCgst = parseFloat(rateObj.igst) / 2;
            computedSgst = parseFloat(rateObj.igst) / 2;
        }
        return {
            product_id: item.product_id,
            temp_product_name: item.product_name || "",
            unit_type: item.unit_type || "",
            sgst: computedSgst,
            cgst: computedCgst,
            quantity: item.quantity || "",
            price: parseFloat(item.price || 0) / (parseFloat(estimate.exchange_rate) || 1.0),
            baseAmount: parseFloat(item.base_price || 0) / (parseFloat(estimate.exchange_rate) || 1.0),
            gst_rate_id: computedId,
            last_product_id: item.product_id,
            width: item.width || "",
            height: item.height || "",
            alternate_quantity: item.alternate_quantity || "",
            alternate_unit_type: item.alternate_unit_type || "",
            last_width: item.width || "",
            last_height: item.height || "",
            last_alternate_quantity: item.alternate_quantity || "",
            last_quantity: item.quantity || "",
            description: item.description || "",
        };
    }),
});

const selectedCustomer = ref(customers.value.find(c => c.id == estimate.customer_id) || null);
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

// Product Quick Add Modal State
const showProductModal = ref(false);
const activeRowIndexForNewProduct = ref(null);

// Move focus to the next logical input/select/button
const moveToNextInput = (event) => {
  const container = document.querySelector('.bg-white.p-8');
  if (!container) return;

  const elements = Array.from(container.querySelectorAll(
    'input:not([disabled]), select:not([disabled]), button:not([disabled]), .vs__search'
  )).filter(el => {
    const rect = el.getBoundingClientRect();
    const isVisible = rect.width > 0 && rect.height > 0;
    const isTrashBtn = el.querySelector('.bi-trash') || el.classList.contains('bg-red-600') || el.closest('button')?.classList.contains('bg-red-600') || el.querySelector('.fa-trash') || el.closest('button')?.querySelector('.fa-trash');
    const isAddRowBtn = el.closest('button')?.classList.contains('bg-green-600') || el.classList.contains('bg-green-600') || el.closest('button')?.classList.contains('bg-green-50') || el.closest('button')?.classList.contains('text-green-600');
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
  if (e.key === 'Escape' || e.key === 'Esc') {
    if (showCustomerModal.value || showProductModal.value) {
      showCustomerModal.value = false;
      showProductModal.value = false;
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

const openProductModal = (rowIndex, search = '') => {
  lastActiveElement.value = document.activeElement;
  activeRowIndexForNewProduct.value = rowIndex;
  productSearchQuery.value = search || '';
  showProductModal.value = true;
};

// Watch showProductModal to restore focus on close
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

  // Auto-select the newly created product in the active row
  if (activeRowIndexForNewProduct.value !== null && form.value.estimate_items[activeRowIndexForNewProduct.value]) {
    const targetItem = form.value.estimate_items[activeRowIndexForNewProduct.value];
    targetItem.product_id = createdProduct.id;
    targetItem.unit_type = createdProduct.unit_type;
    targetItem.sgst = createdProduct.sgst;
    targetItem.cgst = createdProduct.cgst;
    targetItem.price = createdProduct.price || 0;
  }

  showProductModal.value = false;
  activeRowIndexForNewProduct.value = null;
};

// Watch Customer ID to find Customer details
watch(() => form.value.customer_id, (newVal) => {
  if (!newVal) {
    selectedCustomer.value = null;
    return;
  }
  selectedCustomer.value = customers.value.find(c => c.id == newVal);
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
  form.value.estimate_items.forEach(item => {
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
  return form.value.estimate_items.some(item => !!item.gst_rate_id);
});

watch(hasGstSelected, (newVal) => {
  form.value.accepted = newVal;
});

// Watch Estimate Items to auto-populate product details (price, SGST, CGST, unit_type)
watch(() => form.value.estimate_items, (newItems) => {
  newItems.forEach(item => {
    const prod = productRegistry.value[item.product_id] || products.value.find(p => p.id === item.product_id);
    if (prod) {
      if (!item.last_product_id || item.last_product_id !== item.product_id) {
        item.unit_type = prod.unit_type;
        item.width = prod.width || "";
        item.height = prod.height || "";
        item.alternate_unit_type = prod.alternate_unit_type || "";
        item.alternate_quantity = "";
        item.quantity = "";
        item.last_product_id = item.product_id;
        item.gst_rate_id = "";
        item.cgst = 0;
        item.sgst = 0;
        item.price = prod.price || "";
        item.description = prod.description || "";
        
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

      const qty = parseFloat(item.quantity) || 0;
      const prc = parseFloat(item.price) || 0;
      item.baseAmount = qty * prc;
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

const addRow = () => {
    form.value.estimate_items.push({
        product_id: "",
        temp_product_name: "",
        unit_type: "",
        sgst: 0,
        cgst: 0,
        quantity: "",
        price: 0,
        baseAmount: 0,
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
    if (form.value.estimate_items.length > 1) {
        form.value.estimate_items.splice(index, 1);
    } else {
        toast.error("At least one row is required.");
    }
};

const totalGST = computed(() => {
    if (!form.value.accepted) return 0;
    return form.value.estimate_items.reduce((sum, item) => {
        const qty = parseFloat(item.quantity) || 0;
        const prc = parseFloat(item.price) || 0;
        const sgst = parseFloat(item.sgst) || 0;
        const cgst = parseFloat(item.cgst) || 0;
        const baseAmount = qty * prc;
        return sum + (baseAmount * (sgst + cgst) / 100);
    }, 0);
});

const totalAmount = computed(() => {
    return form.value.estimate_items.reduce((sum, item) => {
        const qty = parseFloat(item.quantity) || 0;
        const prc = parseFloat(item.price) || 0;
        return sum + (qty * prc);
    }, 0);
});

const grandTotal = computed(() => {
    let total = totalAmount.value + totalGST.value;
    total -= form.value.discount || 0;
    return Math.max(0, total);
});

const submitForm = async () => {
  const { customer_id, estimate_items } = form.value;

  if (!customer_id) {
      toast.error("Please select a customer.");
      return;
  }

  for (let i = 0; i < estimate_items.length; i++) {
      const item = estimate_items[i];
      if (!item.product_id) {
          toast.error(`Item ${i + 1}: Product is required.`);
          return;
      }
      if (parseFloat(item.quantity) <= 0) {
          toast.error(`Item ${i + 1}: Quantity must be greater than 0.`);
          return;
      }
      if (parseFloat(item.price) < 0) {
          toast.error(`Item ${i + 1}: Price cannot be negative.`);
          return;
      }
  }

  try {
    const rate = parseFloat(form.value.exchange_rate) || 1.0;
    const payload = {
      ...form.value,
      exchange_rate: rate,
      discount: (parseFloat(form.value.discount) || 0) * rate,
      grand_total: grandTotal.value * rate,
      total_amount: totalAmount.value * rate,
      GstAmount: totalGST.value * rate,
      estimate_items: form.value.estimate_items.map(item => ({
        ...item,
        price: (parseFloat(item.price) || 0) * rate,
        baseAmount: (parseFloat(item.baseAmount) || 0) * rate
      }))
    };

    const response = await axios.post(`/estimate/update/${estimate.id}`, payload);
    toast.success(response.data.message || "Quotation updated successfully!");

    // Redirect to list page
    router.visit(route('estimate.index'));
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred while saving the quotation.";
    toast.error(errorMessage);
  }
};
const showDescriptionPopup = ref(false);
const activeDescriptionIndex = ref(null);
const tempDescription = ref("");
const descriptionTextarea = ref(null);

const openDescriptionPopup = (index) => {
    activeDescriptionIndex.value = index;
    const items = form.value.estimate_items;
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
            const items = form.value.estimate_items;
            if (items[index] && items[index].alternate_unit_type) {
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
    const items = form.value.estimate_items;
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
    <Head title="Edit Estimate">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class text-sm">
            <a :href="route('estimate.index')" class="text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <i class="bi bi-chevron-left"></i><span>Back to Quotations</span>
            </a>
        </div>

        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-[#292688]">Edit Quotation / Estimate</h2>
            <span class="px-3 py-1 bg-[#2e2c92] text-white rounded-lg text-sm font-semibold">{{ estimate.estimate_no }}</span>
        </div>

        <div class="space-y-6">
            <!-- Customer and Dates Selection -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Customer <span class="text-red-500">*</span></label>
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
                            <div class="px-3 py-2 text-gray-500 text-sm flex items-center justify-between">
                                <span v-if="!customerSearchQuery">Type to search customer...</span>
                                <span v-else>No customers found.</span>
                                <button
                                    @click.stop="showCustomerModal = true"
                                    :class="customerSearchQuery
                                                ? 'mt-2 inline-flex items-center text-blue-600 text-sm font-semibold border border-blue-300 rounded-lg px-3 py-1.5'
                                                : 'mt-2 inline-flex items-center text-blue-600 text-sm font-semibold'"
                                        >
                                    ➕ Add New Customer
                                </button>
                            </div>
                        </template>
                    </vSelect>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Estimate Date <span class="text-red-500">*</span></label>
                    <input type="date" v-model="form.estimate_date"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-2 bg-white text-black border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Expiry Date</label>
                    <input type="date" v-model="form.expiry_date"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-2 bg-white text-black border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
            </div>

            <!-- Selected Customer Details -->
            <div v-if="selectedCustomer" class="p-4 border border-gray-200 rounded-lg bg-gray-50 text-sm text-gray-700 grid grid-cols-1 md:grid-cols-3 gap-4">
                <p><strong>Phone:</strong> {{ selectedCustomer.phone }}</p>
                <p><strong>Email:</strong> {{ selectedCustomer.email }}</p>
                <p><strong>Address:</strong> {{ selectedCustomer.address || '-' }}</p>
            </div>

            <div v-if="isInternationalCustomer" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4 p-4 bg-purple-50/30 border border-purple-100 rounded-xl">
                <div>
                    <label class="block text-black font-semibold mb-2">Currency</label>
                    <select v-model="form.currency" class="w-full border border-gray-300 px-3 py-2 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition bg-white text-black">
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
                    <label class="block text-black font-semibold mb-2">Exchange Rate (1 {{ form.currency }} = ? INR)</label>
                    <input type="number" v-model="form.exchange_rate" step="any" min="0.0001" class="w-full border border-gray-300 px-3 py-2 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition bg-white text-black" />
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="mt-6">
                <h3 class="text-lg font-bold mb-3 text-[#292688]">Quotation Items</h3>

                <!-- Desktop view: Table layout -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full table-auto border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-[#292688] text-white text-sm">
                            <tr>
                                <th class="px-4 py-3 text-left w-1/3">Product <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3 text-left">GST</th>
                                <th class="px-4 py-3 text-left w-48">Alt Qty / Size</th>
                                <th class="px-4 py-3 text-left w-20">Qty <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3 text-left">Unit</th>
                                <th class="px-4 py-3 text-left w-32">Price <span class="text-red-500">*</span></th>
                                <th class="px-4 py-3 text-left">Net Amount</th>
                                <th class="px-4 py-3 text-center w-40">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <tr v-for="(item, index) in form.estimate_items" :key="index" class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="px-4 py-3">
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
                                            class="w-full border border-gray-300 px-3 py-2 rounded-md focus:ring-2 focus:ring-[#292688] text-black bg-white focus:outline-none"
                                        />
                                        <button 
                                            v-if="!item.product_id"
                                            type="button"
                                            @click.stop="openProductModal(index, item.temp_product_name)"
                                            class="absolute right-2 top-2.5 text-[#2E2C92] hover:text-[#1b1959] text-xs font-semibold"
                                            title="Add New Product"
                                        >
                                            ➕ Add
                                        </button>
                                    </div>
                                    <div v-if="page.props.auth?.user?.allow_provide_additional_descriptions && item.description" class="mt-1">
                                        <div class="text-xxs text-gray-500 bg-gray-50 px-2 py-1 rounded border border-gray-100 flex items-center justify-between">
                                            <span class="truncate max-w-[200px]" :title="item.description">{{ item.description }}</span>
                                            <button type="button" @click="openDescriptionPopup(index)" class="text-indigo-600 hover:text-indigo-800 ml-1 shrink-0">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 min-w-[140px]">
                                    <select
                                        v-model="item.gst_rate_id"
                                        @change="onGstRateChange(item)"
                                        @keydown.enter.prevent="moveToNextInput"
                                        class="w-full border border-gray-300 px-2 py-1.5 rounded-md focus:ring-2 focus:ring-[#292688]"
                                    >
                                        <option value="" disabled>Select GST</option>
                                        <option v-for="rate in filteredGstRates" :key="rate.id" :value="rate.id">
                                            {{ rate.name }}
                                        </option>
                                    </select>
                                </td>

                                <td class="px-4 py-3 min-w-[150px]">
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

                                <td class="px-4 py-3">
                                     <input :id="'qty-input-' + index" type="number" v-model="item.quantity" min="1" required
                                         @keydown.enter.prevent="moveToNextInput"
                                         class="w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-center" />
                                 </td>

                                <td class="px-4 py-3 text-gray-600">
                                    {{ item.unit_type || '-' }}
                                </td>

                                <td class="px-4 py-3">
                                    <input type="number" step="0.01" v-model="item.price" min="0" required
                                        @keydown.enter.prevent="moveToNextInput"
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-right" />
                                </td>

                                <td class="px-4 py-3 font-semibold text-gray-800 text-right">
                                    {{ currencySymbol }} {{ ((parseFloat(item.quantity || 0) * parseFloat(item.price || 0))).toFixed(2) }}
                                </td>

                                <td class="px-4 py-3 text-center space-x-1">
                                    <button @click="removeRow(index)" type="button"
                                        class="bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded transition font-medium text-xs border border-red-200">
                                        <i class="bi bi-trash-fill"></i>
                                    </button>
                                    <button v-if="index === form.estimate_items.length - 1" @click="addRow" type="button"
                                        class="bg-green-50 hover:bg-green-100 text-green-600 px-3 py-1.5 rounded transition font-medium text-xs border border-green-200">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile view: Card list of items -->
                <div class="md:hidden space-y-4">
                    <div v-for="(item, index) in form.estimate_items" :key="'mobile-' + index" class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-4">
                        <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                            <span class="font-bold text-sm text-[#292688]">Item #{{ index + 1 }}</span>
                            <button @click="removeRow(index)" type="button" class="text-red-600 hover:text-red-800 text-sm font-semibold flex items-center gap-1">
                                <i class="fa fa-trash"></i> Remove
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Product</label>
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
                                            placeholder="Search product"
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

                            <div class="grid grid-cols-3 gap-2">
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

                             <div v-if="item.alternate_unit_type" class="grid grid-cols-3 gap-2 bg-gray-100 p-2 rounded-lg border border-gray-200">
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
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Quantity</label>
                                    <input type="number" v-model="item.quantity" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm text-center" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-500 mb-1">Price</label>
                                    <input type="number" step="0.01" v-model="item.price" min="0" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm text-right" />
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2 bg-white p-3 rounded-lg border border-gray-100 text-xs text-gray-500 font-semibold">
                                <div>
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
                                    <span class="text-gray-800 font-semibold">{{ item.unit_type || '-' }}</span>
                                </div>
                                <div>
                                    <span class="block text-gray-400">Net Amount</span>
                                    <span class="text-gray-800 font-bold">{{ currencySymbol }} {{ ((parseFloat(item.quantity || 0) * parseFloat(item.price || 0))).toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button @click="addRow" type="button"
                        class="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl font-semibold transition shadow-sm">
                        <i class="fa fa-plus-circle"></i> Add Item
                    </button>
                </div>
            </div>

            <!-- Tax, Discount, Notes, and Summary Block -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 border-t border-gray-200 pt-6">
                <!-- Notes and GST Config -->
                <div class="space-y-4">
                    <div v-if="!isInternationalCustomer" class="mb-4">
                        <label class="inline-flex items-center space-x-2.5 cursor-pointer">
                            <input type="checkbox" v-model="form.accepted" class="form-checkbox h-5 w-5 text-[#292688] border-gray-300 rounded focus:ring-[#292688]" />
                            <span class="text-sm font-medium text-gray-800">Apply GST Taxes to Estimate</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-1.5">Discount ({{ currencySymbol }})</label>
                        <input type="number" step="0.01" v-model="form.discount" min="0"
                            class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Discount amount" />
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-1.5">Notes & Remarks</label>
                        <textarea v-model="form.notes" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                            placeholder="Add terms, shipping info or private notes..."></textarea>
                    </div>
                </div>

                <!-- Totals Calculation Box -->
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-100 space-y-3 max-w-md ml-auto w-full">
                    <h3 class="text-lg font-bold text-gray-800 border-b border-gray-200 pb-2">Calculation Summary</h3>

                    <div class="flex justify-between items-center text-sm text-gray-600">
                        <span>Total Net (Subtotal):</span>
                        <span class="font-medium">{{ currencySymbol }} {{ totalAmount.toFixed(2) }}</span>
                    </div>

                    <div v-if="form.accepted" class="flex justify-between items-center text-sm text-gray-600">
                        <span>Total GST Amount:</span>
                        <span class="font-medium text-blue-600">{{ currencySymbol }} {{ totalGST.toFixed(2) }}</span>
                    </div>

                    <div v-if="form.discount > 0" class="flex justify-between items-center text-sm text-gray-600">
                        <span>Discount Deducted:</span>
                        <span class="font-medium text-red-600">- {{ currencySymbol }} {{ parseFloat(form.discount || 0).toFixed(2) }}</span>
                    </div>

                    <div class="flex justify-between items-center text-base font-bold text-gray-900 border-t border-gray-200 pt-3">
                        <span>Estimated Grand Total:</span>
                        <span class="text-lg text-[#292688]">{{ currencySymbol }} {{ grandTotal.toFixed(2) }}</span>
                    </div>

                    <div class="pt-4 flex gap-3">
                        <button @click="submitForm" class="flex-1 bg-[#2E2C92] hover:bg-[#201e6a] text-white py-2.5 rounded-lg font-semibold shadow-md transition duration-200 text-center cursor-pointer">
                            Save Changes
                        </button>
                        <a :href="route('estimate.index')" class="px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 font-semibold text-gray-700 transition duration-200 text-center text-sm">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Add Customer Modal -->
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                    <input type="text" v-model="newCustomer.phone" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" v-model="newCustomer.email" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
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
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow-md transition cursor-pointer"
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
