<template>
  <div class="transaction-view">
    <!-- Page Header -->
    <div class="page-header">
      <div class="page-header-left">
        <Button icon="pi pi-arrow-left" text rounded size="small"
                @click="router.push(`/outlets/${outletId}/dashboard`)" />
        <div>
          <h2>{{ $t('transaction.title') }}</h2>
          <p class="text-muted">{{ $t('transaction.subtitle') }}</p>
        </div>
      </div>
      <div class="header-actions">
        <Button icon="pi pi-print" :label="isMobile ? '' : $t('printer.configure')" 
                outlined size="small"
                :severity="printer.settings.value.configured ? 'success' : 'secondary'"
                @click="printerSettingsVisible = true"
                v-tooltip.bottom="isMobile ? $t('printer.configure') : ''" />
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-icon neutral"><i class="pi pi-list"></i></div>
        <div class="summary-body">
          <div class="summary-label">{{ $t('transaction.totalOrders') }}</div>
          <div class="summary-value">{{ summary.total }}</div>
        </div>
      </div>
      <div class="summary-card success">
        <div class="summary-icon green"><i class="pi pi-check-circle"></i></div>
        <div class="summary-body">
          <div class="summary-label">{{ $t('transaction.paidOrders') }}</div>
          <div class="summary-value">{{ summary.paid }}</div>
        </div>
      </div>
      <div class="summary-card revenue">
        <div class="summary-icon blue"><i class="pi pi-wallet"></i></div>
        <div class="summary-body">
          <div class="summary-label">{{ $t('transaction.totalRevenue') }}</div>
          <div class="summary-value summary-value-sm">Rp {{ formatNumber(summary.revenue) }}</div>
        </div>
      </div>
      <div class="summary-card danger">
        <div class="summary-icon red"><i class="pi pi-times-circle"></i></div>
        <div class="summary-body">
          <div class="summary-label">{{ $t('transaction.cancelledOrders') }}</div>
          <div class="summary-value">{{ summary.cancelled }}</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
      <div class="filter-header">
        <h3><i class="pi pi-filter"></i> {{ $t('common.filter') }}</h3>
      </div>
      
      <div class="filter-row">
        <div class="filter-group flex-grow">
          <label class="filter-label">{{ $t('common.search') }}</label>
          <IconField class="w-full">
            <InputIcon><i class="pi pi-search" /></InputIcon>
            <InputText v-model="searchQuery" 
                       :placeholder="$t('transaction.searchOrder')" 
                       fluid />
          </IconField>
        </div>
        
        <div class="filter-group">
          <label class="filter-label">{{ $t('transaction.dateRange') }}</label>
          <DatePicker v-model="dateRange" 
                      selectionMode="range" 
                      :manualInput="false"
                      :placeholder="$t('transaction.dateRange')" 
                      showIcon />
        </div>
      </div>
      
      <div class="filter-row">
        <div class="filter-group">
          <label class="filter-label">{{ $t('transaction.status') }}</label>
          <Select v-model="filterStatus" 
                  :options="statusOptions" 
                  optionLabel="label" 
                  optionValue="value"
                  :placeholder="$t('transaction.allStatuses')" 
                  showClear />
        </div>
        
        <div class="filter-group">
          <label class="filter-label">{{ $t('transaction.type') }}</label>
          <Select v-model="filterType" 
                  :options="typeOptions" 
                  optionLabel="label" 
                  optionValue="value"
                  :placeholder="$t('transaction.allTypes')" 
                  showClear />
        </div>
      </div>
      
      <div class="filter-actions">
        <Button :label="$t('common.search')" 
                icon="pi pi-search" 
                @click="fetchOrders" 
                severity="primary" />
        <Button icon="pi pi-refresh" 
                text 
                @click="resetFilters"
                v-tooltip.top="$t('transaction.reset')" />
      </div>
    </div>

    <!-- Mobile: Card List -->
    <div v-if="isMobile" class="order-cards">
      <div v-if="loading" class="loading-state">
        <i class="pi pi-spin pi-spinner"></i>
        <span>{{ $t('common.loading') }}</span>
      </div>
      <div v-else-if="filteredOrders.length === 0" class="empty-state">
        <i class="pi pi-inbox"></i>
        <span>{{ $t('transaction.noOrders') }}</span>
      </div>
      <div v-else v-for="order in filteredOrders" :key="order.id" 
           class="order-card" @click="openDetail(order)">
        <div class="order-card-top">
          <span class="order-code">{{ order.kode }}</span>
          <Tag :value="getStatusLabel(order.status)" :severity="getStatusSeverity(order.status)" size="small" />
        </div>
        <div class="order-card-mid">
          <div class="order-card-info">
            <i class="pi pi-calendar"></i>
            <span>{{ formatDateTime(order.created_at_local || order.created_at) }}</span>
          </div>
          <div class="order-card-info">
            <Tag :value="getTypeLabel(order.order_type)" :severity="getTypeSeverity(order.order_type)" size="small" />
            <span v-if="order.table_number" class="table-badge">
              <i class="pi pi-table"></i> {{ order.table_number }}
            </span>
          </div>
        </div>
        <div class="order-card-bottom">
          <div class="order-card-customer">
            <template v-if="order.member_id && order.member">
              <i class="pi pi-id-card" style="color:#8b5cf6"></i>
              <span class="member-name">{{ order.member.nama }}</span>
            </template>
            <span v-else-if="order.customer_name">{{ order.customer_name }}</span>
            <span v-else class="text-muted-sm">{{ $t('transaction.regularCustomer') }}</span>
          </div>
          <div class="order-card-right">
            <span class="amount">Rp {{ formatNumber(order.total_amount) }}</span>
            <div class="order-card-actions" @click.stop>
              <Button v-if="order.status === 'paid'" icon="pi pi-print" text rounded size="small" 
                      severity="success" @click.stop="printReceipt(order)" 
                      :loading="printingId === order.id" />
              <Button icon="pi pi-qrcode" text rounded size="small" severity="info"
                      @click.stop="openQr(order)" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Desktop: DataTable -->
    <DataTable v-else :value="filteredOrders" :loading="loading" paginator :rows="20"
               :rowsPerPageOptions="[10, 20, 50]" stripedRows
               @row-click="openDetail($event.data)" rowHover class="orders-table">
      <Column field="kode" :header="$t('transaction.orderCode')" sortable style="width: 160px">
        <template #body="{ data }">
          <span class="order-code">{{ data.kode }}</span>
        </template>
      </Column>
      <Column field="created_at" :header="$t('common.date')" sortable style="width: 160px">
        <template #body="{ data }">
          {{ formatDateTime(data.created_at_local || data.created_at) }}
        </template>
      </Column>
      <Column field="order_type" :header="$t('transaction.type')" style="width: 120px">
        <template #body="{ data }">
          <Tag :value="getTypeLabel(data.order_type)" :severity="getTypeSeverity(data.order_type)" size="small" />
        </template>
      </Column>
      <Column field="table_number" :header="$t('pos.table')" style="width: 100px">
        <template #body="{ data }">{{ data.table_number || '-' }}</template>
      </Column>
      <Column field="customer_name" :header="$t('transaction.customer')" style="width: 160px">
        <template #body="{ data }">
          <div v-if="data.member_id && data.member" class="customer-cell member">
            <i class="pi pi-id-card" style="color: #8b5cf6; font-size: 0.75rem;"></i>
            <span>{{ data.member.nama }}</span>
          </div>
          <span v-else-if="data.customer_name">{{ data.customer_name }}</span>
          <span v-else class="text-muted-sm">{{ $t('transaction.regularCustomer') }}</span>
        </template>
      </Column>
      <Column field="total_amount" :header="$t('transaction.total')" sortable style="width: 140px">
        <template #body="{ data }">
          <span class="amount">Rp {{ formatNumber(data.total_amount) }}</span>
        </template>
      </Column>
      <Column field="status" :header="$t('common.status')" style="width: 110px">
        <template #body="{ data }">
          <Tag :value="getStatusLabel(data.status)" :severity="getStatusSeverity(data.status)" />
        </template>
      </Column>
      <Column :header="$t('common.actions')" style="width: 110px">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button icon="pi pi-eye" text rounded size="small" @click.stop="openDetail(data)"
                    v-tooltip.top="$t('transaction.viewDetail')" />
            <Button v-if="data.status === 'paid'" icon="pi pi-print" text rounded size="small" severity="success"
                    @click.stop="printReceipt(data)" :loading="printingId === data.id"
                    v-tooltip.top="$t('transaction.printReceipt')" />
            <Button icon="pi pi-qrcode" text rounded size="small" severity="info"
                    @click.stop="openQr(data)"
                    v-tooltip.top="$t('transaction.qrTracking')" />
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Detail Dialog -->
    <Dialog v-model:visible="detailVisible" :header="$t('transaction.orderDetail')" modal :style="{ width: '650px' }" :maximizable="true">
      <div v-if="selectedOrder" class="order-detail">

        <!-- Header: kode + status -->
        <div class="detail-header">
          <div class="detail-kode">{{ selectedOrder.kode }}</div>
          <div class="detail-header-tags">
            <Tag :value="getStatusLabel(selectedOrder.status)" :severity="getStatusSeverity(selectedOrder.status)" />
            <Tag :value="getTypeLabel(selectedOrder.order_type)" :severity="getTypeSeverity(selectedOrder.order_type)" size="small" />
          </div>
        </div>

        <!-- Customer / Member -->
        <div class="detail-section">
          <div class="detail-section-title">{{ $t('transaction.customerInfo') }}</div>
          <div v-if="selectedOrder.member" class="member-card">
            <div class="member-card-left">
              <i class="pi pi-id-card" style="font-size: 1.5rem; color: #8b5cf6;"></i>
            </div>
            <div class="member-card-body">
              <div class="member-card-name">{{ selectedOrder.member.nama }}</div>
              <div class="member-card-meta">
                <Tag :value="selectedOrder.member.tier" :severity="getTierSeverity(selectedOrder.member.tier)" size="small" />
                <span class="meta-item">{{ selectedOrder.member.card_number }}</span>
                <span v-if="selectedOrder.member.phone" class="meta-item">{{ selectedOrder.member.phone }}</span>
              </div>
              <div class="member-card-points">
                <i class="pi pi-bolt" style="color: #f59e0b;"></i>
                <span>{{ $t('transaction.pointsAfter') }}: <strong>{{ selectedOrder.member.points?.toLocaleString('id-ID') }} pts</strong></span>
              </div>
            </div>
          </div>
          <div v-else class="detail-grid">
            <div class="detail-row">
              <span class="detail-label">{{ $t('transaction.customerType') }}</span>
              <span>{{ $t('transaction.regularCustomer') }}</span>
            </div>
            <div v-if="selectedOrder.customer_name" class="detail-row">
              <span class="detail-label">{{ $t('transaction.customer') }}</span>
              <span>{{ selectedOrder.customer_name }}</span>
            </div>
            <div v-if="selectedOrder.customer_phone" class="detail-row">
              <span class="detail-label">{{ $t('member.phone') }}</span>
              <span>{{ selectedOrder.customer_phone }}</span>
            </div>
          </div>
        </div>

        <!-- Order Info -->
        <div class="detail-section">
          <div class="detail-section-title">{{ $t('transaction.orderInfo') }}</div>
          <div class="detail-grid">
            <div class="detail-row">
              <span class="detail-label">{{ $t('common.date') }}</span>
              <span>{{ formatDateTime(selectedOrder.created_at_local || selectedOrder.created_at) }}</span>
            </div>
            <div v-if="selectedOrder.table_number" class="detail-row">
              <span class="detail-label">{{ $t('pos.table') }}</span>
              <span>{{ selectedOrder.table_number }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">{{ $t('transaction.cashier') }}</span>
              <span>{{ selectedOrder.cashier?.name || '-' }}</span>
            </div>
          </div>
        </div>

        <!-- Items -->
        <div class="detail-section">
          <div class="detail-section-title">{{ $t('transaction.items') }}</div>
          <div class="items-list">
            <div class="item-row item-row-header">
              <div>{{ $t('transaction.itemName') }}</div>
              <div style="text-align:center">Qty</div>
              <div style="text-align:right">{{ $t('transaction.unitPrice') }}</div>
              <div style="text-align:right">{{ $t('transaction.itemTotal') }}</div>
            </div>
            <div v-for="item in selectedOrder.items" :key="item.id" class="item-row">
              <div class="item-name">
                {{ item.menu_name }}
                <span v-if="item.notes" class="item-notes">— {{ item.notes }}</span>
              </div>
              <div class="item-qty">{{ item.quantity }}</div>
              <div class="item-price">Rp {{ formatNumber(item.menu_price) }}</div>
              <div class="item-subtotal">Rp {{ formatNumber(item.subtotal) }}</div>
            </div>
          </div>
        </div>

        <!-- Totals -->
        <div class="detail-totals">
          <div class="total-row">
            <span>{{ $t('pos.subtotal') }}</span>
            <span>Rp {{ formatNumber(selectedOrder.subtotal) }}</span>
          </div>

          <!-- Promo breakdown -->
          <template v-if="selectedOrder.applied_promos?.length">
            <div v-for="promo in selectedOrder.applied_promos" :key="promo.id" class="total-row discount promo-row">
              <span>
                <i class="pi pi-tag" style="font-size:0.75rem;"></i>
                {{ promo.nama }}
                <span class="promo-code-badge">{{ promo.kode }}</span>
                <Tag v-if="promo.is_stackable" value="Stackable" severity="warn" size="small" style="margin-left:4px;" />
              </span>
              <span>- Rp {{ formatNumber(promo.discount_amount) }}</span>
            </div>
          </template>
          <div v-else-if="selectedOrder.discount_amount > 0 && !selectedOrder.points_redeemed" class="total-row discount">
            <span>{{ $t('pos.discount') }}</span>
            <span>- Rp {{ formatNumber(selectedOrder.discount_amount) }}</span>
          </div>

          <!-- Point redemption -->
          <div v-if="selectedOrder.points_redeemed > 0" class="total-row points-redeem-row">
            <span>
              <i class="pi pi-bolt" style="color:#f59e0b; font-size:0.75rem;"></i>
              {{ $t('transaction.pointsRedeemed') }} ({{ selectedOrder.points_redeemed?.toLocaleString('id-ID') }} pts)
            </span>
            <span>- Rp {{ formatNumber(pointRedeemValue) }}</span>
          </div>

          <div class="total-row">
            <span>{{ $t('pos.tax') }} ({{ selectedOrder.tax_percentage }}%)</span>
            <span>Rp {{ formatNumber(selectedOrder.tax_amount) }}</span>
          </div>
          <div v-if="selectedOrder.service_charge_amount > 0" class="total-row">
            <span>Service Charge ({{ selectedOrder.service_charge_percentage }}%)</span>
            <span>Rp {{ formatNumber(selectedOrder.service_charge_amount) }}</span>
          </div>
          <div class="total-row grand">
            <span>{{ $t('pos.total') }}</span>
            <span>Rp {{ formatNumber(selectedOrder.total_amount) }}</span>
          </div>
        </div>

        <!-- Points earned/redeemed summary -->
        <div v-if="selectedOrder.points_earned > 0 || selectedOrder.points_redeemed > 0" class="points-summary">
          <div v-if="selectedOrder.points_earned > 0" class="points-earned">
            <i class="pi pi-bolt"></i>
            <span>+{{ selectedOrder.points_earned?.toLocaleString('id-ID') }} {{ $t('transaction.pointsEarned') }}</span>
          </div>
          <div v-if="selectedOrder.points_redeemed > 0" class="points-redeemed">
            <i class="pi pi-minus-circle"></i>
            <span>-{{ selectedOrder.points_redeemed?.toLocaleString('id-ID') }} {{ $t('transaction.pointsRedeemedLabel') }}</span>
          </div>
        </div>

        <!-- Payment Info -->
        <div v-if="selectedOrder.status === 'paid'" class="detail-section">
          <div class="detail-section-title">{{ $t('transaction.paymentInfo') }}</div>
          <div class="detail-grid">
            <div class="detail-row">
              <span class="detail-label">{{ $t('pos.paymentMethod') }}</span>
              <span>{{ selectedOrder.paymentMethod?.name || '-' }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">{{ $t('pos.amountPaid') }}</span>
              <span>Rp {{ formatNumber(selectedOrder.paid_amount) }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">{{ $t('pos.change') }}</span>
              <span>Rp {{ formatNumber(selectedOrder.change_amount) }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">{{ $t('transaction.paidAt') }}</span>
              <span>{{ formatDateTime(selectedOrder.paid_at_local || selectedOrder.paid_at) }}</span>
            </div>
          </div>
        </div>

        <!-- Cancellation Info -->
        <div v-if="selectedOrder.status === 'cancelled'" class="detail-section cancelled-section">
          <div class="detail-section-title">{{ $t('transaction.cancellationInfo') }}</div>
          <div class="detail-grid">
            <div class="detail-row">
              <span class="detail-label">{{ $t('transaction.cancelledAt') }}</span>
              <span>{{ formatDateTime(selectedOrder.cancelled_at_local || selectedOrder.cancelled_at) }}</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">{{ $t('transaction.reason') }}</span>
              <span>{{ selectedOrder.cancellation_reason }}</span>
            </div>
          </div>
        </div>

        <!-- End-to-End Time Summary -->
        <div v-if="selectedOrder.end_to_end_seconds !== null && selectedOrder.end_to_end_seconds !== undefined" class="detail-section e2e-section">
          <div class="detail-section-title">
            <i class="pi pi-clock" style="margin-right:0.4rem;"></i>
            {{ $t('transaction.endToEndTitle') }}
          </div>
          
          <div class="e2e-summary-card">
            <div class="e2e-main">
              <div class="e2e-icon">
                <i class="pi pi-flag-fill"></i>
              </div>
              <div class="e2e-content">
                <div class="e2e-label">{{ $t('transaction.totalTimeToCustomer') }}</div>
                <div class="e2e-value" :class="getE2EClass(selectedOrder.end_to_end_seconds)">
                  {{ formatDuration(selectedOrder.end_to_end_seconds) }}
                </div>
                <div class="e2e-meta" v-if="selectedOrder.end_to_end_summary">
                  {{ selectedOrder.end_to_end_summary.items_served_count }} / {{ selectedOrder.end_to_end_summary.total_items_count }} {{ $t('transaction.itemsServed') }}
                </div>
              </div>
            </div>
            
            <!-- Breakdown Steps -->
            <div v-if="selectedOrder.end_to_end_summary?.breakdown?.length" class="e2e-breakdown">
              <div class="e2e-breakdown-title">
                {{ $t('transaction.timeBreakdown') }}
                <span v-if="selectedOrder.end_to_end_summary.stations_count > 1" class="e2e-parallel-badge">
                  <i class="pi pi-sitemap"></i>
                  {{ selectedOrder.end_to_end_summary.stations_count }} {{ $t('transaction.stationsParallel') }}
                </span>
              </div>
              <div class="e2e-steps">
                <div v-for="(step, index) in selectedOrder.end_to_end_summary.breakdown" :key="index" 
                     class="e2e-step" :class="{ 'e2e-step-station': step.step === 'station_processing' }">
                  <div class="e2e-step-icon" 
                       :style="step.station_color ? { background: step.station_color } : {}">
                    <i :class="getStepIcon(step.step)"></i>
                  </div>
                  <div class="e2e-step-content">
                    <div class="e2e-step-info">
                      <div class="e2e-step-label">
                        <template v-if="step.step === 'station_processing'">
                          {{ step.station_name }}
                        </template>
                        <template v-else>
                          {{ $t(`transaction.step_${step.step}`) }}
                        </template>
                      </div>
                      <div v-if="step.items_count" class="e2e-step-meta">
                        {{ step.items_count }} {{ $t('transaction.items') }}
                      </div>
                    </div>
                    <div class="e2e-step-duration">{{ formatDuration(step.seconds) }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- KDS Processing Time -->
        <div v-if="hasKdsData" class="detail-section kds-section">
          <div class="detail-section-title">
            <i class="pi pi-stopwatch" style="margin-right:0.4rem;"></i>
            {{ $t('transaction.kdsTitle') }}
          </div>

          <!-- Order-level summary -->
          <div v-if="selectedOrder.kds_summary" class="kds-summary-cards">
            <div class="kds-card">
              <div class="kds-card-label">{{ $t('transaction.kdsAvgPrep') }}</div>
              <div class="kds-card-value">{{ formatDuration(selectedOrder.kds_summary.avg_prep_seconds) }}</div>
            </div>
            <div class="kds-card">
              <div class="kds-card-label">{{ $t('transaction.kdsMaxPrep') }}</div>
              <div class="kds-card-value warn">{{ formatDuration(selectedOrder.kds_summary.max_prep_seconds) }}</div>
            </div>
            <div class="kds-card" v-if="selectedOrder.kds_summary.avg_serve_seconds !== null">
              <div class="kds-card-label">{{ $t('transaction.kdsAvgServe') }}</div>
              <div class="kds-card-value">{{ formatDuration(selectedOrder.kds_summary.avg_serve_seconds) }}</div>
            </div>
          </div>

          <!-- Per-item timeline -->
          <div class="kds-items">
            <div v-for="item in kdsItems" :key="item.id" class="kds-item-row">
              <div class="kds-item-left">
                <span v-if="item.station_name" class="kds-station-badge"
                      :style="{ background: item.station_color + '22', color: item.station_color, borderColor: item.station_color + '55' }">
                  <i :class="item.station_icon"></i> {{ item.station_name }}
                </span>
                <span class="kds-item-name">{{ item.menu_name }}</span>
                <span class="kds-qty">×{{ item.quantity }}</span>
              </div>
              <div class="kds-item-timeline">
                <!-- Prep time -->
                <div class="kds-time-block" :class="getPrepClass(item.prep_duration)">
                  <i class="pi pi-play"></i>
                  <span>{{ formatDuration(item.prep_duration) }}</span>
                  <small>{{ $t('transaction.kdsPrep') }}</small>
                </div>
                <!-- Arrow -->
                <i class="pi pi-arrow-right kds-arrow" v-if="item.serve_duration !== null"></i>
                <!-- Serve time -->
                <div v-if="item.serve_duration !== null" class="kds-time-block serve">
                  <i class="pi pi-send"></i>
                  <span>{{ formatDuration(item.serve_duration) }}</span>
                  <small>{{ $t('transaction.kdsServe') }}</small>
                </div>
                <!-- Not processed -->
                <div v-if="item.prep_duration === null" class="kds-time-block none">
                  <i class="pi pi-minus"></i>
                  <small>{{ $t('transaction.kdsNotTracked') }}</small>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <template #footer>
        <Button :label="$t('common.close')" text @click="detailVisible = false" />
        <Button icon="pi pi-qrcode" label="QR Tracking" outlined severity="info"
                @click="openQr(selectedOrder)" />
        <Button v-if="selectedOrder?.status === 'paid'" :label="$t('transaction.printReceipt')"
                icon="pi pi-print" @click="printReceipt(selectedOrder)"
                :loading="printingId === selectedOrder?.id" severity="success" />
      </template>
    </Dialog>

    <!-- QR Tracking Dialog - Mobile Optimized -->
    <Dialog v-model:visible="qrDialogVisible" :header="$t('transaction.qrTracking')" modal 
            :style="{ width: isMobile ? '95vw' : '480px', maxWidth: '600px' }" 
            :breakpoints="{ '960px': '90vw', '640px': '95vw' }">
      <div class="qr-dialog-content">
        <!-- Order Header -->
        <div class="qr-order-header">
          <div class="qr-order-code-badge">
            <i class="pi pi-receipt"></i>
            <span>{{ qrOrder?.kode }}</span>
          </div>
          <Tag :value="getStatusLabel(qrOrder?.status)" :severity="getStatusSeverity(qrOrder?.status)" />
        </div>

        <!-- QR Code -->
        <div class="qr-code-container">
          <canvas ref="qrCanvas" class="qr-canvas"></canvas>
          <p class="qr-scan-hint">
            <i class="pi pi-camera"></i>
            {{ $t('transaction.scanQrHint') }}
          </p>
        </div>

        <!-- Transaction Details -->
        <div v-if="qrOrder" class="qr-transaction-details">
          <div class="qr-detail-section">
            <div class="qr-detail-title">
              <i class="pi pi-info-circle"></i>
              {{ $t('transaction.orderInfo') }}
            </div>
            <div class="qr-detail-grid">
              <div class="qr-detail-row">
                <span class="qr-label">{{ $t('common.date') }}</span>
                <span class="qr-value">{{ formatDateTime(qrOrder.created_at_local || qrOrder.created_at) }}</span>
              </div>
              <div class="qr-detail-row">
                <span class="qr-label">{{ $t('transaction.type') }}</span>
                <Tag :value="getTypeLabel(qrOrder.order_type)" :severity="getTypeSeverity(qrOrder.order_type)" size="small" />
              </div>
              <div v-if="qrOrder.table_number" class="qr-detail-row">
                <span class="qr-label">{{ $t('pos.table') }}</span>
                <span class="qr-value">{{ qrOrder.table_number }}</span>
              </div>
            </div>
          </div>

          <div class="qr-detail-section">
            <div class="qr-detail-title">
              <i class="pi pi-shopping-cart"></i>
              {{ $t('transaction.items') }} ({{ qrOrder.items?.length || 0 }})
            </div>
            <div class="qr-items-list">
              <div v-for="item in qrOrder.items?.slice(0, 3)" :key="item.id" class="qr-item">
                <span class="qr-item-name">{{ item.menu_name }}</span>
                <span class="qr-item-qty">×{{ item.quantity }}</span>
              </div>
              <div v-if="qrOrder.items?.length > 3" class="qr-more-items">
                +{{ qrOrder.items.length - 3 }} {{ $t('transaction.moreItems') }}
              </div>
            </div>
          </div>

          <div class="qr-detail-section qr-total-section">
            <div class="qr-total-row">
              <span class="qr-total-label">{{ $t('pos.total') }}</span>
              <span class="qr-total-value">Rp {{ formatNumber(qrOrder.total_amount) }}</span>
            </div>
          </div>
        </div>

        <!-- Tracking URL -->
        <div class="qr-url-section">
          <label class="qr-url-label">{{ $t('transaction.trackingUrl') }}</label>
          <div class="qr-url-box">
            <input type="text" :value="qrTrackingUrl" readonly class="qr-url-input" />
            <Button icon="pi pi-copy" text rounded size="small" 
                    @click="copyToClipboard(qrTrackingUrl)"
                    v-tooltip.top="$t('common.copy')" />
          </div>
        </div>
      </div>

      <template #footer>
        <div class="qr-dialog-footer">
          <Button :label="$t('common.close')" text @click="qrDialogVisible = false" />
          <Button :label="$t('transaction.openTracking')" icon="pi pi-external-link" severity="info"
                  @click="navigateToTracking(qrTrackingUrl)" />
        </div>
      </template>
    </Dialog>

    <!-- Printer Settings Dialog -->
    <PrinterSettingsDialog v-model="printerSettingsVisible" :printer="printer" />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import api from '@/services/api'
import { useThermalPrinter } from '@/composables/useThermalPrinter'
import PrinterSettingsDialog from '@/components/PrinterSettingsDialog.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { t } = useI18n()

const outletId = route.params.outletId

const printer = useThermalPrinter(outletId)
const printerSettingsVisible = ref(false)

// ── Mobile detection ──────────────────────────────────────────────────────────
const windowWidth = ref(window.innerWidth)
const onResize = () => { windowWidth.value = window.innerWidth }
onMounted(() => window.addEventListener('resize', onResize))
onUnmounted(() => window.removeEventListener('resize', onResize))
const isMobile = computed(() => windowWidth.value < 768)

const orders = ref([])
const loading = ref(false)
const searchQuery = ref('')
const filterStatus = ref(null)
const filterType = ref(null)
const dateRange = ref(null)
const detailVisible = ref(false)
const selectedOrder = ref(null)
const printingId = ref(null)

// QR tracking
const qrDialogVisible = ref(false)
const qrOrder         = ref(null)
const qrCanvas        = ref(null)
const qrTrackingUrl   = computed(() => {
  if (!qrOrder.value) return ''
  const base = window.location.origin
  return `${base}/track/${outletId}/${qrOrder.value.kode}`
})

const openQr = async (order) => {
  qrOrder.value         = order
  qrDialogVisible.value = true
  await nextTick()
  if (qrCanvas.value) {
    QRCode.toCanvas(qrCanvas.value, qrTrackingUrl.value, {
      width: 280,
      margin: 2,
      color: { dark: '#2A3547', light: '#ffffff' },
    })
  }
}

const navigateToTracking = (url) => {
  qrDialogVisible.value = false
  window.open(url, '_blank')
}

// ── Clipboard helper ─────────────────────────────────────────────────────────
const copyToClipboard = async (text) => {
  try {
    await navigator.clipboard.writeText(text)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('common.copied'), life: 2000 })
  } catch {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('common.copyFailed'), life: 2000 })
  }
}

const statusOptions = computed(() => [
  { label: t('transaction.statusDraft'), value: 'draft' },
  { label: t('transaction.statusPaid'), value: 'paid' },
  { label: t('transaction.statusCancelled'), value: 'cancelled' },
])

const typeOptions = computed(() => [
  { label: t('pos.dineIn'), value: 'dine_in' },
  { label: t('pos.takeaway'), value: 'takeaway' },
  { label: t('pos.delivery'), value: 'delivery' },
])

const pointRedeemValue = computed(() => {
  if (!selectedOrder.value?.points_redeemed || !selectedOrder.value?.applied_promos) return 0
  const promoDiscount = (selectedOrder.value.applied_promos || []).reduce((s, p) => s + parseFloat(p.discount_amount || 0), 0)
  return Math.max(0, parseFloat(selectedOrder.value.discount_amount || 0) - promoDiscount)
})

// KDS items: only items that have a station assigned
const kdsItems = computed(() =>
  (selectedOrder.value?.items || []).filter(i => i.station_id)
)

const hasKdsData = computed(() =>
  kdsItems.value.length > 0
)

const summary = computed(() => {
  const paid = orders.value.filter(o => o.status === 'paid')
  return {
    total: orders.value.length,
    paid: paid.length,
    cancelled: orders.value.filter(o => o.status === 'cancelled').length,
    revenue: paid.reduce((sum, o) => sum + parseFloat(o.total_amount || 0), 0),
  }
})

const filteredOrders = computed(() => {
  let result = orders.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(o =>
      o.kode?.toLowerCase().includes(q) ||
      o.customer_name?.toLowerCase().includes(q) ||
      o.table_number?.toLowerCase().includes(q)
    )
  }
  if (filterStatus.value) result = result.filter(o => o.status === filterStatus.value)
  if (filterType.value) result = result.filter(o => o.order_type === filterType.value)
  return result
})

