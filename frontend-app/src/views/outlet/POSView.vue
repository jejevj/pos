<template>
  <div class="pos-container">
    <!-- Header -->
    <div class="pos-header">
      <div>
        <h2>{{ outlet?.name }}</h2>
        <p class="text-muted">{{ $t('pos.title') }}</p>
      </div>
      <div style="display:flex;gap:0.5rem;align-items:center;">
        <Button v-if="tables.some(t => t.status === 'occupied')"
                icon="pi pi-broom" :label="$t('pos.cleanupTable')" outlined size="small"
                severity="warning" @click="openCleanupDialog" />
        <Button icon="pi pi-print" :label="$t('printer.configure')" outlined size="small"
                :severity="printer.settings.value.configured ? 'success' : 'secondary'"
                @click="printerSettingsVisible = true" />
        <Button :label="$t('common.back')" icon="pi pi-arrow-left" text @click="router.push(`/outlets/${outletId}/dashboard`)" />
      </div>
    </div>

    <div class="pos-content">
      <!-- Left: Menu List -->
      <div class="menu-section">
        <div class="menu-header">
          <IconField>
            <InputIcon><i class="pi pi-search" /></InputIcon>
            <InputText v-model="searchQuery" :placeholder="$t('pos.searchMenu')" fluid />
          </IconField>
          
          <!-- Category Filter -->
          <div class="category-filter">
            <Button :label="$t('pos.allCategories')" :outlined="selectedCategory !== null" 
                    @click="selectedCategory = null" size="small" />
            <Button v-for="category in categories" :key="category.id" 
                    :label="category.nama" :outlined="selectedCategory !== category.id"
                    @click="selectedCategory = category.id" size="small" />
          </div>
        </div>
        
        <div class="menu-grid">
          <div v-for="menu in filteredMenus" :key="menu.id" class="menu-card" @click="addToCart(menu)">
            <div class="menu-image">
              <img v-if="menu.image_url" :src="menu.image_url" :alt="menu.nama" />
              <div v-else class="menu-initials">{{ getInitials(menu.nama) }}</div>
            </div>
            <div class="menu-info">
              <div class="menu-name">{{ menu.nama }}</div>
              <div class="menu-price">Rp {{ formatNumber(menu.harga_jual) }}</div>
              <Tag v-if="menu.available_quantity > 0" :value="`${menu.available_quantity} ${$t('pos.available')}`" severity="success" size="small" />
              <Tag v-else :value="$t('pos.outOfStock')" severity="danger" size="small" />
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Cart & Order -->
      <div class="cart-section">

        <!-- Order Type Selection -->
        <div v-if="!currentOrder" class="order-type-selection">
          <h3>{{ $t('pos.newOrder') }}</h3>
          <div class="order-type-buttons">
            <Button :label="$t('pos.dineIn')" icon="pi pi-home" @click="startOrder('dine_in')" class="p-button-lg" />
            <Button :label="$t('pos.takeaway')" icon="pi pi-shopping-bag" @click="startOrder('takeaway')" class="p-button-lg" />
            <Button label="Daftar Bon" icon="pi pi-receipt" severity="warning" outlined
                    @click="showBonList = true" class="p-button-lg" />
            <Button :label="$t('pos.publicOrders.title')" icon="pi pi-qrcode"
                    :severity="pendingPublicOrders.length > 0 ? 'help' : 'secondary'"
                    :badge="pendingPublicOrders.length > 0 ? String(pendingPublicOrders.length) : null"
                    outlined @click="showPublicOrdersList = true" class="p-button-lg" />
          </div>
        </div>

        <!-- Current Order -->
        <div v-else class="current-order">
          <div class="order-header">
            <div>
              <h3>{{ $t('pos.currentOrder') }}</h3>
              <p class="text-muted">{{ currentOrder.kode || 'Draft' }}</p>
            </div>
            <Button icon="pi pi-times" text rounded severity="danger" @click="confirmCancelOrder" />
          </div>

          <!-- Order Info -->
          <div class="order-info">
            <div class="info-row">
              <span>{{ $t('pos.orderType') }}:</span>
              <span><strong>{{ getOrderTypeLabel(currentOrder.order_type) }}</strong></span>
            </div>
            <div v-if="currentOrder.order_type === 'dine_in'" class="info-row">
              <span>{{ $t('pos.table') }}:</span>
              <Select v-model="currentOrder.table_id" :options="availableTables" optionLabel="table_number" 
                      optionValue="id" :placeholder="$t('pos.selectTable')" fluid />
            </div>
            <!-- Member Selection -->
            <div class="info-row member-row">
              <span>{{ $t('member.member') }}:</span>
              <div class="member-select-wrapper">
                <div v-if="selectedMember" class="selected-member">
                  <div class="member-info-compact">
                    <span class="member-name">{{ selectedMember.nama }}</span>
                    <Tag :value="selectedMember.tier" :severity="getTierSeverity(selectedMember.tier)" size="small" />
                    <span class="member-points">{{ selectedMember.points }} pts</span>
                  </div>
                  <Button icon="pi pi-times" text rounded size="small" severity="danger" @click="removeMember" />
                </div>
                <div v-else class="member-search">
                  <IconField>
                    <InputIcon><i class="pi pi-search" /></InputIcon>
                    <InputText v-model="memberSearchQuery" :placeholder="$t('member.searchMember')" 
                               @input="onMemberSearch" fluid size="small" />
                  </IconField>
                  <div v-if="memberSearchResults.length > 0" class="member-dropdown">
                    <div v-for="member in memberSearchResults" :key="member.id" 
                         class="member-dropdown-item" @click="selectMember(member)">
                      <div class="member-dropdown-name">{{ member.nama }}</div>
                      <div class="member-dropdown-meta">
                        <Tag :value="member.tier" :severity="getTierSeverity(member.tier)" size="small" />
                        <span>{{ member.points }} pts</span>
                        <span class="member-phone">{{ member.phone }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Cart Items -->
          <div class="cart-items">
            <div v-if="cartItems.length === 0" class="empty-cart">
              <i class="pi pi-shopping-cart" style="font-size: 3rem; color: var(--p-text-muted-color, #9ca3af);"></i>
              <p>{{ $t('pos.emptyCart') }}</p>
            </div>
            <div v-else>
              <div v-for="(item, index) in cartItems" :key="index" class="cart-item">
                <div class="item-row">
                  <div class="item-info">
                    <div class="item-name">{{ item.menu_name }}</div>
                    <div class="item-price">Rp {{ formatNumber(item.menu_price) }}</div>
                  </div>
                  <div class="item-controls">
                    <Button icon="pi pi-trash" text rounded size="small" severity="danger" 
                            @click="removeItem(index)" v-tooltip.top="$t('common.delete')" />
                    <InputNumber v-model="item.quantity" :min="1" :max="99" 
                                 style="width: 80px" />
                  </div>
                  <div class="item-subtotal">Rp {{ formatNumber(item.subtotal) }}</div>
                </div>
                <div class="item-notes-row">
                  <i class="pi pi-comment item-notes-icon"></i>
                  <InputText v-model="item.notes" :placeholder="$t('pos.itemNotesPlaceholder')"
                             class="item-notes-input" size="small" />
                </div>
              </div>
            </div>
          </div>

          <!-- Promo Section -->
          <div v-if="cartItems.length > 0" class="promo-section">
            <label class="promo-label">{{ $t('pos.selectPromo') }}</label>
            <div class="promo-select-wrapper">
              <MultiSelect v-model="selectedPromos" :options="applicablePromos" optionLabel="nama" 
                          :placeholder="$t('pos.choosePromo')" fluid display="chip" @change="onPromoSelect">
                <template #option="slotProps">
                  <div class="promo-option">
                    <div class="promo-option-header">
                      <Tag v-if="slotProps.option.tipe === 'percentage'" 
                           :value="`${slotProps.option.nilai}%`" severity="info" size="small" />
                      <Tag v-else :value="`Rp ${formatNumber(slotProps.option.nilai)}`" 
                           severity="success" size="small" />
                      <span class="promo-option-name">{{ slotProps.option.nama }}</span>
                      <Tag v-if="slotProps.option.is_stackable" value="Stackable" severity="warn" size="small" />
                    </div>
                    <div class="promo-option-desc">{{ slotProps.option.deskripsi }}</div>
                    <div class="promo-option-code">{{ $t('promo.code') }}: {{ slotProps.option.kode }}</div>
                    <div class="promo-option-details">
                      <span v-if="slotProps.option.minimum_pembelian > 0" class="promo-option-min">
                        Min: Rp {{ formatNumber(slotProps.option.minimum_pembelian) }}
                      </span>
                      <span v-if="slotProps.option.jam_mulai && slotProps.option.jam_selesai" class="promo-option-time">
                        ⏰ {{ slotProps.option.jam_mulai.substring(0,5) }} - {{ slotProps.option.jam_selesai.substring(0,5) }}
                      </span>
                    </div>
                  </div>
                </template>
              </MultiSelect>
              <Button v-if="selectedPromos.length > 0" icon="pi pi-times" text rounded severity="danger" 
                      @click="removeAllPromos" v-tooltip.top="$t('pos.removeAllPromos')" />
            </div>
            <div v-if="applicablePromos.length === 0 && availablePromos.length > 0" class="promo-info">
              <i class="pi pi-info-circle"></i>
              <span>{{ $t('pos.noApplicablePromos') }}</span>
            </div>
            <div v-if="promoError" class="promo-error">
              <i class="pi pi-exclamation-circle"></i>
              <span>{{ promoError }}</span>
            </div>
            <div v-if="selectedPromos.length > 0" class="applied-promos">
              <div v-for="promo in selectedPromos" :key="promo.id" class="applied-promo-item">
                <Tag v-if="promo.tipe === 'percentage'" 
                     :value="`${promo.nilai}%`" severity="info" size="small" />
                <Tag v-else :value="`Rp ${formatNumber(promo.nilai)}`" 
                     severity="success" size="small" />
                <span class="promo-name">{{ promo.nama }}</span>
                <Tag v-if="promo.is_stackable" value="✓" severity="warn" size="small" />
              </div>
            </div>
          </div>

          <!-- Totals -->
          <div class="order-totals">
            <div class="total-row">
              <span>{{ $t('pos.subtotal') }}:</span>
              <span>Rp {{ formatNumber(orderTotals.subtotal) }}</span>
            </div>
            <div v-if="orderTotals.discount > 0" class="total-row discount">
              <span>{{ $t('pos.discount') }}:</span>
              <span>- Rp {{ formatNumber(orderTotals.discount) }}</span>
            </div>
            <div v-if="txSettings.tax_enabled && !txSettings.tax_inclusive" class="total-row">
              <span>{{ txSettings.tax_label }} ({{ txSettings.tax_percentage }}%):</span>
              <span>Rp {{ formatNumber(orderTotals.tax) }}</span>
            </div>
            <div v-if="txSettings.service_charge_enabled" class="total-row">
              <span>{{ txSettings.service_charge_label }} ({{ txSettings.service_charge_percentage }}%):</span>
              <span>Rp {{ formatNumber(orderTotals.serviceCharge) }}</span>
            </div>
            <div v-if="txSettings.tax_enabled && txSettings.tax_inclusive" class="total-row tax-inclusive-note">
              <small><i class="pi pi-info-circle"></i> {{ $t('pos.taxInclusiveNote') || 'Sudah termasuk pajak' }}</small>
            </div>
            <div class="total-row grand">
              <span>{{ $t('pos.total') }}:</span>
              <span>Rp {{ formatNumber(orderTotals.total) }}</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="order-actions">
            <Button :label="$t('pos.clearCart')" icon="pi pi-trash" outlined @click="clearCart" :disabled="cartItems.length === 0" />
            <Button :label="$t('pos.processPayment')" icon="pi pi-check" @click="showPaymentDialog" 
                    :disabled="cartItems.length === 0" severity="success" />
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Dialog -->
    <Dialog v-model:visible="paymentDialogVisible" :header="$t('pos.payment')" modal :style="{ width: '500px' }">
      <div class="payment-form">
        <div class="payment-total">
          <label>{{ $t('pos.total') }}</label>
          <div class="total-amount">Rp {{ formatNumber(orderTotals.total) }}</div>
        </div>

        <!-- Member Points Redemption -->
        <div v-if="selectedMember" class="member-points-section">
          <div class="member-points-header">
            <i class="pi pi-star-fill" style="color: #f59e0b;"></i>
            <span>{{ selectedMember.nama }} — <strong>{{ selectedMember.points }} pts</strong></span>
            <Tag :value="selectedMember.tier" :severity="getTierSeverity(selectedMember.tier)" size="small" />
          </div>
          <div v-if="membershipSettings" class="points-redeem-row">
            <label>{{ $t('member.redeemPoints') }}</label>
            <div class="points-redeem-input">
              <InputNumber v-model="paymentData.redeem_points" :min="0" :max="selectedMember.points" 
                           :step="1" fluid @update:modelValue="onRedeemPointsChange" />
              <span class="points-value">= Rp {{ formatNumber(pointsRedeemValue) }}</span>
            </div>
            <small class="points-hint">
              {{ $t('member.pointsConversion', { rate: membershipSettings.point_conversion_rate }) }}
            </small>
          </div>
        </div>

        <div class="form-field">
          <label>{{ $t('pos.paymentMethod') }} *</label>
          <Select v-model="paymentData.payment_method_id" :options="paymentMethods" optionLabel="name" 
                  optionValue="id" :placeholder="$t('pos.paymentMethod')" fluid />
        </div>

        <div class="form-field">
          <label>{{ $t('pos.amountPaid') }} *</label>
          <InputNumber v-model="paymentData.paid_amount" :minFractionDigits="0" :maxFractionDigits="0" 
                       prefix="Rp " fluid @update:modelValue="calculateChange" />
          
          <!-- Quick Amount Buttons -->
          <div class="quick-amount-buttons">
            <Button v-for="amount in suggestedAmounts" :key="amount" 
                    :label="`Rp ${formatNumber(amount)}`" 
                    outlined size="small"
                    @click="setQuickAmount(amount)" />
          </div>
        </div>

        <div class="payment-change">
          <label>{{ $t('pos.change') }}</label>
          <div class="change-amount" :class="{ negative: paymentData.change < 0 }">
            Rp {{ formatNumber(Math.abs(paymentData.change)) }}
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="paymentDialogVisible = false" />
        <Button :label="$t('pos.confirmPayment')" @click="processPayment" :loading="processing" 
                :disabled="!paymentData.payment_method_id || !paymentData.paid_amount || paymentData.paid_amount < effectiveTotal" />
      </template>
    </Dialog>

    <!-- Table Selection Dialog -->
    <Dialog v-model:visible="tableDialogVisible" :header="$t('pos.selectTable')" modal :style="{ width: '700px' }">
      <div class="area-filter">
        <Button :label="$t('pos.allAreas')" :outlined="selectedArea !== null" 
                @click="selectedArea = null" size="small" />
        <Button :label="$t('table.indoor')" :outlined="selectedArea !== 'indoor'" 
                @click="selectedArea = 'indoor'" size="small" />
        <Button :label="$t('table.outdoor')" :outlined="selectedArea !== 'outdoor'" 
                @click="selectedArea = 'outdoor'" size="small" />
        <Button :label="$t('table.vip')" :outlined="selectedArea !== 'vip'" 
                @click="selectedArea = 'vip'" size="small" />
      </div>
      <div class="table-grid">
        <div v-for="table in availableTables" :key="table.id" class="table-card" 
             :class="{ occupied: table.status !== 'available' }"
             @click="selectTable(table)">
          <div class="table-number">{{ table.table_number }}</div>
          <div class="table-status">
            <Tag :value="getTableStatusLabel(table.status)" :severity="getTableStatusSeverity(table.status)" />
          </div>
          <div class="table-capacity">{{ table.capacity }} {{ $t('common.seats') }}</div>
          <div class="table-area">
            <Tag :value="getAreaLabel(table.area)" severity="info" size="small" />
          </div>
        </div>
      </div>
    </Dialog>

    <!-- Cleanup Table Dialog -->
    <Dialog v-model:visible="showCleanupDialog" :header="$t('pos.cleanupTable')" modal :style="{ width: '700px' }">
      <div class="area-filter">
        <Button :label="$t('pos.allAreas')" :outlined="selectedArea !== null" 
                @click="selectedArea = null" size="small" />
        <Button :label="$t('table.indoor')" :outlined="selectedArea !== 'indoor'" 
                @click="selectedArea = 'indoor'" size="small" />
        <Button :label="$t('table.outdoor')" :outlined="selectedArea !== 'outdoor'" 
                @click="selectedArea = 'outdoor'" size="small" />
        <Button :label="$t('table.vip')" :outlined="selectedArea !== 'vip'" 
                @click="selectedArea = 'vip'" size="small" />
      </div>
      <div class="table-grid">
        <div v-for="table in occupiedTables" :key="table.id" class="table-card occupied"
             @click="cleanupTable(table)">
          <div class="table-number">{{ table.table_number }}</div>
          <div class="table-status">
            <Tag :value="getTableStatusLabel(table.status)" severity="danger" />
          </div>
          <div class="table-capacity">{{ table.capacity }} {{ $t('common.seats') }}</div>
          <div class="table-area">
            <Tag :value="getAreaLabel(table.area)" severity="info" size="small" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.close')" text @click="showCleanupDialog = false" />
      </template>
    </Dialog>

    <!-- Daftar Bon Dialog -->
    <Dialog v-model:visible="showBonList" header="Daftar Bon" :style="{ width: '520px' }" modal>
      <div v-if="bonOrders.length === 0" class="bon-empty">
        <i class="pi pi-check-circle"></i>
        <p>Tidak ada bon yang menunggu pembayaran</p>
      </div>

      <div v-else class="bon-list">
        <div v-for="order in bonOrders" :key="order.id" class="bon-item">
          <div class="bon-item-header">
            <span class="bon-order-code">{{ order.kode || order.order_code }}</span>
            <span class="bon-table">{{ getBonTableLabel(order) }}</span>
            <span class="bon-time">{{ formatBonTime(order.created_at_local || order.created_at) }}</span>
          </div>
          <div class="bon-item-body">
            <span class="bon-items-summary">{{ (order.items && order.items.length) || 0 }} item</span>
            <span class="bon-total">Rp {{ formatNumber(order.total_amount || order.grand_total || 0) }}</span>
          </div>
          <div class="bon-item-actions">
            <Button label="Lunas" icon="pi pi-check" severity="success" size="small"
                    :loading="processingPayment && selectedBonOrder && selectedBonOrder.id === order.id"
                    @click="markBonAsPaid(order)" />
            <Button label="Detail" icon="pi pi-eye" severity="secondary" size="small" outlined
                    @click="viewBonDetail(order)" />
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Tutup" severity="secondary" outlined @click="showBonList = false" />
        <Button label="Refresh" icon="pi pi-refresh" outlined @click="loadBonOrders" />
      </template>
    </Dialog>

    <!-- Public Table Orders (Pending Approval) Dialog -->
    <Dialog v-model:visible="showPublicOrdersList"
            :header="$t('pos.publicOrders.title')"
            :style="{ width: '560px' }" modal>
      <div v-if="pendingPublicOrders.length === 0" class="bon-empty">
        <i class="pi pi-check-circle"></i>
        <p>{{ $t('pos.publicOrders.empty') }}</p>
      </div>

      <div v-else class="bon-list public-pending-list">
        <div v-for="order in pendingPublicOrders" :key="order.id" class="bon-item public-pending-item">
          <div class="bon-item-header">
            <span class="bon-order-code">{{ order.kode }}</span>
            <span class="bon-table"><i class="pi pi-th-large"></i> {{ order.table_number || '-' }}</span>
            <span class="bon-time">{{ formatBonTime(order.created_at) }}</span>
          </div>
          <div class="bon-item-body" style="flex-direction: column; align-items: flex-start; gap: 6px;">
            <div style="font-size: 12px; color: #666;">
              <i class="pi pi-user"></i> {{ order.customer_name || '-' }}
              · <i class="pi pi-phone"></i> {{ order.customer_phone || '-' }}
              <span v-if="order.customer_email"> · {{ order.customer_email }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
              <span class="bon-items-summary">{{ (order.items && order.items.length) || 0 }} item</span>
              <span class="bon-total">Rp {{ formatNumber(order.total_amount || 0) }}</span>
            </div>
            <ul v-if="order.items && order.items.length" style="margin: 4px 0 0; padding-left: 16px; font-size: 12px; color: #444;">
              <li v-for="it in order.items.slice(0, 3)" :key="it.id">
                {{ it.quantity }}× {{ it.menu_name }}
              </li>
              <li v-if="order.items.length > 3" style="color: #888;">
                +{{ order.items.length - 3 }} item lainnya
              </li>
            </ul>
          </div>
          <div class="bon-item-actions">
            <Button :label="$t('pos.publicOrders.approve')" icon="pi pi-check" severity="success" size="small"
                    :loading="processingPublicId === order.id"
                    @click="approvePublicOrder(order)" />
            <Button :label="$t('pos.publicOrders.reject')" icon="pi pi-times" severity="danger" size="small" outlined
                    :loading="processingPublicId === order.id"
                    @click="rejectPublicOrder(order)" />
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.close')" severity="secondary" outlined @click="showPublicOrdersList = false" />
        <Button :label="$t('common.refresh') || 'Refresh'" icon="pi pi-refresh" outlined @click="loadPendingPublicOrders" />
      </template>
    </Dialog>

    <!-- Payment Confirmation Dialog -->
    <Dialog v-model:visible="showPayConfirm" header="Konfirmasi Pembayaran" :style="{ width: '360px' }" modal>
      <div v-if="selectedBonOrder" class="pay-confirm-content">
        <p>Tandai pesanan <strong>{{ selectedBonOrder.kode || selectedBonOrder.order_code }}</strong> sebagai lunas?</p>
        <div class="pay-confirm-total">
          <span>Total:</span>
          <strong>Rp {{ formatNumber(selectedBonOrder.total_amount || selectedBonOrder.grand_total || 0) }}</strong>
        </div>
      </div>
      <template #footer>
        <Button label="Batal" severity="secondary" outlined @click="showPayConfirm = false" />
        <Button label="Lunas" icon="pi pi-check" severity="success"
                :loading="processingPayment" @click="confirmPayment" />
      </template>
    </Dialog>

    <!-- Printer Settings Dialog -->
    <PrinterSettingsDialog v-model="printerSettingsVisible" :printer="printer" />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { decodeOutletId } from '@/utils/outletId'
import { useThermalPrinter } from '@/composables/useThermalPrinter'
import PrinterSettingsDialog from '@/components/PrinterSettingsDialog.vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import InputNumber from 'primevue/inputnumber'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId
const numericOutletId = decodeOutletId(outletId) || outletId

const printer = useThermalPrinter(outletId)
const printerSettingsVisible = ref(false)

const outlet = ref(null)
const menus = ref([])
const categories = ref([])
const tables = ref([])
const paymentMethods = ref([])
const availablePromos = ref([])
const applicablePromos = ref([])
const searchQuery = ref('')
const selectedCategory = ref(null)
const currentOrder = ref(null)
const cartItems = ref([])
const selectedPromo = ref(null)
const selectedPromos = ref([])
const promoError = ref('')
const paymentDialogVisible = ref(false)
const tableDialogVisible = ref(false)
const showCleanupDialog = ref(false)
const processing = ref(false)
const selectedArea = ref(null)
const promoRefreshInterval = ref(null)

// Daftar Bon state
const showBonList = ref(false)
const showPayConfirm = ref(false)
const bonOrders = ref([])
const selectedBonOrder = ref(null)

// Public table orders (pending approval) state
const showPublicOrdersList = ref(false)
const pendingPublicOrders = ref([])
const processingPublicId = ref(null)
const publicOrdersPollTimer = ref(null)
const processingPayment = ref(false)

// Transaction settings (tax/service charge) from outlet config
const txSettings = ref({
  tax_enabled: true,
  tax_percentage: 11,
  tax_label: 'PPN',
  tax_inclusive: false,
  service_charge_enabled: false,
  service_charge_percentage: 0,
  service_charge_label: 'Service Charge',
})

// Member state
const selectedMember = ref(null)
const memberSearchQuery = ref('')
const memberSearchResults = ref([])
const membershipSettings = ref(null)
let memberSearchTimeout = null

const paymentData = ref({
  payment_method_id: null,
  paid_amount: 0,
  change: 0,
  redeem_points: 0
})

const filteredMenus = computed(() => {
  let filtered = menus.value
  
  // Filter by category
  if (selectedCategory.value !== null) {
    filtered = filtered.filter(m => m.kategori_id === selectedCategory.value)
  }
  
  // Filter by search query
  if (searchQuery.value) {
    filtered = filtered.filter(m => 
      m.nama.toLowerCase().includes(searchQuery.value.toLowerCase())
    )
  }
  
  return filtered
})

const availableTables = computed(() => {
  let filtered = tables.value.filter(t => t.status === 'available' && t.is_active)
  if (selectedArea.value) {
    filtered = filtered.filter(t => t.area === selectedArea.value)
  }
  return filtered
})

const occupiedTables = computed(() => {
  let filtered = tables.value.filter(t => t.status === 'occupied')
  if (selectedArea.value) {
    filtered = filtered.filter(t => t.area === selectedArea.value)
  }
  return filtered
})

const orderTotals = computed(() => {
  const subtotal = cartItems.value.reduce((sum, item) => sum + item.subtotal, 0)
  let discount = 0
  
  // Calculate discount from all selected promos
  if (selectedPromos.value.length > 0) {
    for (const promo of selectedPromos.value) {
      if (promo.tipe === 'percentage') {
        // Ensure nilai is a number
        const nilaiPercent = parseFloat(promo.nilai) || 0
        let promoDiscount = subtotal * (nilaiPercent / 100)
        
        // Apply maximum discount if set
        if (promo.maksimum_diskon) {
          const maxDiskon = parseFloat(promo.maksimum_diskon) || 0
          if (promoDiscount > maxDiskon) {
            promoDiscount = maxDiskon
          }
        }
        discount += promoDiscount
      } else if (promo.tipe === 'nominal') {
        // Ensure nilai is a number
        const nilaiNominal = parseFloat(promo.nilai) || 0
        discount += nilaiNominal
      }
    }
  }
  
  // Ensure discount doesn't exceed subtotal
  discount = Math.min(discount, subtotal)
  
  const subtotalAfterDiscount = Math.max(0, subtotal - discount)

  const ts = txSettings.value
  const taxRate = (ts.tax_enabled && !ts.tax_inclusive) ? (parseFloat(ts.tax_percentage) || 0) / 100 : 0
  const tax = subtotalAfterDiscount * taxRate

  const scRate = ts.service_charge_enabled ? (parseFloat(ts.service_charge_percentage) || 0) / 100 : 0
  const serviceCharge = subtotalAfterDiscount * scRate

  const total = subtotalAfterDiscount + tax + serviceCharge

  return { subtotal, discount, tax, serviceCharge, total }
})

const pointsRedeemValue = computed(() => {
  if (!membershipSettings.value || !paymentData.value.redeem_points) return 0
  return membershipSettings.value.point_conversion_rate * paymentData.value.redeem_points / (membershipSettings.value.point_per_rupiah || 1)
})

const effectiveTotal = computed(() => {
  return Math.max(0, orderTotals.value.total - pointsRedeemValue.value)
})

const suggestedAmounts = computed(() => {
  const total = Math.ceil(effectiveTotal.value)
  const amounts = []
  
  // First suggestion: exact amount (rounded up to nearest 1000)
  const roundedTotal = Math.ceil(total / 1000) * 1000
  amounts.push(roundedTotal)
  
  // Second suggestion: next common denomination
  if (roundedTotal < 50000) {
    amounts.push(50000)
  } else if (roundedTotal < 100000) {
    amounts.push(100000)
  } else {
    amounts.push(roundedTotal + 50000)
  }
  
  // Third suggestion: larger denomination
  if (roundedTotal < 20000) {
    amounts.push(100000)
  } else if (roundedTotal < 100000) {
    amounts.push(200000)
  } else {
    amounts.push(roundedTotal + 100000)
  }
  
  // Remove duplicates and sort
  return [...new Set(amounts)].sort((a, b) => a - b).slice(0, 3)
})

const fetchOutlet = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}`)
    outlet.value = response.data
  } catch (error) {
    console.error(error)
  }
}

const fetchMenus = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/menu`)
    menus.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: 'Failed to fetch menus', life: 3000 })
  }
}

