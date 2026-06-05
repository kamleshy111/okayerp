<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';


defineProps({
  categories: {
        type: Array
    }
})

// Column definitions for DataTable
const columns = [
//   { data: 'id', title: 'S No' },
    { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'name' },
    { data: 'description'},
    {
        title: 'Actions',
        data: null,
        render: (data, type, row) => {
            return `
            <div class="icon-all-dflex">
              <a  href="category/${data.id}/edit" class="btn btn-light action-btn"><i class="fa fa-edit"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
            </div>
            `;
        }
    }
];

// Attach event when component is mounted
onMounted(() => {
  setupDeleteButton()
});

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const categoryId = button.dataset.id;
      deleteCategory(categoryId);
    }
  });
}

function deleteCategory(categoryId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this category?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/category/destroy/${categoryId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your category has been deleted.', 'success');
          location.reload(); // Reload or re-fetch the data if needed
        })
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the category. Please try again.', 'error');
        });
    }
  });
}
</script>

<template>

  <Head title="Categories" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Categories</h1>
        <div class="flex items-center gap-4">
          <a :href="route('category.Create')"
              class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium">
              <span>+ Add Category</span>
          </a>
        </div>
      </div>
      <div class="overflow-x-auto mt-10">
        <!-- DataTable Component -->
        <DataTable :data="categories" :columns="columns" id="category">
            <thead class="bg-[#2e2c92] text-white main-head-table">
                <tr>
                    <th scope="col">S No</th>
                    <th scope="col">Name</th>
                    <th scope="col">description</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <!-- The table rows would be dynamically inserted here -->
        </DataTable>
      </div>
    </div> 
     
  </AuthenticatedLayout>
</template>