const fetchOrders = async () => {
  loading.value = true
  try {
    const params = {
      tz: Intl.DateTimeFormat().resolvedOptions().timeZone
    }
    
    if (dateRange.value?.[0]) {
      // Start date: beginning of day
      params.start_date = dateRange.value[0].toISOString().split('T')[0] + ' 00:00:00'
      
      // End date: if same day or no end date, use end of start day
      if (dateRange.value[1]) {
        params.end_date = dateRange.value[1].toISOString().split('T')[0] + ' 23:59:59'
      } else {
        // If only one date selected, treat as same day (start to end of that day)
        params.end_date = dateRange.value[0].toISOString().split('T')[0] + ' 23:59:59'
      }
    }
    
    const response = await api.get(`/outlets/${outletId}/orders`, { params })
    orders.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const openDetail = async (order) => {
  try {
    const params = {
      tz: Intl.DateTimeFormat().resolvedOptions().timeZone
    }
    const response = await api.get(`/outlets/${outletId}/orders/${order.id}`, { params })
    selectedOrder.value = response.data
    detailVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const printReceipt = async (order) => {
  // If printer not configured, open settings first
  if (!printer.settings.value.configured) {
    printerSettingsVisible.value = true
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('printer.notConfiguredError'), life: 4000 })
    return
  }

  printingId.value = order.id
  try {
    const params = { tz: Intl.DateTimeFormat().resolvedOptions().timeZone }
    const response = await api.get(`/outlets/${outletId}/orders/${order.id}/thermal-receipt`, { params })
    await printer.print(response.data.lines)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('printer.printSuccess'), life: 3000 })
  } catch (error) {
    if (error.message === 'PRINTER_NOT_CONFIGURED') {
      printerSettingsVisible.value = true
      toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('printer.notConfiguredError'), life: 4000 })
    } else {
      // Fallback to PDF print
      try {
        const params = { tz: Intl.DateTimeFormat().resolvedOptions().timeZone }
        const response = await api.get(`/outlets/${outletId}/orders/${order.id}/receipt`, { params, responseType: 'blob' })
        const blob = new Blob([response.data], { type: 'application/pdf' })
        const blobUrl = URL.createObjectURL(blob)
        const printWindow = window.open(blobUrl, '_blank', 'width=400,height=700')
        if (printWindow) {
          printWindow.onload = () => { printWindow.focus(); printWindow.print() }
          setTimeout(() => URL.revokeObjectURL(blobUrl), 10000)
        }
      } catch {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: t('transaction.printFailed'), life: 3000 })
      }
    }
  } finally {
    printingId.value = null
  }
}

