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

    <Card>
      <template #title>
        <div class="card-header">
          <span>{{ $t('kategoriMenu.menuCategories') }}</span>
          <Button :label="$t('kategoriMenu.addCategory')" icon="pi pi-plus" @click="openDialog()"
                  :style="{ backgroundColor: 'var(--sage-primary)', borderColor: 'var(--sage-primary)' }" />
        </div>
      </template>
      <template #content>
        <DataTable :value="categories" :loading="loading" stripedRows showGridlines>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-list" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
          <Column field="nama" :header="$t('kategoriMenu.categoryName')" sortable />
          <Column field="deskripsi" :header="$t('kategoriMenu.description')" />
          <Column field="urutan" :header="$t('kategoriMenu.order')" sortable style="width: 100px" />
          <Column field="menu_count" :header="$t('kategoriMenu.menuCount')" sortable style="width: 120px" />
          <Column field="is_active" :header="$t('common.status')" sortable style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.is_active ? $t('common.active') : $t('common.inactive')"
                   :severity="data.is_active ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 120px">
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
            :header="isEdit ? $t('kategoriMenu.editCategory') : $t('kategoriMenu.addCategory')"
            :modal="true" :style="{ width: '500px' }">
      <div class="dialog-content">
        <div class="field">
          <label>{{ $t('kategoriMenu.categoryName') }} *</label>
          <InputText v-model="form.nama" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('kategoriMenu.description') }}</label>
          <Textarea v-model="form.deskripsi" rows="3" style="width: 100%" />
        </div>
        <div class="field">
          <label>{{ $t('kategoriMenu.order') }}</label>
          <InputNumber v-model="form.urutan" style="width: 100%" />
        </div>
        <div class="field">
          <div class="flex align-items-center gap-2">
            <Checkbox v-model="form.is_active" :binary="true" inputId="is_active" />
            <label for="is_active">{{ $t('common.active') }}</label>
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
import { useRoute } from 'vue-router'
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
import InputNumber from 'primevue/inputnumber'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const breadcrumbHome = ref({ icon: 'pi pi-home', to: '/dashboard' })
const breadcrumbItems = computed(() => [
  { label: t('menu.outletManagement'), to: '/outlets' },
  { label: outlet.value?.name || '...', to: `/outlets/${outletId}/dashboard` },
  { label: t('kategoriMenu.menuCategories') }
])

const outlet = ref(null)
const categories = ref([])
const loading = ref(false)
const dialogVisible = ref(false)
const saving = ref(false)
const isEdit = ref(false)

const form = ref({ nama: '', deskripsi: '', urutan: 0, is_active: true })

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
    const response = await api.get(`/outlets/${outletId}/kategori-menu`)
    categories.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Failed to fetch categories', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openDialog = (category = null) => {
  isEdit.value = !!category
  form.value = category ? { ...category } : { nama: '', deskripsi: '', urutan: 0, is_active: true }
  dialogVisible.value = true
}

const saveCategory = async () => {
  saving.value = true
  try {
    if (isEdit.value) {
      await api.put(`/outlets/${outletId}/kategori-menu/${form.value.id}`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.updatedSuccessfully'), life: 3000 })
    } else {
      await api.post(`/outlets/${outletId}/kategori-menu`, form.value)
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.createdSuccessfully'), life: 3000 })
    }
    dialogVisible.value = false
    fetchCategories()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to save', life: 3000 })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (category) => {
  confirm.require({
    message: t('messages.confirmDelete', { item: category.nama }),
    header: t('kategoriMenu.deleteCategory'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => deleteCategory(category.id)
  })
}

const deleteCategory = async (id) => {
  try {
    await api.delete(`/outlets/${outletId}/kategori-menu/${id}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('messages.deletedSuccessfully'), life: 3000 })
    fetchCategories()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.error'), life: 3000 })
  }
}

onMounted(() => {
  fetchOutlet()
  fetchCategories()
})
</script>

<style scoped>
.view-container { max-width: 1400px; margin: 0 auto; }
.card-header { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.action-buttons { display: flex; gap: 0.25rem; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 3rem; color: #6b7280; }
.dialog-content { display: flex; flex-direction: column; gap: 1.5rem; padding: 1rem 0; }
.field { display: flex; flex-direction: column; gap: 0.5rem; }
.field label { font-weight: 600; color: #374151; }
</style>
