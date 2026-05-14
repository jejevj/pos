<template>
  <div class="view-container">
    <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="mb-4">
      <template #item="{ item, props }">
        <router-link v-if="item.to" v-slot="{ href, navigate }" :to="item.to" custom>
          <a :href="href" v-bind="props.action" @click="navigate">
            <span v-if="item.icon" :class="item.icon"></span>
            <span v-if="item.label">{{ item.label }}</span>
          </a>
        </router-link>
        <span v-else>{{ item.label }}</span>
      </template>
    </Breadcrumb>

    <div v-if="isProcessing" class="loading-overlay">
      <div class="loading-content">
        <ProgressSpinner style="width: 60px; height: 60px" strokeWidth="4" animationDuration="1s" />
        <p class="loading-text">{{ $t('common.loading') }}</p>
      </div>
    </div>

    <Card>
      <template #title>
        <div class="card-header">
          <span>{{ $t('bahanBaku.categories') }}</span>
          <Button :label="$t('bahanBaku.addCategory')" icon="pi pi-plus" @click="openDialog()"
                  :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
        </div>
      </template>
      <template #content>
        <DataTable :value="filteredCategories" :loading="loading" paginator :rows="10"
                   :rowsPerPageOptions="[5, 10, 20, 50]" stripedRows showGridlines>
          <template #header>
            <div class="filter-bar">
              <div class="filter-group">
                <label class="filter-label"><i class="pi pi-search" /> {{ $t('common.search') }}</label>
                <InputText v-model="searchQuery" :placeholder="$t('common.search') + '...'" class="filter-input" />
              </div>
            </div>
          </template>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-tags" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
          <Column field="nama" :header="$t('bahanBaku.categoryName')" sortable />
          <Column field="deskripsi" :header="$t('bahanBaku.categoryDescription')" />
          <Column field="bahan_baku_count" :header="$t('bahanBaku.materials')" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.bahan_baku_count || 0" severity="info" />
            </template>
          </Column>
          <Column field="is_active" :header="$t('bahanBaku.categoryActive')" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.is_active ? $t('common.yes') : $t('common.no')"
                   :severity="data.is_active ? 'success' : 'danger'" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 100px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button icon="pi pi-pencil" text rounded severity="info" @click="openDialog(data)" v-tooltip.top="$t('common.edit')" />
                <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" v-tooltip.top="$t('common.delete')" />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="dialogVisible"
            :header="isEdit ? $t('bahanBaku.editCategory') : $t('bahanBaku.addCategory')"
            :modal="true" :style="{ width: '450px' }">
      <div class="dialog-content">
        <div class="field">
          <label for="nama">{{ $t('bahanBaku.categoryName') }}</label>
          <InputText id="nama" v-model="form.nama" style="width: 100%" />
        </div>
        <div class="field">
          <label for="deskripsi">{{ $t('bahanBaku.categoryDescription') }}</label>
          <Textarea id="deskripsi" v-model="form.deskripsi" rows="3" style="width: 100%" />
        </div>
        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active">{{ $t('bahanBaku.categoryActive') }}</label>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="dialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveCategory" :loading="saving"
                :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Card from 'primevue/card'
import Breadcrumb from 'primevue/breadcrumb'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Checkbox from 'primevue/checkbox'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import ProgressSpinner from 'primevue/progressspinner'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId
const outlet = ref(null)
const categories = ref([])
const searchQuery = ref('')
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialogVisible = ref(false)
const isEdit = ref(false)

const isProcessing = computed(() => saving.value || deleting.value)

const form = ref({ nama: '', deskripsi: '', is_active: true })

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('bahanBaku.categories') }
])

const filteredCategories = computed(() => {
  if (!searchQuery.value) return categories.value
  const q = searchQuery.value.toLowerCase()
  return categories.value.filter(c =>
    c.nama?.toLowerCase().includes(q) || c.deskripsi?.toLowerCase().includes(q)
  )
})

const fetchOutlet = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    outlet.value = response.data
  } catch (error) {
    console.error('Failed to fetch outlet:', error)
  }
}

const fetchCategories = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/kategori-bahan-baku`)
    categories.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: t('messages.error'), life: 3000 })
  } finally {
    loading.value = false
  }
}

const openDialog = (category = null) => {
  isEdit.value = !!category
  form.value = category ? { ...category } : { nama: '', deskripsi: '', is_active: true }
  dialogVisible.value = true
}

const saveCategory = async () => {
  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/kategori-bahan-baku/${form.value.id}`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.updatedSuccessfully'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/kategori-bahan-baku`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.createdSuccessfully'), life: 3000 })
    }
    dialogVisible.value = false
    fetchCategories()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (category) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: category.nama }),
    header: t('bahanBaku.deleteCategory'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteCategory(category.id)
  })
}

const deleteCategory = async (id) => {
  deleting.value = true
  try {
    await api.delete(`/outlets/${outletId}/kategori-bahan-baku/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
    fetchCategories()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  } finally {
    deleting.value = false
  }
}

onMounted(() => {
  fetchOutlet()
  fetchCategories()
})
</script>

<style scoped>
.view-container { max-width: 1400px; margin: 0 auto; }

.loading-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  backdrop-filter: blur(4px);
}

.loading-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 2rem;
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.loading-text { color: #1f2937; font-size: 1rem; font-weight: 600; margin: 0; }

.card-header { display: flex; justify-content: space-between; align-items: center; width: 100%; }

.filter-bar { display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
.filter-group { display: flex; flex-direction: column; gap: 0.35rem; }
.filter-label { font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; display: flex; align-items: center; gap: 0.3rem; }
.filter-input { width: 220px; }

.action-buttons { display: flex; gap: 0.25rem; }

.empty-state {
  display: flex; flex-direction: column; align-items: center;
  gap: 1rem; padding: 3rem; color: #6b7280;
}

.dialog-content { display: flex; flex-direction: column; gap: 1.5rem; padding: 1rem 0; }

.field { display: flex; flex-direction: column; gap: 0.5rem; }

.field label { font-weight: 600; color: #374151; }
</style>