const resetFilters = () => {
  searchQuery.value = ''
  filterStatus.value = null
  filterType.value = null
  dateRange.value = null
  fetchOrders()
}

const formatNumber = (num) => Number(num || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })

// Format seconds into human-readable duration with auto unit selection and 1 decimal
const formatDuration = (seconds) => {
  if (seconds === null || seconds === undefined) return '-'
  
  // Convert to hours if >= 60 minutes
  if (seconds >= 3600) {
    const hours = seconds / 3600
    return `${hours.toFixed(1)}h`
  }
  
  // Convert to minutes if >= 60 seconds
  if (seconds >= 60) {
    const minutes = seconds / 60
    return `${minutes.toFixed(1)}m`
  }
  
  // Show seconds
  return `${seconds.toFixed(1)}d`
}

// Color-code prep time: green < 5m, yellow < 10m, red >= 10m
const getPrepClass = (seconds) => {
  if (seconds === null || seconds === undefined) return 'none'
  if (seconds < 300) return 'good'
  if (seconds < 600) return 'warn'
  return 'slow'
}

// Color-code end-to-end time: green < 15m, yellow < 30m, red >= 30m
const getE2EClass = (seconds) => {
  if (seconds === null || seconds === undefined) return 'none'
  if (seconds < 900) return 'good'   // < 15 minutes
  if (seconds < 1800) return 'warn'  // < 30 minutes
  return 'slow'                       // >= 30 minutes
}