const fetchCategories = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/kategori-menu`)
    categories.value = response.data
  } catch (error) {
    console.error('Failed to fetch categories:', error)
  }
}

const fetchTables = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/tables`)
    tables.value = response.data
  } catch (error) {
    console.error(error)
  }
}

const fetchPaymentMethods = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/payment-methods`)
    paymentMethods.value = response.data
  } catch (error) {
    console.error(error)
  }
}

const fetchAvailablePromos = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/promos/available`)
    availablePromos.value = response.data
    
    // Fetch applicable promos based on current subtotal
    await fetchApplicablePromos()
  } catch (error) {
    console.error('Failed to fetch available promos:', error)
  }
}

const fetchApplicablePromos = async () => {
  try {
    const subtotal = cartItems.value.reduce((sum, item) => sum + item.subtotal, 0)
    // Don't send current_datetime - let server use its own timezone
    
    const response = await api.post(`/outlets/${outletId}/promos/applicable`, {
      subtotal,
      member_id: selectedMember.value?.id || null
    })
    applicablePromos.value = response.data
  } catch (error) {
    console.error('Failed to fetch applicable promos:', error)
    applicablePromos.value = []
  }
}

const fetchTxSettings = async () => {
  if (!numericOutletId) return
  try {
    const res = await api.get(`/outlets/${numericOutletId}/transaction-settings`)
    const d = res.data || {}
    txSettings.value = {
      tax_enabled:               d.tax_enabled !== undefined ? Boolean(d.tax_enabled) : true,
      tax_percentage:            parseFloat(d.tax_percentage) || 11,
      tax_label:                 d.tax_label || 'PPN',
      tax_inclusive:             Boolean(d.tax_inclusive),
      service_charge_enabled:    Boolean(d.service_charge_enabled),
      service_charge_percentage: parseFloat(d.service_charge_percentage) || 0,
      service_charge_label:      d.service_charge_label || 'Service Charge',
    }
  } catch (e) {
    console.error('Failed to fetch transaction settings:', e)
  }
}