// Get icon for each step in breakdown
const getStepIcon = (step) => {
  const icons = {
    'order_to_kitchen': 'pi pi-send',
    'first_item_prep': 'pi pi-play',
    'remaining_items_prep': 'pi pi-spinner',
    'station_processing': 'pi pi-cog',
    'ready_to_served': 'pi pi-check-circle',
  }
  return icons[step] || 'pi pi-circle'
}

const formatDateTime = (dt) => {
  if (!dt) return '-'
  // Prioritize _local timestamp if available (already converted by backend)
  const timestamp = dt.replace('_local', '')
  const dateObj = new Date(timestamp)
  return dateObj.toLocaleString('id-ID', { 
    day: '2-digit', 
    month: 'short', 
    year: 'numeric', 
    hour: '2-digit', 
    minute: '2-digit' 
  })
}

const getTierSeverity = (tier) => {
  const map = { Silver: 'secondary', Gold: 'warn', Platinum: 'info' }
  return map[tier] || 'secondary'
}

const getStatusLabel = (status) => {
  const map = { draft: t('transaction.statusDraft'), paid: t('transaction.statusPaid'), cancelled: t('transaction.statusCancelled') }
  return map[status] || status
}

const getStatusSeverity = (status) => {
  const map = { draft: 'warn', paid: 'success', cancelled: 'danger' }
  return map[status] || 'secondary'
}

const getTypeLabel = (type) => {
  const map = { dine_in: t('pos.dineIn'), takeaway: t('pos.takeaway'), delivery: t('pos.delivery') }
  return map[type] || type
}

const getTypeSeverity = (type) => {
  const map = { dine_in: 'info', takeaway: 'secondary', delivery: 'warn' }
  return map[type] || 'secondary'
}

onMounted(fetchOrders)
</script>

<style scoped>
.transaction-view { padding: 1.5rem; }

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}
.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }

.header-actions { display: flex; gap: 0.5rem; align-items: center; }

/* ── Filter Card ─────────────────────────────────────────────────────────────── */
.filter-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.filter-header {
  display: flex;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #f3f4f6;
}

.filter-header h3 {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #374151;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.filter-header i {
  font-size: 1rem;
  color: #6b7280;
}

.filter-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.filter-group.flex-grow {
  grid-column: 1 / -1;
}

.filter-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.filter-actions {
  display: flex;
  gap: 0.75rem;
  justify-content: flex-start;
  flex-wrap: wrap;
}

.w-full {
  width: 100%;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.summary-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem 1.25rem;
  border-left: 4px solid #6b7280;
}
.summary-card.success { border-left-color: #22c55e; }
.summary-card.revenue { border-left-color: #3b82f6; }
.summary-card.danger { border-left-color: #ef4444; }

.summary-label { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem; }
.summary-value { font-size: 1.5rem; font-weight: 700; }

.order-code { font-family: monospace; font-weight: 600; color: #3b82f6; }
.amount { font-weight: 600; }
.text-muted-sm { color: #9ca3af; font-size: 0.8rem; }

.customer-cell { display: flex; align-items: center; gap: 0.35rem; font-size: 0.875rem; }
.customer-cell.member { color: #7c3aed; font-weight: 500; }

.action-buttons { display: flex; gap: 0.25rem; }

/* Detail dialog */
.order-detail { display: flex; flex-direction: column; gap: 1.25rem; }

.detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}
.detail-kode { font-size: 1.25rem; font-weight: 700; font-family: monospace; color: #3b82f6; }
.detail-header-tags { display: flex; gap: 0.5rem; align-items: center; }

.detail-grid { display: flex; flex-direction: column; gap: 0.5rem; }
.detail-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; }
.detail-label { color: #6b7280; }

.detail-section { border-top: 1px solid #e5e7eb; padding-top: 1rem; }
.detail-section-title { font-weight: 700; font-size: 0.875rem; color: #374151; margin-bottom: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }

.items-list { display: flex; flex-direction: column; gap: 0.5rem; }
.item-row-header {
  font-size: 0.75rem;
  font-weight: 700;
  color: #6b7280;
  text-transform: uppercase;
  background: transparent !important;
  padding-bottom: 0.25rem;
}
.item-row {
  display: grid;
  grid-template-columns: 1fr 40px 110px 110px;
  gap: 0.5rem;
  align-items: center;
  font-size: 0.875rem;
  padding: 0.5rem;
  background: #f9fafb;
  border-radius: 4px;
}
.item-name { font-weight: 500; }
.item-notes { font-size: 0.75rem; color: #9ca3af; font-style: italic; }
.item-qty { color: #6b7280; text-align: center; }
.item-price { color: #6b7280; text-align: right; }
.item-subtotal { font-weight: 600; text-align: right; }

.detail-totals { display: flex; flex-direction: column; gap: 0.4rem; padding: 0.75rem; background: #f9fafb; border-radius: 6px; }
.total-row { display: flex; justify-content: space-between; font-size: 0.875rem; }
.total-row.discount { color: #22c55e; }
.total-row.points-redeem-row { color: #f59e0b; }
.total-row.grand { font-size: 1rem; font-weight: 700; border-top: 1px solid #e5e7eb; padding-top: 0.5rem; margin-top: 0.25rem; }

.promo-row { flex-wrap: wrap; gap: 0.25rem; }
.promo-code-badge {
  font-family: monospace;
  font-size: 0.7rem;
  background: #dcfce7;
  color: #166534;
  padding: 1px 5px;
  border-radius: 3px;
  margin-left: 4px;
}

.points-summary {
  display: flex;
  gap: 1rem;
  padding: 0.75rem 1rem;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: 6px;
  font-size: 0.875rem;
}
.points-earned { display: flex; align-items: center; gap: 0.4rem; color: #22c55e; font-weight: 600; }
.points-redeemed { display: flex; align-items: center; gap: 0.4rem; color: #f59e0b; font-weight: 600; }

/* Member card */
.member-card {
  display: flex;
  gap: 1rem;
  padding: 0.75rem;
  background: #f5f3ff;
  border: 1px solid #ddd6fe;
  border-radius: 8px;
}
.member-card-body { flex: 1; }
.member-card-name { font-weight: 700; font-size: 1rem; margin-bottom: 0.35rem; }
.member-card-meta { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; flex-wrap: wrap; }
.meta-item { font-size: 0.8rem; color: #6b7280; }
.member-card-points { display: flex; align-items: center; gap: 0.4rem; font-size: 0.875rem; color: #92400e; }

.cancelled-section .detail-section-title { color: #ef4444; }

/* End-to-End Time Summary */
.e2e-section .detail-section-title { color: #059669; }

.e2e-summary-card {
  background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
  border: 2px solid #10b981;
  border-radius: 12px;
  padding: 1.25rem;
  box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1);
}

.e2e-main {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.e2e-icon {
  width: 56px;
  height: 56px;
  background: #10b981;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.e2e-icon i {
  font-size: 1.5rem;
  color: white;
}

.e2e-content {
  flex: 1;
}

.e2e-label {
  font-size: 0.75rem;
  color: #065f46;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.e2e-value {
  font-size: 2rem;
  font-weight: 800;
  line-height: 1;
  margin-bottom: 0.25rem;
}

.e2e-value.good { color: #059669; }
.e2e-value.warn { color: #d97706; }
.e2e-value.slow { color: #dc2626; }

.e2e-meta {
  font-size: 0.8rem;
  color: #047857;
  font-weight: 500;
}

.e2e-breakdown {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid #10b98155;
}

.e2e-breakdown-title {
  font-size: 0.75rem;
  color: #065f46;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 700;
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.e2e-parallel-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  background: #fef3c7;
  color: #92400e;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: none;
  letter-spacing: normal;
}

.e2e-parallel-badge i {
  font-size: 0.65rem;
}

.e2e-steps {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.e2e-step {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  background: white;
  border-radius: 6px;
  border: 1px solid #d1fae5;
}

.e2e-step.e2e-step-station {
  background: linear-gradient(90deg, #ffffff 0%, #fef3c755 100%);
  border-left: 3px solid #f59e0b;
}

.e2e-step-icon {
  width: 32px;
  height: 32px;
  background: #10b981;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.e2e-step-icon i {
  font-size: 0.875rem;
  color: white;
}

.e2e-step-content {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.e2e-step-info {
  flex: 1;
}

.e2e-step-label {
  font-size: 0.875rem;
  color: #065f46;
  font-weight: 500;
}

.e2e-step-meta {
  font-size: 0.75rem;
  color: #6b7280;
  margin-top: 2px;
}

.e2e-step-duration {
  font-size: 0.875rem;
  font-weight: 700;
  color: #059669;
  flex-shrink: 0;
}

/* KDS Processing Time */
.kds-section .detail-section-title { color: #7c3aed; }

.kds-summary-cards {
  display: flex;
  gap: 0.75rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.kds-card {
  flex: 1;
  min-width: 100px;
  background: #f5f3ff;
  border: 1px solid #ddd6fe;
  border-radius: 8px;
  padding: 0.6rem 0.9rem;
  text-align: center;
}

.kds-card-label { font-size: 0.7rem; color: #7c3aed; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem; }
.kds-card-value { font-size: 1.25rem; font-weight: 800; color: #4c1d95; }
.kds-card-value.warn { color: #d97706; }

.kds-items { display: flex; flex-direction: column; gap: 0.5rem; }

.kds-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.6rem 0.75rem;
  background: #fafafa;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  flex-wrap: wrap;
}

.kds-item-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  min-width: 0;
}

.kds-station-badge {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 4px;
  border: 1px solid;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  white-space: nowrap;
}

.kds-item-name { font-weight: 600; font-size: 0.875rem; }
.kds-qty { font-size: 0.8rem; color: #9ca3af; }

.kds-item-timeline {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-shrink: 0;
}

.kds-time-block {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
  padding: 0.3rem 0.6rem;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  min-width: 60px;
  text-align: center;
}

.kds-time-block i { font-size: 0.7rem; margin-bottom: 1px; }
.kds-time-block small { font-size: 0.65rem; font-weight: 400; opacity: 0.8; }

.kds-time-block.good  { background: #dcfce7; color: #166534; }
.kds-time-block.warn  { background: #fef9c3; color: #854d0e; }
.kds-time-block.slow  { background: #fee2e2; color: #991b1b; }
.kds-time-block.serve { background: #eff6ff; color: #1d4ed8; }
.kds-time-block.none  { background: #f3f4f6; color: #9ca3af; font-weight: 400; }

.kds-arrow { color: #d1d5db; font-size: 0.75rem; }

/* ── QR Dialog ───────────────────────────────────────────────────────────────── */
.qr-dialog-content {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.qr-order-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.qr-order-code-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.1rem;
  font-weight: 700;
  color: #3b82f6;
  font-family: monospace;
}

.qr-order-code-badge i {
  font-size: 1.25rem;
}

.qr-code-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 1.5rem;
  background: #ffffff;
  border: 2px dashed #3b82f6;
  border-radius: 12px;
}

.qr-canvas {
  max-width: 280px;
  width: 100%;
  height: auto;
}

.qr-scan-hint {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0;
  text-align: center;
}

.qr-scan-hint i {
  font-size: 1rem;
  color: #3b82f6;
}

.qr-transaction-details {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
}

.qr-detail-section {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.qr-detail-title {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.875rem;
  font-weight: 700;
  color: #374151;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.qr-detail-title i {
  font-size: 1rem;
  color: #3b82f6;
}

.qr-detail-grid {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.qr-detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8rem;
  gap: 0.5rem;
}

.qr-label {
  color: #6b7280;
  font-weight: 500;
}

.qr-value {
  color: #374151;
  font-weight: 600;
}

.qr-items-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 150px;
  overflow-y: auto;
}

.qr-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem;
  background: white;
  border-radius: 6px;
  font-size: 0.8rem;
}

.qr-item-name {
  font-weight: 600;
  color: #374151;
}

.qr-item-qty {
  background: #eff6ff;
  color: #1d4ed8;
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  font-weight: 600;
  font-size: 0.75rem;
}

.qr-more-items {
  font-size: 0.75rem;
  color: #6b7280;
  padding: 0.5rem;
  text-align: center;
  font-style: italic;
}

.qr-total-section {
  background: white;
  padding: 0.75rem;
  border-radius: 8px;
  border-left: 4px solid #22c55e;
}

.qr-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.9rem;
}

.qr-total-label {
  font-weight: 700;
  color: #374151;
}

.qr-total-value {
  font-weight: 800;
  color: #22c55e;
  font-size: 1rem;
}

.qr-url-section {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem;
  background: #f0f9ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
}

.qr-url-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #1d4ed8;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.qr-url-box {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: white;
  border: 1px solid #dbeafe;
  border-radius: 6px;
  padding: 0.5rem;
}

.qr-url-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 0.75rem;
  color: #1d4ed8;
  font-family: monospace;
  word-break: break-all;
}

.qr-dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

/* ── Responsive Design ───────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .transaction-view { padding: 1rem; }
  
  .page-header {
    flex-direction: column;
    gap: 1rem;
  }
  
  .summary-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
  }
  
  .summary-value-sm {
    font-size: 1.25rem;
  }
  
  .filter-card {
    padding: 1rem;
  }
  
  .filter-row {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }
  
  .filter-group.flex-grow {
    grid-column: auto;
  }
  
  .filter-actions {
    width: 100%;
  }
  
  .filter-actions Button {
    flex: 1;
    min-width: auto;
  }
  
  .detail-grid {
    gap: 0.75rem;
  }
  
  .item-row {
    grid-template-columns: 1fr 30px 80px 80px;
    gap: 0.25rem;
    font-size: 0.8rem;
  }
  
  .e2e-summary-card {
    padding: 1rem;
  }
  
  .e2e-main {
    gap: 0.75rem;
  }
  
  .e2e-icon {
    width: 48px;
    height: 48px;
  }
  
  .e2e-value {
    font-size: 1.5rem;
  }
}

@media (max-width: 640px) {
  .transaction-view { padding: 0.75rem; }
  
  .filter-card {
    padding: 0.75rem;
    margin-bottom: 1rem;
  }
  
  .filter-header h3 {
    font-size: 0.85rem;
  }
  
  .filter-row {
    gap: 0.5rem;
  }
  
  .summary-card {
    padding: 0.75rem 0.875rem;
  }
  
  .summary-value {
    font-size: 1.25rem;
  }
  
  .summary-label {
    font-size: 0.7rem;
  }
}

/* ── Mobile Order Cards ──────────────────────────────────────────────────────── */
.order-cards {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 2rem;
  text-align: center;
  color: #6b7280;
}

.loading-state i {
  font-size: 1.5rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 3rem 1rem;
  text-align: center;
  color: #9ca3af;
}

.empty-state i {
  font-size: 2.5rem;
  color: #d1d5db;
}

.order-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.3s ease;
}

.order-card:active {
  transform: scale(0.98);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.order-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #f3f4f6;
}

.order-card-mid {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}

.order-card-info {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8rem;
  color: #6b7280;
}

.table-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  background: #eff6ff;
  color: #1d4ed8;
  padding: 0.2rem 0.4rem;
  border-radius: 4px;
  font-weight: 600;
}

.order-card-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.order-card-customer {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.8rem;
  color: #6b7280;
  flex: 1;
  min-width: 0;
}

.member-name {
  color: #8b5cf6;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.order-card-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}

.order-card-actions {
  display: flex;
  gap: 0.25rem;
}
</style>