const fetchMembershipSettings = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/membership-settings`)
    membershipSettings.value = response.data
  } catch (error) {
    console.error('Failed to fetch membership settings:', error)
  }
}

const onMemberSearch = () => {
  clearTimeout(memberSearchTimeout)
  if (!memberSearchQuery.value || memberSearchQuery.value.length < 2) {
    memberSearchResults.value = []
    return
  }
  memberSearchTimeout = setTimeout(async () => {
    try {
      const response = await api.get(`/outlets/${outletId}/members/search`, {
        params: { query: memberSearchQuery.value }
      })
      memberSearchResults.value = response.data
    } catch (error) {
      console.error('Member search failed:', error)
    }
  }, 300)
}

const selectMember = (member) => {
  selectedMember.value = member
  memberSearchQuery.value = ''
  memberSearchResults.value = []
  // Refresh promos with member context
  fetchApplicablePromos()
}

const removeMember = () => {
  selectedMember.value = null
  memberSearchQuery.value = ''
  memberSearchResults.value = []
  fetchApplicablePromos()
}

const getTierSeverity = (tier) => {
  const map = { Silver: 'secondary', Gold: 'warn', Platinum: 'info' }
  return map[tier] || 'secondary'
}

const onRedeemPointsChange = () => {
  calculateChange()
}

const startOrder = (orderType) => {
  selectedArea.value = null // Reset filter
  if (orderType === 'dine_in') {
    tableDialogVisible.value = true
  } else {
    currentOrder.value = {
      order_type: orderType,
      table_id: null,
      table_number: null
    }
  }
}

const selectTable = (table) => {
  if (table.status !== 'available') {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: 'Table is not available', life: 3000 })
    return
  }
  currentOrder.value = {
    order_type: 'dine_in',
    table_id: table.id,
    table_number: table.table_number
  }
  tableDialogVisible.value = false
}

const addToCart = (menu) => {
  if (!currentOrder.value) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('pos.startOrder'), life: 3000 })
    return
  }

  if (menu.available_quantity <= 0) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('pos.outOfStock'), life: 3000 })
    return
  }

  const existingItem = cartItems.value.find(item => item.menu_id === menu.id)
  if (existingItem) {
    existingItem.quantity++
    existingItem.subtotal = existingItem.quantity * existingItem.menu_price
  } else {
    cartItems.value.push({
      menu_id: menu.id,
      menu_name: menu.nama,
      menu_price: menu.harga_jual,
      quantity: 1,
      subtotal: menu.harga_jual,
      notes: null
    })
  }
}

// Watch cart items for quantity changes
watch(cartItems, (newItems) => {
  newItems.forEach((item, index) => {
    if (!item.quantity || item.quantity < 1) {
      item.quantity = 1
    }
    item.subtotal = item.quantity * item.menu_price
  })
}, { deep: true })

// Watch paid amount for change calculation
watch(() => paymentData.value.paid_amount, () => {
  calculateChange()
})

// Watch cart items to auto-remove invalid promos and refresh applicable promos
watch(() => cartItems.value, async () => {
  // Refresh applicable promos when cart changes
  await fetchApplicablePromos()
  
  if (selectedPromos.value.length > 0) {
    // Remove promos that are no longer in applicable list
    const applicableIds = applicablePromos.value.map(p => p.id)
    selectedPromos.value = selectedPromos.value.filter(promo => applicableIds.includes(promo.id))
  }
}, { deep: true })

const updateItemSubtotal = (index) => {
  const item = cartItems.value[index]
  // Ensure quantity is valid
  if (!item.quantity || item.quantity < 1) {
    item.quantity = 1
  }
  item.subtotal = item.quantity * item.menu_price
}

const removeItem = (index) => {
  cartItems.value.splice(index, 1)
}

const clearCart = () => {
  confirm.require({
    message: t('pos.emptyCart') + '?',
    header: t('pos.clearCart'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => {
      cartItems.value = []
      removeAllPromos()
    }
  })
}

const onPromoSelect = async () => {
  if (selectedPromos.value.length === 0) return
  
  promoError.value = ''
  
  // Check for non-stackable promos
  const nonStackablePromos = selectedPromos.value.filter(p => !p.is_stackable)
  
  if (nonStackablePromos.length > 0 && selectedPromos.value.length > 1) {
    // If there's any non-stackable promo and multiple promos selected,
    // keep only the last selected promo
    const lastPromo = selectedPromos.value[selectedPromos.value.length - 1]
    selectedPromos.value = [lastPromo]
    promoError.value = t('pos.nonStackablePromo')
    
    toast.add({ 
      severity: 'warn', 
      summary: t('messages.warning'), 
      detail: t('pos.nonStackablePromo'), 
      life: 3000 
    })
    return
  }
  
  // Validate all selected promos
  const validPromos = []
  
  for (const promo of selectedPromos.value) {
    try {
      const response = await api.post(`/outlets/${outletId}/promos/validate`, {
        kode: promo.kode,
        subtotal: orderTotals.value.subtotal
      })
      validPromos.push(promo)
    } catch (error) {
      promoError.value = error.response?.data?.message || `${promo.nama}: ${t('pos.invalidPromo')}`
      // Remove invalid promo
      selectedPromos.value = selectedPromos.value.filter(p => p.id !== promo.id)
    }
  }
  
  if (validPromos.length > 0) {
    toast.add({ 
      severity: 'success', 
      summary: t('messages.success'), 
      detail: `${validPromos.length} ${t('pos.promosApplied')}`, 
      life: 3000 
    })
  }
}

const removeAllPromos = () => {
  selectedPromos.value = []
  promoError.value = ''
}

const showPaymentDialog = () => {
  if (currentOrder.value.order_type === 'dine_in' && !currentOrder.value.table_id) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('pos.selectTableFirst'), life: 3000 })
    return
  }
  paymentData.value.paid_amount = orderTotals.value.total
  paymentData.value.redeem_points = 0
  calculateChange()
  paymentDialogVisible.value = true
}

const calculateChange = () => {
  paymentData.value.change = paymentData.value.paid_amount - effectiveTotal.value
}

const setQuickAmount = (amount) => {
  paymentData.value.paid_amount = amount
  calculateChange()
}

const processPayment = async () => {
  processing.value = true
  try {
    // Create or update order
    let orderId
    const orderPayload = {
      order_type: currentOrder.value.order_type,
      table_id: currentOrder.value.table_id,
      items: cartItems.value,
      promo_codes: selectedPromos.value.map(p => p.kode),
      member_id: selectedMember.value?.id || null
    }
    
    if (currentOrder.value.id) {
      await api.put(`/outlets/${outletId}/orders/${currentOrder.value.id}`, orderPayload)
      orderId = currentOrder.value.id
    } else {
      const orderResponse = await api.post(`/outlets/${outletId}/orders`, orderPayload)
      orderId = orderResponse.data.data.id
    }

    // Process payment
    await api.post(`/outlets/${outletId}/orders/${orderId}/payment`, {
      payment_method_id: paymentData.value.payment_method_id,
      paid_amount: paymentData.value.paid_amount,
      redeem_points: paymentData.value.redeem_points || 0
    })

    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('pos.paymentSuccess'), life: 3000 })
    
    // Auto print receipt (thermal if configured, fallback to PDF)
    try {
      if (printer.settings.value.configured) {
        const params = { tz: Intl.DateTimeFormat().resolvedOptions().timeZone }
        const thermalResponse = await api.get(`/outlets/${outletId}/orders/${orderId}/thermal-receipt`, { params })
        await printer.print(thermalResponse.data.lines)
      } else {
        // Fallback to PDF
        const receiptResponse = await api.get(`/outlets/${outletId}/orders/${orderId}/receipt`, { responseType: 'blob' })
        const blob = new Blob([receiptResponse.data], { type: 'application/pdf' })
        const blobUrl = URL.createObjectURL(blob)
        const printWindow = window.open(blobUrl, '_blank', 'width=400,height=700')
        if (printWindow) {
          printWindow.onload = () => { printWindow.focus(); printWindow.print() }
          setTimeout(() => URL.revokeObjectURL(blobUrl), 10000)
        }
      }
    } catch (printError) {
      console.error('Print error:', printError)
      toast.add({ severity: 'warn', summary: t('messages.warning'), detail: 'Pembayaran berhasil, tapi gagal print otomatis.', life: 5000 })
    }
    
    // Reset
    currentOrder.value = null
    cartItems.value = []
    removeAllPromos()
    selectedMember.value = null
    memberSearchQuery.value = ''
    paymentData.value = { payment_method_id: null, paid_amount: 0, change: 0, redeem_points: 0 }
    paymentDialogVisible.value = false
    fetchTables()
    fetchMenus()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Payment failed', life: 3000 })
  } finally {
    processing.value = false
  }
}

const confirmCancelOrder = () => {
  confirm.require({
    message: t('pos.cancelOrder') + '?',
    header: t('common.cancel'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: () => {
      currentOrder.value = null
      cartItems.value = []
    }
  })
}

const formatNumber = (num) => {
  return Number(num || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

const getInitials = (name) => {
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
}

const getOrderTypeLabel = (type) => {
  const labels = { dine_in: t('pos.dineIn'), takeaway: t('pos.takeaway'), delivery: t('pos.delivery') }
  return labels[type] || type
}

const getTableStatusLabel = (status) => {
  const labels = { available: t('pos.tableAvailable'), occupied: t('pos.tableOccupied'), reserved: t('pos.tableReserved') }
  return labels[status] || status
}

const getTableStatusSeverity = (status) => {
  return status === 'available' ? 'success' : 'danger'
}

const getAreaLabel = (area) => {
  const labels = { indoor: t('table.indoor'), outdoor: t('table.outdoor'), vip: t('table.vip') }
  return labels[area] || area
}

// Daftar Bon helpers
function getBonTableLabel(order) {
  const num = order?.table?.table_number || order?.table_number
  if (num) return `Meja ${num}`
  return order?.order_type === 'takeaway' ? 'Takeaway' : '-'
}

function formatBonTime(ts) {
  if (!ts) return ''
  try {
    return new Date(ts).toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' })
  } catch (e) {
    return String(ts)
  }
}

async function loadBonOrders() {
  try {
    const res = await api.get(`/outlets/${numericOutletId}/orders`, { params: { status: 'bon' } })
    bonOrders.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Failed to load bon orders:', err)
    toast.add({ severity: 'error', summary: 'Gagal memuat daftar bon', detail: err.message, life: 3000 })
  }
}

watch(showBonList, (val) => { if (val) loadBonOrders() })

function viewBonDetail(order) {
  const code = order.kode || order.order_code
  if (!code) return
  window.open(`/track/${outletId}/${code}`, '_blank')
}

function markBonAsPaid(order) {
  selectedBonOrder.value = order
  showPayConfirm.value = true
}

async function confirmPayment() {
  if (!selectedBonOrder.value) return
  processingPayment.value = true
  try {
    await api.post(`/outlets/${numericOutletId}/orders/${selectedBonOrder.value.id}/settle-bon`)
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Bon telah dilunasi', life: 3000 })
    showPayConfirm.value = false
    selectedBonOrder.value = null
    await loadBonOrders()
  } catch (err) {
    console.error('Payment failed:', err)
    toast.add({ severity: 'error', summary: 'Gagal melunasi bon', detail: err.response?.data?.message || err.message, life: 3000 })
  } finally {
    processingPayment.value = false
  }
}

const openCleanupDialog = () => {
  selectedArea.value = null // Reset filter
  showCleanupDialog.value = true
}

const cleanupTable = async (table) => {
  confirm.require({
    message: `${t('pos.cleanupTableConfirm')} ${table.table_number}?`,
    header: t('pos.cleanupTable'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: async () => {
      try {
        await api.post(`/outlets/${outletId}/tables/${table.id}/cleanup`)
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('pos.tableCleanedUp'), life: 3000 })
        fetchTables()
      } catch (error) {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || 'Failed to cleanup table', life: 3000 })
      }
    }
  })
}

async function loadPendingPublicOrders () {
  try {
    const res = await api.get(`/outlets/${outletId}/public-orders/pending`)
    pendingPublicOrders.value = res.data || []
  } catch (e) {
    // Silent for background poll
  }
}

async function approvePublicOrder (order) {
  if (!order || !order.id) return
  processingPublicId.value = order.id
  try {
    await api.post(`/outlets/${outletId}/public-orders/${order.id}/approve`)
    toast.add({ severity: 'success', summary: 'OK', detail: 'Pesanan disetujui', life: 2500 })
    await loadPendingPublicOrders()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: e.response?.data?.message || e.message, life: 3500 })
  } finally {
    processingPublicId.value = null
  }
}

async function rejectPublicOrder (order) {
  if (!order || !order.id) return
  const reason = window.prompt('Alasan penolakan (opsional):', '') || ''
  processingPublicId.value = order.id
  try {
    await api.post(`/outlets/${outletId}/public-orders/${order.id}/reject`, { reason })
    toast.add({ severity: 'info', summary: 'OK', detail: 'Pesanan ditolak', life: 2500 })
    await loadPendingPublicOrders()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: e.response?.data?.message || e.message, life: 3500 })
  } finally {
    processingPublicId.value = null
  }
}

onMounted(() => {
  fetchOutlet()
  fetchMenus()
  fetchCategories()
  fetchTables()
  fetchPaymentMethods()
  fetchAvailablePromos()
  fetchMembershipSettings()
  fetchTxSettings()
  loadPendingPublicOrders()

  // Refresh applicable promos every minute to handle time-based promos
  promoRefreshInterval.value = setInterval(() => {
    if (cartItems.value.length > 0) {
      fetchApplicablePromos()
    }
  }, 60000) // 60 seconds

  // Poll pending public-orders every 15s so cashier sees new ones quickly
  publicOrdersPollTimer.value = setInterval(() => {
    loadPendingPublicOrders()
  }, 15000)
})

onBeforeUnmount(() => {
  if (promoRefreshInterval.value) {
    clearInterval(promoRefreshInterval.value)
  }
  if (publicOrdersPollTimer.value) {
    clearInterval(publicOrdersPollTimer.value)
  }
})
</script>

<style scoped>
.pos-container {
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--p-surface-50, #f5f5f5);
  color: var(--p-text-color, #111827);
}

.pos-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background: var(--p-content-background, white);
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
}

.pos-header h2 {
  margin: 0;
  font-size: 1.5rem;
  color: var(--p-text-color, inherit);
}

.text-muted {
  color: var(--p-text-muted-color, #6b7280);
  font-size: 0.875rem;
  margin: 0;
}

.pos-content {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: 0;
  overflow: hidden;
}

.menu-section {
  display: flex;
  flex-direction: column;
  background: var(--p-content-background, white);
  border-right: 1px solid var(--p-content-border-color, #e5e7eb);
}

.menu-header {
  padding: 1rem;
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.category-filter {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  overflow-x: auto;
  padding-bottom: 0.25rem;
}

.category-filter::-webkit-scrollbar {
  height: 4px;
}

.category-filter::-webkit-scrollbar-track {
  background: #f3f4f6;
}

.category-filter::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 2px;
}

.menu-grid {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 1rem;
  align-content: start;
}

.menu-card {
  background: var(--p-content-background, white);
  border: 1px solid var(--p-content-border-color, #e5e7eb);
  border-radius: 8px;
  padding: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
  color: var(--p-text-color, inherit);
}

.menu-card:hover {
  border-color: var(--p-primary-color, #3b82f6);
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
}

.menu-image {
  width: 100%;
  aspect-ratio: 1;
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 0.5rem;
  background: var(--p-surface-100, #f3f4f6);
  display: flex;
  align-items: center;
  justify-content: center;
}

.menu-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.menu-initials {
  font-size: 2rem;
  font-weight: bold;
  color: var(--p-text-muted-color, #9ca3af);
}

.menu-info {
  text-align: center;
}

.menu-name {
  font-weight: 600;
  font-size: 0.875rem;
  margin-bottom: 0.25rem;
}

.menu-price {
  color: var(--p-primary-color, #3b82f6);
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.cart-section {
  display: flex;
  flex-direction: column;
  background: var(--p-content-background, white);
  overflow-y: auto;
  color: var(--p-text-color, inherit);
}

.cleanup-section {
  padding: 1rem;
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
}

.order-type-selection {
  padding: 2rem;
  text-align: center;
}

.order-type-buttons {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 2rem;
}

.current-order {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.order-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
}

.order-header h3 {
  margin: 0;
}

.order-info {
  padding: 1rem;
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
}

.info-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.cart-items {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
}

.promo-section {
  padding: 1rem;
  border-top: 1px solid var(--p-content-border-color, #e5e7eb);
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
}

.promo-label {
  display: block;
  font-weight: 600;
  color: var(--p-text-color, #374151);
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
}

.promo-select-wrapper {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.promo-value {
  display: flex;
  align-items: center;
}

.promo-option {
  padding: 0.5rem 0;
}

.promo-option-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.25rem;
}

.promo-option-name {
  font-weight: 600;
  color: var(--p-text-color, #111827);
}

.promo-option-desc {
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #6b7280);
  margin-bottom: 0.25rem;
  margin-left: 0;
}

.promo-option-code {
  font-size: 0.75rem;
  color: #3b82f6;
  font-weight: 500;
}

.promo-option-details {
  display: flex;
  gap: 0.75rem;
  margin-top: 0.25rem;
  flex-wrap: wrap;
}

.promo-option-min {
  font-size: 0.75rem;
  color: #f59e0b;
  font-weight: 500;
}

.promo-option-time {
  font-size: 0.75rem;
  color: #8b5cf6;
  font-weight: 500;
}

.promo-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: #eff6ff;
  border: 1px solid #3b82f6;
  border-radius: 6px;
  margin-top: 0.5rem;
  color: #1e40af;
  font-size: 0.875rem;
}

.promo-info i {
  color: #3b82f6;
}

.promo-error {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: #fef2f2;
  border: 1px solid #ef4444;
  border-radius: 6px;
  margin-top: 0.5rem;
  color: #991b1b;
  font-size: 0.875rem;
}

.promo-error i {
  color: #ef4444;
}

.applied-promos {
  margin-top: 0.75rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.applied-promo-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem;
  background: #f0fdf4;
  border-radius: 6px;
  font-size: 0.875rem;
}

.promo-name {
  flex: 1;
  font-weight: 500;
  color: #166534;
}

.empty-cart {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--p-text-muted-color, #9ca3af);
}

.cart-item {
  padding: 0.75rem;
  border: 1px solid var(--p-content-border-color, #e5e7eb);
  border-radius: 6px;
  margin-bottom: 0.5rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  background: var(--p-content-background, transparent);
}

.item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.item-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.item-name {
  font-weight: 600;
}

.item-price {
  font-size: 0.875rem;
  color: var(--p-text-muted-color, #6b7280);
}

.item-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.item-controls :deep(.p-inputnumber-input) {
  text-align: center;
  padding: 0.5rem;
  font-weight: 600;
}

.item-subtotal {
  text-align: right;
  font-weight: 700;
  color: var(--p-primary-color, #3b82f6);
  min-width: 100px;
}

.item-notes-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px dashed var(--p-content-border-color, #e5e7eb);
}

.item-notes-icon {
  color: var(--p-text-muted-color, #9ca3af);
  font-size: 0.875rem;
}

.item-notes-input {
  flex: 1;
  font-size: 0.875rem;
}

.item-notes-input :deep(.p-inputtext) {
  padding: 0.4rem 0.6rem;
  font-size: 0.875rem;
  font-style: italic;
  color: var(--p-text-muted-color, #6b7280);
}

.order-totals {
  padding: 1rem;
  border-top: 1px solid var(--p-content-border-color, #e5e7eb);
  border-bottom: 1px solid var(--p-content-border-color, #e5e7eb);
}

.total-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.total-row.discount {
  color: #10b981;
  font-weight: 600;
}

.total-row.grand {
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--p-primary-color, #3b82f6);
  border-top: 2px solid var(--p-content-border-color, #e5e7eb);
  padding-top: 0.5rem;
  margin-top: 0.5rem;
}

.total-row.tax-inclusive-note {
  color: var(--p-text-muted-color, #6b7280);
  font-size: 0.75rem;
  font-style: italic;
}

.total-row.tax-inclusive-note small {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.order-actions {
  padding: 1rem;
  display: flex;
  gap: 0.5rem;
}

.payment-form {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.payment-total {
  text-align: center;
  padding: 1rem;
  background: var(--p-surface-100, #f3f4f6);
  border-radius: 8px;
}

.total-amount {
  font-size: 2rem;
  font-weight: 700;
  color: var(--p-primary-color, #3b82f6);
  margin-top: 0.5rem;
}

.payment-change {
  text-align: center;
  padding: 1rem;
  background: var(--p-surface-100, #f0fdf4);
  border-radius: 8px;
}

.change-amount {
  font-size: 1.5rem;
  font-weight: 700;
  color: #10b981;
  margin-top: 0.5rem;
}

.change-amount.negative {
  color: #ef4444;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field label {
  font-weight: 600;
}

.quick-amount-buttons {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
  flex-wrap: wrap;
}

.quick-amount-buttons button {
  flex: 1;
  min-width: 100px;
}

.table-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 1rem;
}

.table-card {
  padding: 1rem;
  border: 2px solid var(--p-content-border-color, #e5e7eb);
  border-radius: 8px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: var(--p-content-background, transparent);
  color: var(--p-text-color, inherit);
}

.table-card:hover:not(.occupied) {
  border-color: var(--p-primary-color, #3b82f6);
  background: var(--p-content-hover-background, #eff6ff);
}

.table-card.occupied {
  opacity: 0.5;
  cursor: not-allowed;
}

.table-number {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
}

.table-capacity {
  font-size: 0.875rem;
  color: var(--p-text-muted-color, #6b7280);
  margin-top: 0.5rem;
}

.area-filter {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.table-area {
  margin-top: 0.5rem;
  display: flex;
  justify-content: center;
}

/* Member styles */
.member-row {
  flex-direction: column;
  align-items: flex-start;
  gap: 0.5rem;
}

.member-row > span {
  font-weight: 600;
  font-size: 0.875rem;
}

.member-select-wrapper {
  width: 100%;
  position: relative;
}

.selected-member {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 0.75rem;
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 6px;
}

.member-info-compact {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.member-name {
  font-weight: 600;
  color: #166534;
}

.member-points {
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #6b7280);
  font-weight: 500;
}

.member-search {
  position: relative;
}

.member-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: var(--p-content-background, white);
  border: 1px solid var(--p-content-border-color, #e5e7eb);
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  z-index: 100;
  max-height: 200px;
  overflow-y: auto;
  color: var(--p-text-color, inherit);
}

.member-dropdown-item {
  padding: 0.75rem;
  cursor: pointer;
  border-bottom: 1px solid var(--p-content-border-color, #f3f4f6);
  transition: background 0.15s;
}

.member-dropdown-item:hover {
  background: var(--p-content-hover-background, #f9fafb);
}

.member-dropdown-item:last-child {
  border-bottom: none;
}

.member-dropdown-name {
  font-weight: 600;
  font-size: 0.875rem;
  margin-bottom: 0.25rem;
}

.member-dropdown-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #6b7280);
}

.member-phone {
  color: var(--p-text-muted-color, #9ca3af);
}

/* Payment member points */
.member-points-section {
  padding: 1rem;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: 8px;
}

.member-points-header {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  font-size: 0.875rem;
}

.points-redeem-row {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.points-redeem-row label {
  font-weight: 600;
  font-size: 0.875rem;
}

.points-redeem-input {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.points-value {
  font-weight: 700;
  color: #d97706;
  white-space: nowrap;
}

.points-hint {
  color: #92400e;
  font-size: 0.75rem;
}

/* Daftar Bon */
.bon-list { display: flex; flex-direction: column; gap: 0.75rem; max-height: 400px; overflow-y: auto; }
.bon-item { border: 1px solid var(--p-surface-border); border-radius: 8px; padding: 0.75rem; }
.bon-item-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; }
.bon-order-code { font-weight: 700; font-size: 0.9rem; }
.bon-table { font-size: 0.8rem; color: var(--p-text-muted-color); }
.bon-time { font-size: 0.75rem; color: var(--p-text-muted-color); margin-left: auto; }
.bon-item-body { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
.bon-total { font-weight: 600; }
.bon-item-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }
.bon-empty { text-align: center; padding: 2rem; color: var(--p-text-muted-color); }
.bon-empty i { font-size: 2rem; margin-bottom: 0.5rem; display: block; }
.pay-confirm-content { padding: 0.5rem 0; }
.pay-confirm-total { display: flex; justify-content: space-between; margin-top: 0.75rem; padding: 0.75rem; background: var(--p-surface-50); border-radius: 6px; }
:global(html.is-dark) .pay-confirm-total { background: #1a1a24; }
</style>
