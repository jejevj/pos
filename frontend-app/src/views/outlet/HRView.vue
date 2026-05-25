<template>
  <div class="hr-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('hr.title') }}</h2>
        <p class="text-muted">{{ $t('hr.subtitle') }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
      <div class="tabs">
        <button 
          class="tab" 
          :class="{ active: activeTab === 'attendance' }"
          @click="activeTab = 'attendance'"
        >
          <i class="pi pi-clock"></i>
          {{ $t('hr.attendance') }}
        </button>
        <button 
          class="tab" 
          :class="{ active: activeTab === 'leave' }"
          @click="activeTab = 'leave'"
        >
          <i class="pi pi-calendar"></i>
          {{ $t('hr.leave') }}
        </button>
        <button 
          v-if="canManagePayroll"
          class="tab" 
          :class="{ active: activeTab === 'payroll' }"
          @click="activeTab = 'payroll'"
        >
          <i class="pi pi-wallet"></i>
          {{ $t('hr.payroll') }}
        </button>
        <button 
          class="tab" 
          :class="{ active: activeTab === 'kasbon' }"
          @click="activeTab = 'kasbon'"
        >
          <i class="pi pi-money-bill"></i>
          {{ $t('kasbon.title') }}
        </button>
        <button 
          class="tab" 
          :class="{ active: activeTab === 'employees' }"
          @click="activeTab = 'employees'"
        >
          <i class="pi pi-users"></i>
          {{ $t('hr.employees') }}
        </button>
        <button 
          class="tab" 
          :class="{ active: activeTab === 'settings' }"
          @click="activeTab = 'settings'"
        >
          <i class="pi pi-cog"></i>
          {{ $t('hr.payrollSettings') }}
        </button>
      </div>
    </div>

    <!-- Attendance Tab -->
    <div v-show="activeTab === 'attendance'" class="tab-content">
      <div class="attendance-section">
        <div class="section-header">
          <h3>{{ $t('hr.attendance') }}</h3>

        </div>

        <!-- Today Status -->
        <Card v-if="todayStatus.attendance" class="today-status">
          <template #content>
            <div class="status-grid">
              <div class="status-item">
                <i class="pi pi-sign-in"></i>
                <div>
                  <div class="status-label">{{ $t('hr.clockIn') }}</div>
                  <div class="status-value">{{ formatTime(todayStatus.attendance.clock_in) }}</div>
                </div>
              </div>
              <div class="status-item" v-if="todayStatus.attendance.clock_out">
                <i class="pi pi-sign-out"></i>
                <div>
                  <div class="status-label">{{ $t('hr.clockOut') }}</div>
                  <div class="status-value">{{ formatTime(todayStatus.attendance.clock_out) }}</div>
                </div>
              </div>
              <div class="status-item" v-if="todayStatus.attendance.work_hours">
                <i class="pi pi-clock"></i>
                <div>
                  <div class="status-label">{{ $t('hr.workHours') }}</div>
                  <div class="status-value">{{ todayStatus.attendance.work_hours }}h</div>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Attendance List -->
        <DataTable :value="attendances" :loading="loading" paginator :rows="15" stripedRows class="mt-4">
          <Column field="date" :header="$t('common.date')" sortable>
            <template #body="{ data }">
              {{ formatDate(data.date) }}
            </template>
          </Column>
          <Column field="user_name" :header="$t('users.name')" sortable />
          <Column field="clock_in" :header="$t('hr.clockIn')" sortable>
            <template #body="{ data }">
              {{ formatTime(data.clock_in) }}
            </template>
          </Column>
          <Column field="clock_out" :header="$t('hr.clockOut')" sortable>
            <template #body="{ data }">
              {{ formatTime(data.clock_out) }}
            </template>
          </Column>
          <Column :header="$t('hr.photos')" style="width: 120px">
            <template #body="{ data }">
              <div class="photo-thumbnails">
                <img 
                  v-if="data.clock_in_photo" 
                  :src="data.clock_in_photo" 
                  class="photo-thumb"
                  @click="viewAttendanceDetail(data)"
                  v-tooltip.top="$t('hr.clockInPhoto')"
                />
                <img 
                  v-if="data.clock_out_photo" 
                  :src="data.clock_out_photo" 
                  class="photo-thumb"
                  @click="viewAttendanceDetail(data)"
                  v-tooltip.top="$t('hr.clockOutPhoto')"
                />
                <span v-if="!data.clock_in_photo && !data.clock_out_photo" class="no-photo">-</span>
              </div>
            </template>
          </Column>
          <Column field="work_hours" :header="$t('hr.workHours')" sortable>
            <template #body="{ data }">
              {{ data.work_hours }}h
            </template>
          </Column>
          <Column field="overtime_hours" :header="$t('hr.overtimeHours')" sortable>
            <template #body="{ data }">
              {{ data.overtime_hours }}h
            </template>
          </Column>
          <Column field="status" :header="$t('common.status')">
            <template #body="{ data }">
              <Tag :value="$t(`hr.${data.status}`)" :severity="getStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 100px">
            <template #body="{ data }">
              <Button 
                icon="pi pi-eye" 
                text 
                rounded 
                size="small" 
                @click="viewAttendanceDetail(data)" 
                v-tooltip.top="$t('hr.viewDetails')" 
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Leave Tab -->
    <div v-show="activeTab === 'leave'" class="tab-content">
      <div class="leave-section">
        <div class="section-header">
          <h3>{{ $t('hr.leave') }}</h3>
          <Button :label="$t('hr.requestLeave')" icon="pi pi-plus" @click="openLeaveDialog" />
        </div>

        <!-- Leave Balance -->
        <div class="balance-cards" v-if="leaveBalance">
          <Card class="balance-card">
            <template #content>
              <div class="balance-info">
                <i class="pi pi-calendar"></i>
                <div>
                  <div class="balance-label">{{ $t('hr.totalLeave') }}</div>
                  <div class="balance-value">{{ leaveBalance.total_days }} {{ $t('common.days') }}</div>
                </div>
              </div>
            </template>
          </Card>
          <Card class="balance-card">
            <template #content>
              <div class="balance-info">
                <i class="pi pi-check-circle"></i>
                <div>
                  <div class="balance-label">{{ $t('hr.usedLeave') }}</div>
                  <div class="balance-value">{{ leaveBalance.used_days }} {{ $t('common.days') }}</div>
                </div>
              </div>
            </template>
          </Card>
          <Card class="balance-card">
            <template #content>
              <div class="balance-info">
                <i class="pi pi-clock"></i>
                <div>
                  <div class="balance-label">{{ $t('hr.remainingLeave') }}</div>
                  <div class="balance-value">{{ leaveBalance.remaining_days }} {{ $t('common.days') }}</div>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- Leave Requests List -->
        <DataTable :value="leaveRequests" :loading="loading" paginator :rows="15" stripedRows class="mt-4">
          <Column field="user_name" :header="$t('users.name')" sortable />
          <Column field="leave_type" :header="$t('hr.leaveType')" sortable>
            <template #body="{ data }">
              {{ $t(`hr.${data.leave_type}`) }}
            </template>
          </Column>
          <Column field="start_date" :header="$t('hr.startDate')" sortable>
            <template #body="{ data }">
              {{ formatDate(data.start_date) }}
            </template>
          </Column>
          <Column field="end_date" :header="$t('hr.endDate')" sortable>
            <template #body="{ data }">
              {{ formatDate(data.end_date) }}
            </template>
          </Column>
          <Column field="total_days" :header="$t('hr.totalDays')" sortable />
          <Column field="reason" :header="$t('hr.reason')" />
          <Column field="status" :header="$t('common.status')">
            <template #body="{ data }">
              <Tag :value="$t(`hr.${data.status}`)" :severity="getLeaveStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 150px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button v-if="data.status === 'pending'" icon="pi pi-check" text rounded size="small" severity="success"
                        @click="approveLeave(data)" v-tooltip.top="$t('hr.approve')" />
                <Button v-if="data.status === 'pending'" icon="pi pi-times" text rounded size="small" severity="danger"
                        @click="rejectLeave(data)" v-tooltip.top="$t('hr.reject')" />
                <Button icon="pi pi-eye" text rounded size="small" 
                        @click="viewLeaveDetail(data)" v-tooltip.top="$t('hr.viewDetails')" />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Payroll Tab -->
    <div v-show="activeTab === 'payroll'" class="tab-content">
      <div class="payroll-section">
        <div class="section-header">
          <h3>{{ $t('hr.payroll') }}</h3>
          <Button :label="$t('hr.generatePayroll')" icon="pi pi-plus" @click="openGenerateDialog" />
        </div>

        <!-- Payroll List -->
        <DataTable :value="payrolls" :loading="loading" paginator :rows="15" stripedRows>
          <Column :header="$t('hr.period')" sortable>
            <template #body="{ data }">
              {{ getMonthName(data.period_month) }} {{ data.period_year }}
            </template>
          </Column>
          <Column field="user_name" :header="$t('users.name')" sortable />
          <Column field="basic_salary" :header="$t('hr.basicSalary')" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.basic_salary) }}
            </template>
          </Column>
          <Column field="overtime_pay" :header="$t('hr.overtimePay')" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.overtime_pay) }}
            </template>
          </Column>
          <Column field="deductions" :header="$t('hr.deductions')" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.deductions) }}
            </template>
          </Column>
          <Column field="net_salary" :header="$t('hr.netSalary')" sortable>
            <template #body="{ data }">
              <strong>{{ formatCurrency(data.net_salary) }}</strong>
            </template>
          </Column>
          <Column field="status" :header="$t('common.status')">
            <template #body="{ data }">
              <Tag :value="$t(`hr.${data.status}`)" :severity="getPayrollStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 150px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button icon="pi pi-eye" text rounded size="small" 
                        @click="viewPayrollDetail(data)" v-tooltip.top="$t('hr.viewDetails')" />
                <Button v-if="data.status === 'draft'" icon="pi pi-pencil" text rounded size="small" 
                        @click="openEditPayrollDialog(data)" v-tooltip.top="$t('common.edit')" />
                <Button v-if="data.status === 'draft'" icon="pi pi-check" text rounded size="small" severity="success"
                        @click="approvePayroll(data)" v-tooltip.top="$t('hr.approve')" />
                <Button v-if="data.status === 'approved'" icon="pi pi-money-bill" text rounded size="small" severity="info"
                        @click="openMarkPaidDialog(data)" v-tooltip.top="$t('hr.markAsPaid')" />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Kasbon Tab -->
    <div v-show="activeTab === 'kasbon'" class="tab-content">
      <div class="kasbon-section">
        <div class="section-header">
          <h3>{{ $t('kasbon.title') }}</h3>
          <div class="header-actions">
            <Button :label="$t('kasbon.settings')" icon="pi pi-cog" severity="secondary" outlined @click="openKasbonSettings" />
            <Button :label="$t('kasbon.addKasbon')" icon="pi pi-plus" @click="openAddKasbonDialog" />
          </div>
        </div>

        <!-- Kasbon List -->
        <DataTable :value="kasbonList" :loading="loadingKasbon" paginator :rows="15" stripedRows>
          <Column field="request_date" :header="$t('kasbon.requestDate')" sortable>
            <template #body="{ data }">
              {{ formatDate(data.request_date) }}
            </template>
          </Column>
          <Column field="user_name" :header="$t('kasbon.employee')" sortable></Column>
          <Column field="amount" :header="$t('kasbon.amount')" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.amount) }}
            </template>
          </Column>
          <Column field="reason" :header="$t('kasbon.reason')"></Column>
          <Column field="status" :header="$t('kasbon.status')">
            <template #body="{ data }">
              <Tag :value="$t(`kasbon.${data.status}`)" :severity="getKasbonStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column field="repayment_status" :header="$t('kasbon.repaymentStatus')">
            <template #body="{ data }">
              <Tag :value="$t(`kasbon.${data.repayment_status}`)" :severity="data.repayment_status === 'paid' ? 'success' : 'warn'" />
            </template>
          </Column>
          <!-- Bukti Persetujuan -->
          <Column :header="$t('kasbon.approvalProof')" style="width: 130px">
            <template #body="{ data }">
              <a v-if="data.approval_proof" :href="data.approval_proof" target="_blank"
                 class="proof-link" style="display:flex;align-items:center;gap:4px;color:var(--p-primary-color);font-size:0.85rem;">
                <i class="pi pi-image" /> Lihat
              </a>
              <span v-else class="text-muted" style="font-size:0.8rem;">-</span>
            </template>
          </Column>
          <!-- Bukti Pelunasan -->
          <Column :header="$t('kasbon.repaymentProof')" style="width: 130px">
            <template #body="{ data }">
              <a v-if="data.repayment_proof" :href="data.repayment_proof" target="_blank"
                 class="proof-link" style="display:flex;align-items:center;gap:4px;color:var(--p-primary-color);font-size:0.85rem;">
                <i class="pi pi-image" /> Lihat
              </a>
              <span v-else class="text-muted" style="font-size:0.8rem;">-</span>
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 200px">
            <template #body="{ data }">
              <div class="action-buttons">
                <Button v-if="canApproveKasbon && data.status === 'pending'" icon="pi pi-check" text rounded severity="success" 
                        @click="approveKasbon(data)" v-tooltip.top="$t('kasbon.approve')" />
                <Button v-if="canApproveKasbon && data.status === 'pending'" icon="pi pi-times" text rounded severity="danger" 
                        @click="openRejectDialog(data)" v-tooltip.top="$t('kasbon.reject')" />
                <Button v-if="canApproveKasbon && data.status === 'approved' && data.repayment_status === 'unpaid'" 
                        icon="pi pi-dollar" text rounded severity="info" 
                        @click="openMarkKasbonPaidDialog(data)" v-tooltip.top="$t('kasbon.markAsPaid')" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="empty-state">
              <i class="pi pi-money-bill" style="font-size: 3rem; color: #9ca3af;"></i>
              <p>{{ $t('common.noData') }}</p>
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Employees Tab -->
    <div v-show="activeTab === 'employees'" class="tab-content">
      <div class="employees-section">
        <div class="section-header">
          <h3>{{ $t('hr.employeeManagement') }}</h3>
        </div>

        <!-- Employees List -->
        <DataTable :value="employees" :loading="loading" paginator :rows="15" stripedRows>
          <Column header="Foto" style="width: 70px">
            <template #body="{ data }">
              <img v-if="data.photo" :src="data.photo" alt="foto" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" />
              <span v-else><i class="pi pi-user" style="font-size:1.8rem;color:#9ca3af"></i></span>
            </template>
          </Column>
          <Column field="name" :header="$t('users.name')" sortable />
          <Column field="username" header="Username" sortable>
            <template #body="{ data }">
              <span v-if="data.username" class="font-mono text-sm">{{ data.username }}</span>
              <span v-else class="text-gray-400 text-sm">-</span>
            </template>
          </Column>
          <Column field="role_display" :header="$t('users.role')" sortable />
          <Column field="employee_code" :header="$t('hr.employeeCode')" sortable />
          <Column field="basic_salary" :header="$t('hr.basicSalary')" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.basic_salary || 0) }}
            </template>
          </Column>
          <Column field="overtime_rate" :header="$t('hr.overtimePay')" sortable>
            <template #body="{ data }">
              {{ formatCurrency(data.overtime_rate || 0) }}/h
            </template>
          </Column>
          <Column field="employment_type" :header="$t('hr.employmentType')" sortable>
            <template #body="{ data }">
              {{ getEmploymentTypeLabel(data.employment_type) }}
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="width: 100px">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded size="small" 
                      @click="openEmployeeDialog(data)" v-tooltip.top="$t('common.edit')" />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Settings Tab -->
    <div v-show="activeTab === 'settings'" class="tab-content">
      <div class="settings-section">
        <div class="section-header">
          <h3>{{ $t('hr.payrollSettings') }}</h3>
        </div>

        <Card class="mb-4">
          <template #content>
            <div class="form-grid">
              <div class="form-field">
                <label>{{ $t('hr.workDaysPerMonth') }}</label>
                <InputNumber v-model="payrollSettings.work_days_per_month" :min="1" :max="31" fluid />
              </div>
              <div class="form-field">
                <label>{{ $t('hr.workHoursPerDay') }}</label>
                <InputNumber v-model="payrollSettings.work_hours_per_day" :min="1" :max="24" :minFractionDigits="1" :maxFractionDigits="1" fluid />
              </div>
              <div class="form-field">
                <label>{{ $t('hr.overtimeMultiplier') }}</label>
                <InputNumber v-model="payrollSettings.overtime_multiplier" :min="1" :max="5" :minFractionDigits="1" :maxFractionDigits="1" fluid />
              </div>
              <div class="form-field">
                <label>{{ $t('hr.lateToleranceMinutes') }}</label>
                <InputNumber v-model="payrollSettings.late_tolerance_minutes" :min="0" :max="60" fluid />
              </div>
              <div class="form-field">
                <label>{{ $t('hr.annualLeaveDays') }}</label>
                <InputNumber v-model="payrollSettings.annual_leave_days" :min="0" :max="30" fluid />
              </div>
              <div class="form-field">
                <label>{{ $t('hr.sickLeaveDays') }}</label>
                <InputNumber v-model="payrollSettings.sick_leave_days" :min="0" :max="30" fluid />
              </div>
              <div class="form-field">
                <label>{{ $t('hr.taxPercentage') }} (%)</label>
                <InputNumber v-model="payrollSettings.tax_percentage" :min="0" :max="100" :minFractionDigits="1" :maxFractionDigits="1" fluid />
              </div>
            </div>
          </template>
        </Card>

        <div class="section-header">
          <h3>{{ $t('hr.attendanceLocation') }}</h3>
        </div>

        <Card>
          <template #content>
            <div class="attendance-location-section">
              <div class="location-actions">
                <div class="search-field-wrapper">
                  <div class="search-field">
                    <InputText 
                      v-model="searchAddress" 
                      :placeholder="$t('hr.searchAddress')"
                      @input="onSearchInput"
                      @keyup.enter="searchLocation"
                      @blur="closeSuggestions"
                      fluid
                    />
                    <Button 
                      icon="pi pi-search" 
                      @click="searchLocation"
                      :loading="searchingLocation"
                    />
                  </div>
                  
                  <!-- Search Suggestions Dropdown -->
                  <div v-if="showSuggestions && searchSuggestions.length > 0" class="search-suggestions">
                    <div 
                      v-for="(suggestion, index) in searchSuggestions" 
                      :key="index"
                      class="suggestion-item"
                      @mousedown="selectSuggestion(suggestion)"
                    >
                      <i class="pi pi-map-marker"></i>
                      <div class="suggestion-content">
                        <div class="suggestion-name">{{ suggestion.display_name }}</div>
                        <div class="suggestion-type">{{ suggestion.type }}</div>
                      </div>
                    </div>
                  </div>
                  
                  <small class="search-hint">
                    <i class="pi pi-info-circle"></i>
                    {{ $t('hr.searchAddressHint') }}
                  </small>
                </div>
                
                <Button 
                  :label="$t('hr.useMyLocation')" 
                  icon="pi pi-map-marker" 
                  severity="secondary"
                  @click="useCurrentLocation"
                  :loading="gettingLocation"
                />
              </div>

              <div class="form-field">
                <label>{{ $t('hr.attendanceRadius') }} ({{ $t('common.meters') }})</label>
                <InputNumber v-model="payrollSettings.attendance_radius" :min="10" :max="1000" fluid @input="updateCircleRadius" />
                <small class="text-muted">{{ $t('hr.attendanceRadiusHint') }}</small>
              </div>

              <div class="map-container">
                <div ref="mapElement" class="leaflet-map"></div>
              </div>

              <div class="map-instructions">
                <i class="pi pi-info-circle"></i>
                <span>{{ $t('hr.mapInstructions') }}</span>
              </div>
            </div>

            <div class="form-actions">
              <Button :label="$t('common.save')" @click="savePayrollSettings" :loading="saving" />
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- Leave Request Dialog -->
    <Dialog v-model:visible="leaveDialogVisible" :header="$t('hr.requestLeave')" modal :style="{ width: '500px' }">
      <div class="form-grid">
        <div class="form-field full-width">
          <label>{{ $t('hr.leaveType') }} *</label>
          <Select v-model="leaveForm.leave_type" :options="leaveTypes" optionLabel="label" optionValue="value" 
                  :placeholder="$t('hr.leaveType')" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.startDate') }} *</label>
          <DatePicker v-model="leaveForm.start_date" dateFormat="yy-mm-dd" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.endDate') }} *</label>
          <DatePicker v-model="leaveForm.end_date" dateFormat="yy-mm-dd" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('hr.reason') }} *</label>
          <Textarea v-model="leaveForm.reason" rows="3" fluid />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="leaveDialogVisible = false" />
        <Button :label="$t('common.submit')" @click="submitLeaveRequest" :loading="saving" />
      </template>
    </Dialog>

    <!-- Leave Detail Dialog -->
    <Dialog v-model:visible="leaveDetailVisible" :header="$t('shift.leaveDetails')" modal :style="{ width: '500px' }">
      <div v-if="selectedLeave" class="detail-content">
        <div class="detail-item">
          <span class="detail-label">{{ $t('users.name') }}:</span>
          <span class="detail-value">{{ selectedLeave.user_name }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('hr.leaveType') }}:</span>
          <span class="detail-value">{{ $t(`hr.${selectedLeave.leave_type}`) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('hr.startDate') }}:</span>
          <span class="detail-value">{{ formatDate(selectedLeave.start_date) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('hr.endDate') }}:</span>
          <span class="detail-value">{{ formatDate(selectedLeave.end_date) }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('hr.totalDays') }}:</span>
          <span class="detail-value">{{ selectedLeave.total_days }} {{ $t('common.days') }}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('common.status') }}:</span>
          <Tag :value="$t(`hr.${selectedLeave.status}`)" :severity="getLeaveStatusSeverity(selectedLeave.status)" />
        </div>
        <div class="detail-item">
          <span class="detail-label">{{ $t('hr.reason') }}:</span>
          <span class="detail-value">{{ selectedLeave.reason }}</span>
        </div>
        <div v-if="selectedLeave.review_notes" class="detail-item">
          <span class="detail-label">{{ $t('hr.reviewNotes') }}:</span>
          <span class="detail-value">{{ selectedLeave.review_notes }}</span>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.close')" text @click="leaveDetailVisible = false" />
        <Button v-if="selectedLeave?.status === 'pending'" :label="$t('hr.approve')" severity="success" 
                @click="approveLeave(selectedLeave); leaveDetailVisible = false" />
        <Button v-if="selectedLeave?.status === 'pending'" :label="$t('hr.reject')" severity="danger" 
                @click="rejectLeave(selectedLeave); leaveDetailVisible = false" />
      </template>
    </Dialog>

    <!-- Employee Edit Dialog -->
    <Dialog v-model:visible="employeeDialogVisible" :header="$t('hr.editEmployee')" modal :style="{ width: '700px' }">
      <div class="form-grid">
        <!-- Foto Karyawan -->
        <div class="form-field full-width">
          <label>Foto Karyawan <span class="text-gray-400 text-sm">(opsional)</span></label>
          <div class="flex items-center gap-4">
            <img v-if="employeeForm.photoPreview || employeeForm.photo" 
                 :src="employeeForm.photoPreview || employeeForm.photo" 
                 alt="foto" 
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb" />
            <div v-else style="width:72px;height:72px;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center">
              <i class="pi pi-user" style="font-size:2rem;color:#9ca3af"></i>
            </div>
            <div class="flex flex-col gap-2">
              <input ref="photoInputRef" type="file" accept="image/*" style="display:none" @change="onPhotoSelected" />
              <Button label="Pilih Foto" icon="pi pi-upload" size="small" outlined @click="photoInputRef.click()" />
              <Button v-if="employeeForm.photoPreview" label="Hapus" icon="pi pi-times" size="small" severity="danger" outlined @click="clearPhoto" />
            </div>
          </div>
        </div>
        <!-- Username -->
        <div class="form-field">
          <label>Username <span class="text-gray-400 text-sm">(untuk login)</span></label>
          <InputText v-model="employeeForm.username" placeholder="contoh: budi123" fluid />
          <small class="text-gray-400">Kosongkan jika tidak ingin mengubah</small>
        </div>
        <div class="form-field">
          <label>{{ $t('hr.employeeCode') }}</label>
          <InputText v-model="employeeForm.employee_code" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.joinDate') }}</label>
          <DatePicker v-model="employeeForm.join_date" dateFormat="yy-mm-dd" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.employmentType') }}</label>
          <Select v-model="employeeForm.employment_type" :options="employmentTypes" optionLabel="label" optionValue="value" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.basicSalary') }}</label>
          <InputNumber v-model="employeeForm.basic_salary" mode="currency" currency="IDR" locale="id-ID" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.overtimePay') }} (per jam)</label>
          <InputNumber v-model="employeeForm.overtime_rate" mode="currency" currency="IDR" locale="id-ID" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.bankName') }}</label>
          <InputText v-model="employeeForm.bank_name" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.bankAccount') }}</label>
          <InputText v-model="employeeForm.bank_account" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.bankAccountName') }}</label>
          <InputText v-model="employeeForm.bank_account_name" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('common.address') }}</label>
          <Textarea v-model="employeeForm.address" rows="2" fluid />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="employeeDialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveEmployee" :loading="saving" />
      </template>
    </Dialog>

    <!-- Generate Payroll Dialog -->
    <Dialog v-model:visible="generateDialogVisible" :header="$t('hr.generatePayroll')" modal :style="{ width: '500px' }">
      <div class="form-grid">
        <div class="form-field">
          <label>{{ $t('hr.selectMonth') }} *</label>
          <Select v-model="generateForm.month" :options="monthOptions" optionLabel="label" optionValue="value" 
                  :placeholder="$t('hr.selectMonth')" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.selectYear') }} *</label>
          <InputNumber v-model="generateForm.year" :min="2020" :max="2030" fluid />
        </div>
      </div>
      <Message severity="info" :closable="false">
        {{ $t('hr.generatePayrollHint') }}
      </Message>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="generateDialogVisible = false" />
        <Button :label="$t('hr.generatePayroll')" @click="generatePayroll" :loading="saving" />
      </template>
    </Dialog>

    <!-- Edit Payroll Dialog -->
    <Dialog v-model:visible="editPayrollDialogVisible" :header="$t('hr.editPayroll')" modal :style="{ width: '600px' }">
      <div class="form-grid">
        <div class="form-field">
          <label>{{ $t('hr.allowances') }}</label>
          <InputNumber v-model="payrollForm.allowances" mode="currency" currency="IDR" locale="id-ID" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.bonuses') }}</label>
          <InputNumber v-model="payrollForm.bonuses" mode="currency" currency="IDR" locale="id-ID" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.deductions') }}</label>
          <InputNumber v-model="payrollForm.deductions" mode="currency" currency="IDR" locale="id-ID" fluid />
        </div>
        <div class="form-field full-width">
          <label>{{ $t('common.notes') }}</label>
          <Textarea v-model="payrollForm.notes" rows="3" fluid />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="editPayrollDialogVisible = false" />
        <Button :label="$t('common.save')" @click="savePayroll" :loading="saving" />
      </template>
    </Dialog>

    <!-- Mark as Paid Dialog -->
    <Dialog v-model:visible="markPaidDialogVisible" :header="$t('hr.markAsPaid')" modal :style="{ width: '500px' }">
      <div class="form-grid">
        <div class="form-field">
          <label>{{ $t('hr.paymentDate') }} *</label>
          <DatePicker v-model="paidForm.payment_date" dateFormat="yy-mm-dd" fluid />
        </div>
        <div class="form-field">
          <label>{{ $t('hr.paymentMethod') }} *</label>
          <Select v-model="paidForm.payment_method" :options="paymentMethodOptions" optionLabel="label" optionValue="value" 
                  :placeholder="$t('hr.paymentMethod')" fluid />
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="markPaidDialogVisible = false" />
        <Button :label="$t('hr.markAsPaid')" @click="markPayrollAsPaid" :loading="saving" />
      </template>
    </Dialog>

    <!-- Payroll Detail Dialog -->
    <Dialog v-model:visible="detailDialogVisible" :header="$t('hr.payrollDetails')" modal :style="{ width: '800px' }">
      <div v-if="selectedPayroll" class="payroll-detail">
        <div class="detail-section">
          <h4>{{ $t('hr.employeeInfo') }}</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">{{ $t('users.name') }}:</span>
              <span class="detail-value">{{ selectedPayroll.user_name }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('hr.period') }}:</span>
              <span class="detail-value">{{ getMonthName(selectedPayroll.period_month) }} {{ selectedPayroll.period_year }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('common.status') }}:</span>
              <Tag :value="$t(`hr.${selectedPayroll.status}`)" :severity="getPayrollStatusSeverity(selectedPayroll.status)" />
            </div>
          </div>
        </div>

        <div class="detail-section">
          <h4>{{ $t('hr.attendanceSummary') }}</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">{{ $t('hr.workDays') }}:</span>
              <span class="detail-value">{{ selectedPayroll.work_days }} {{ $t('common.days') }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('hr.presentDays') }}:</span>
              <span class="detail-value">{{ selectedPayroll.present_days }} {{ $t('common.days') }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('hr.absentDays') }}:</span>
              <span class="detail-value">{{ selectedPayroll.absent_days }} {{ $t('common.days') }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('hr.overtimeHours') }}:</span>
              <span class="detail-value">{{ selectedPayroll.overtime_hours }}h</span>
            </div>
          </div>
        </div>

        <div class="detail-section">
          <h4>{{ $t('hr.salaryBreakdown') }}</h4>
          <div class="salary-breakdown">
            <div class="breakdown-item">
              <span>{{ $t('hr.basicSalary') }}</span>
              <span>{{ formatCurrency(selectedPayroll.basic_salary) }}</span>
            </div>
            <div class="breakdown-item">
              <span>{{ $t('hr.overtimePay') }}</span>
              <span>{{ formatCurrency(selectedPayroll.overtime_pay) }}</span>
            </div>
            <div class="breakdown-item">
              <span>{{ $t('hr.allowances') }}</span>
              <span>{{ formatCurrency(selectedPayroll.allowances) }}</span>
            </div>
            <div class="breakdown-item">
              <span>{{ $t('hr.bonuses') }}</span>
              <span>{{ formatCurrency(selectedPayroll.bonuses) }}</span>
            </div>
            <div class="breakdown-item subtotal">
              <span>{{ $t('hr.grossSalary') }}</span>
              <span>{{ formatCurrency(selectedPayroll.gross_salary) }}</span>
            </div>
            <div class="breakdown-item deduction">
              <span>{{ $t('hr.deductions') }}</span>
              <span>-{{ formatCurrency(selectedPayroll.deductions) }}</span>
            </div>
            <div class="breakdown-item total">
              <span>{{ $t('hr.netSalary') }}</span>
              <span>{{ formatCurrency(selectedPayroll.net_salary) }}</span>
            </div>
          </div>
        </div>

        <div v-if="selectedPayroll.notes" class="detail-section">
          <h4>{{ $t('common.notes') }}</h4>
          <p>{{ selectedPayroll.notes }}</p>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.close')" text @click="detailDialogVisible = false" />
      </template>
    </Dialog>

    <!-- Add Kasbon Dialog -->
    <Dialog v-model:visible="addKasbonDialogVisible" :header="$t('kasbon.addKasbon')" modal :style="{ width: '600px' }">
      <div class="form-content">
        <!-- Informasi pemohon: otomatis sesuai user yang login -->
        <div class="field" v-if="currentOutletMembership">
          <label>{{ $t('kasbon.employee') }}</label>
          <div class="p-inputtext p-component" style="background:var(--p-surface-100);color:var(--p-text-muted-color);cursor:default;">
            {{ currentOutletMembership.outlet_user_name || authStore.user?.name || '-' }}
          </div>
          <small class="text-muted">{{ $t('kasbon.selfSubmitNote', 'Pengajuan kasbon atas nama Anda sendiri') }}</small>
        </div>

        <!-- Employee Summary -->
        <Card v-if="employeeSummary" class="employee-summary-card">
          <template #content>
            <div class="summary-grid">
              <div class="summary-item">
                <span class="summary-label">{{ $t('kasbon.basicSalary') }}</span>
                <span class="summary-value">{{ formatCurrency(employeeSummary.basic_salary) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">{{ $t('kasbon.maxAllowed') }} ({{ employeeSummary.max_percentage }}%)</span>
                <span class="summary-value success">{{ formatCurrency(employeeSummary.max_allowed) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">{{ $t('kasbon.unpaidKasbon') }}</span>
                <span class="summary-value warn">{{ formatCurrency(employeeSummary.unpaid_kasbon) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">{{ $t('kasbon.availableKasbon') }}</span>
                <span class="summary-value primary">{{ formatCurrency(employeeSummary.available_kasbon) }}</span>
              </div>
            </div>
          </template>
        </Card>

        <div class="field">
          <label>{{ $t('kasbon.requestDate') }} *</label>
          <DatePicker v-model="kasbonForm.request_date" dateFormat="yy-mm-dd" showIcon fluid />
        </div>

        <div class="field">
          <label>{{ $t('kasbon.amount') }} *</label>
          <InputNumber v-model="kasbonForm.amount" mode="currency" currency="IDR" locale="id-ID" fluid />
          <small v-if="employeeSummary && kasbonForm.amount > employeeSummary.available_kasbon" class="p-error">
            {{ $t('kasbon.exceedsMaximum') }}
          </small>
        </div>

        <div class="field">
          <label>{{ $t('kasbon.reason') }}</label>
          <Textarea v-model="kasbonForm.reason" rows="3" fluid />
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="addKasbonDialogVisible = false" />
        <Button :label="$t('common.create')" @click="createKasbon" :loading="savingKasbon" />
      </template>
    </Dialog>

    <!-- Approve Kasbon Dialog -->
    <Dialog v-model:visible="approveDialogVisible" :header="$t('kasbon.approve')" modal :style="{ width: '500px' }">
      <div class="form-content">
        <Message severity="info" :closable="false">
          {{ $t('kasbon.approveHint') }}
        </Message>
        
        <div class="field">
          <label>{{ $t('kasbon.approvalProof') }} *</label>
          <FileUpload 
            mode="basic" 
            accept="image/*" 
            :maxFileSize="5000000"
            :chooseLabel="$t('kasbon.uploadProof')"
            @select="onApprovalProofSelect"
            :auto="false"
          />
          <small class="text-muted">{{ $t('kasbon.maxFileSize') }}</small>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="approveDialogVisible = false" />
        <Button :label="$t('kasbon.approve')" severity="success" @click="submitApproveKasbon" :loading="savingKasbon" />
      </template>
    </Dialog>

    <!-- Reject Kasbon Dialog -->
    <Dialog v-model:visible="rejectDialogVisible" :header="$t('kasbon.reject')" modal :style="{ width: '500px' }">
      <div class="form-content">
        <div class="field">
          <label>{{ $t('kasbon.rejectionReason') }} *</label>
          <Textarea v-model="rejectionReason" rows="3" fluid />
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="rejectDialogVisible = false" />
        <Button :label="$t('kasbon.reject')" severity="danger" @click="rejectKasbon" :loading="savingKasbon" />
      </template>
    </Dialog>

    <!-- Mark as Paid Dialog -->
    <Dialog v-model:visible="markPaidDialogVisible" :header="$t('kasbon.markAsPaid')" modal :style="{ width: '500px' }">
      <div class="form-content">
        <div class="field">
          <label>{{ $t('kasbon.repaymentAmount') }} *</label>
          <InputNumber v-model="repaymentForm.repayment_amount" mode="currency" currency="IDR" locale="id-ID" fluid />
        </div>

        <div class="field">
          <label>{{ $t('kasbon.repaymentDate') }} *</label>
          <DatePicker v-model="repaymentForm.repayment_date" dateFormat="yy-mm-dd" showIcon fluid />
        </div>

        <div class="field">
          <label>{{ $t('kasbon.repaymentProof') }} *</label>
          <FileUpload 
            mode="basic" 
            accept="image/*" 
            :maxFileSize="5000000"
            :chooseLabel="$t('kasbon.uploadProof')"
            @select="onRepaymentProofSelect"
            :auto="false"
          />
          <small class="text-muted">{{ $t('kasbon.maxFileSize') }}</small>
        </div>

        <div class="field">
          <label>{{ $t('common.notes') }}</label>
          <Textarea v-model="repaymentForm.notes" rows="2" fluid />
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="markPaidDialogVisible = false" />
        <Button :label="$t('kasbon.markAsPaid')" @click="markKasbonAsPaid" :loading="savingKasbon" />
      </template>
    </Dialog>

    <!-- Kasbon Settings Dialog -->
    <Dialog v-model:visible="kasbonSettingsDialogVisible" :header="$t('kasbon.settings')" modal :style="{ width: '500px' }">
      <div class="form-content">
        <div class="field">
          <label>{{ $t('kasbon.maxPercentage') }} *</label>
          <InputNumber v-model="kasbonSettings.max_percentage" suffix="%" :min="0" :max="100" fluid />
          <small class="text-muted">{{ $t('kasbon.maxPercentageHint') }}</small>
        </div>

        <div class="field">
          <div class="checkbox-field">
            <Checkbox v-model="kasbonSettings.require_approval" :binary="true" inputId="require_approval" />
            <label for="require_approval">{{ $t('kasbon.requireApproval') }}</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.cancel')" text @click="kasbonSettingsDialogVisible = false" />
        <Button :label="$t('common.save')" @click="saveKasbonSettings" :loading="savingKasbon" />
      </template>
    </Dialog>

    <!-- Attendance Detail Dialog -->
    <Dialog v-model:visible="attendanceDetailVisible" :header="$t('hr.attendanceDetails')" modal :style="{ width: '800px' }">
      <div v-if="selectedAttendance" class="attendance-detail">
        <div class="detail-section">
          <h4>{{ $t('hr.employeeInfo') }}</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">{{ $t('users.name') }}:</span>
              <span class="detail-value">{{ selectedAttendance.user_name }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('common.date') }}:</span>
              <span class="detail-value">{{ formatDate(selectedAttendance.date) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('common.status') }}:</span>
              <Tag :value="$t(`hr.${selectedAttendance.status}`)" :severity="getStatusSeverity(selectedAttendance.status)" />
            </div>
          </div>
        </div>

        <div class="detail-section">
          <h4>{{ $t('hr.clockInOut') }}</h4>
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">{{ $t('hr.clockIn') }}:</span>
              <span class="detail-value">{{ formatTime(selectedAttendance.clock_in) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('hr.clockOut') }}:</span>
              <span class="detail-value">{{ formatTime(selectedAttendance.clock_out) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('hr.workHours') }}:</span>
              <span class="detail-value">{{ selectedAttendance.work_hours }}h</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">{{ $t('hr.overtimeHours') }}:</span>
              <span class="detail-value">{{ selectedAttendance.overtime_hours }}h</span>
            </div>
          </div>
        </div>

        <div class="detail-section">
          <h4>{{ $t('hr.photos') }}</h4>
          <div class="photos-grid">
            <div v-if="selectedAttendance.clock_in_photo" class="photo-item">
              <div class="photo-label">{{ $t('hr.clockInPhoto') }}</div>
              <img :src="selectedAttendance.clock_in_photo" class="photo-full" />
            </div>
            <div v-if="selectedAttendance.clock_out_photo" class="photo-item">
              <div class="photo-label">{{ $t('hr.clockOutPhoto') }}</div>
              <img :src="selectedAttendance.clock_out_photo" class="photo-full" />
            </div>
            <div v-if="!selectedAttendance.clock_in_photo && !selectedAttendance.clock_out_photo" class="no-photos">
              <i class="pi pi-image"></i>
              <p>{{ $t('hr.noPhotos') }}</p>
            </div>
          </div>
        </div>

        <div class="detail-section">
          <h4>{{ $t('hr.locationInfo') }}</h4>
          <div class="location-grid">
            <div v-if="selectedAttendance.clock_in_location" class="location-item">
              <div class="location-label">{{ $t('hr.clockInLocation') }}</div>
              <div class="location-details">
                <div class="location-coord">
                  <i class="pi pi-map-marker"></i>
                  <span>{{ getLocationCoords(selectedAttendance.clock_in_location) }}</span>
                </div>
                <div class="location-accuracy">
                  <i class="pi pi-info-circle"></i>
                  <span>{{ $t('hr.accuracy') }}: {{ getLocationAccuracy(selectedAttendance.clock_in_location) }}m</span>
                </div>
              </div>
            </div>
            <div v-if="selectedAttendance.clock_out_location" class="location-item">
              <div class="location-label">{{ $t('hr.clockOutLocation') }}</div>
              <div class="location-details">
                <div class="location-coord">
                  <i class="pi pi-map-marker"></i>
                  <span>{{ getLocationCoords(selectedAttendance.clock_out_location) }}</span>
                </div>
                <div class="location-accuracy">
                  <i class="pi pi-info-circle"></i>
                  <span>{{ $t('hr.accuracy') }}: {{ getLocationAccuracy(selectedAttendance.clock_out_location) }}m</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="selectedAttendance.clock_in_notes || selectedAttendance.clock_out_notes" class="detail-section">
          <h4>{{ $t('common.notes') }}</h4>
          <div class="notes-grid">
            <div v-if="selectedAttendance.clock_in_notes" class="note-item">
              <strong>{{ $t('hr.clockIn') }}:</strong>
              <p>{{ selectedAttendance.clock_in_notes }}</p>
            </div>
            <div v-if="selectedAttendance.clock_out_notes" class="note-item">
              <strong>{{ $t('hr.clockOut') }}:</strong>
              <p>{{ selectedAttendance.clock_out_notes }}</p>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button :label="$t('common.close')" text @click="attendanceDetailVisible = false" />
      </template>
    </Dialog>

    <!-- Location Permission Dialog -->
    <Dialog v-model:visible="locationPermissionDialogVisible" :header="$t('hr.locationPermissionRequired')" modal :closable="false" :style="{ width: '550px' }">
      <div class="permission-content">
        <div class="permission-item">
          <i class="pi pi-map-marker permission-icon"></i>
          <div>
            <h4>{{ $t('hr.locationAccess') }}</h4>
            <p>{{ $t('hr.locationAccessDesc') }}</p>
          </div>
        </div>
        <Message severity="info" :closable="false">
          {{ $t('hr.locationUsageHint') }}
        </Message>
        <Message severity="warn" :closable="false" class="mt-3">
          <div class="permission-instructions">
            <strong>⚠️ {{ $t('hr.locationAccuracyNote') }}</strong>
            <ul>
              <li>{{ $t('hr.accuracyNote1') }}</li>
              <li>{{ $t('hr.accuracyNote2') }}</li>
              <li>{{ $t('hr.accuracyNote3') }}</li>
            </ul>
          </div>
        </Message>
        <Message severity="warn" :closable="false" class="mt-3">
          <div class="permission-instructions">
            <strong>{{ $t('hr.permissionInstructions') }}</strong>
            <ol>
              <li>{{ $t('hr.permissionStep1') }}</li>
              <li>{{ $t('hr.permissionStep2') }}</li>
              <li>{{ $t('hr.permissionStep3') }}</li>
            </ol>
          </div>
        </Message>
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="locationPermissionDialogVisible = false" />
        <Button :label="$t('hr.allowLocation')" @click="confirmUseCurrentLocation" :loading="gettingLocation" />
      </template>
    </Dialog>

    <!-- ── Dialog Clock Out (dengan alasan lembur jika overtime) ────────────── -->
    <Dialog v-model:visible="clockOutDialogVisible" header="Clock Out" modal :style="{ width: '480px' }">
      <div class="form-content">
        <Message v-if="clockOutOvertimeHours > 0" severity="warn" :closable="false" class="mb-3">
          <strong>Anda akan lembur {{ clockOutOvertimeHours }} jam hari ini.</strong>
          Alasan lembur wajib diisi dan akan dikirim untuk approval manajer.
        </Message>
        <div class="field">
          <label>Catatan Clock Out</label>
          <Textarea v-model="clockOutForm.notes" rows="2" fluid :placeholder="$t('hr.clockOutNotes', 'Catatan opsional...')" />
        </div>
        <div v-if="clockOutOvertimeHours > 0" class="field">
          <label>Alasan Lembur <span class="p-error">*</span></label>
          <Textarea v-model="clockOutForm.overtime_reason" rows="3" fluid placeholder="Jelaskan alasan bekerja melebihi jam kerja standar..." />
        </div>
      </div>
      <template #footer>
        <Button label="Batal" text @click="clockOutDialogVisible = false" />
        <Button label="Clock Out" severity="warning" @click="submitClockOut" :loading="clockingOut" />
      </template>
    </Dialog>


  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Card from 'primevue/card'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import Checkbox from 'primevue/checkbox'
import FileUpload from 'primevue/fileupload'
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId
const authStore = useAuthStore()

// Ambil outlet_user_id dari membership yang cocok dengan outlet ini
const currentOutletMembership = computed(() =>
  authStore.outletMemberships?.find(
    m => String(m.encoded_outlet_id) === String(outletId) ||
         String(m.outlet_id) === String(outletId)
  )
)

// outlet_user id (bukan global user id) — digunakan untuk endpoint absensi & kasbon
const userId = computed(() => currentOutletMembership.value?.outlet_user_id || null)

// Apakah user bisa manage payroll (generate, approve, mark-paid) dan lihat tab payroll
const canManagePayroll = computed(() =>
  authStore.isSuperAdmin ||
  currentOutletMembership.value?.roles?.some(r => r.is_owner || r.name === 'admin') ||
  authStore.hasOutletPermission(outletId, 'manage_payroll')
)

// Apakah user punya permission approve_kasbon di outlet ini
const canApproveKasbon = computed(() =>
  authStore.isSuperAdmin ||
  currentOutletMembership.value?.roles?.some(r => r.is_owner || r.name === 'admin') ||
  authStore.hasOutletPermission(outletId, 'approve_kasbon')
)

const activeTab = ref('attendance')
const loading = ref(false)
const saving = ref(false)
const attendances = ref([])
const leaveRequests = ref([])
const payrolls = ref([])
const employees = ref([])
const todayStatus = ref({ has_clocked_in: false, has_clocked_out: false, attendance: null })
const leaveBalance = ref(null)
const leaveDialogVisible = ref(false)
const leaveDetailVisible = ref(false)
const selectedLeave = ref(null)
const attendanceDetailVisible = ref(false)
const selectedAttendance = ref(null)
const employeeDialogVisible = ref(false)
const generateDialogVisible = ref(false)
const editPayrollDialogVisible = ref(false)
const markPaidDialogVisible = ref(false)
const detailDialogVisible = ref(false)
const selectedPayroll = ref(null)

const leaveForm = ref({
  leave_type: 'annual',
  start_date: null,
  end_date: null,
  reason: ''
})

const photoInputRef = ref(null)

const employeeForm = ref({
  id: null,
  username: '',
  photo: null,
  photoPreview: null,
  photoFile: null,
  employee_code: '',
  join_date: null,
  employment_type: 'full_time',
  basic_salary: 0,
  overtime_rate: 0,
  bank_name: '',
  bank_account: '',
  bank_account_name: '',
  address: ''
})

const onPhotoSelected = (event) => {
  const file = event.target.files[0]
  if (!file) return
  employeeForm.value.photoFile = file
  const reader = new FileReader()
  reader.onload = (e) => {
    employeeForm.value.photoPreview = e.target.result
  }
  reader.readAsDataURL(file)
}

const clearPhoto = () => {
  employeeForm.value.photoFile = null
  employeeForm.value.photoPreview = null
  if (photoInputRef.value) photoInputRef.value.value = ''
}

const payrollSettings = ref({
  work_days_per_month: 22,
  work_hours_per_day: 8,
  overtime_multiplier: 1.5,
  late_tolerance_minutes: 15,
  annual_leave_days: 12,
  sick_leave_days: 12,
  tax_percentage: 0,
  attendance_location_lat: null,
  attendance_location_lng: null,
  attendance_radius: 100
})

const mapElement = ref(null)
const searchAddress = ref('')
const searchingLocation = ref(false)
const gettingLocation = ref(false)
const locationPermissionDialogVisible = ref(false)
const searchSuggestions = ref([])
const showSuggestions = ref(false)
let map = null
let marker = null
let circle = null
let searchTimeout = null

const generateForm = ref({
  month: new Date().getMonth() + 1,
  year: new Date().getFullYear()
})

const payrollForm = ref({
  id: null,
  allowances: 0,
  bonuses: 0,
  deductions: 0,
  notes: ''
})

const paidForm = ref({
  payment_date: new Date(),
  payment_method: 'bank_transfer',
  notes: ''
})

// Kasbon variables
const loadingKasbon = ref(false)
const savingKasbon = ref(false)
const kasbonList = ref([])
const addKasbonDialogVisible = ref(false)
const approveDialogVisible = ref(false)
const rejectDialogVisible = ref(false)
const kasbonSettingsDialogVisible = ref(false)
const selectedKasbon = ref(null)
const employeeSummary = ref(null)
const rejectionReason = ref('')
const approvalProof = ref(null)

const kasbonForm = ref({
  user_id: null,
  request_date: new Date(),
  amount: 0,
  reason: ''
})

const repaymentForm = ref({
  repayment_amount: 0,
  repayment_date: new Date(),
  repayment_proof: null,
  notes: ''
})

const kasbonSettings = ref({
  max_percentage: 50,
  require_approval: true
})

const leaveTypes = computed(() => [
  { label: t('hr.annual'), value: 'annual' },
  { label: t('hr.sick'), value: 'sick' },
  { label: t('hr.unpaid'), value: 'unpaid' },
  { label: t('hr.emergency'), value: 'emergency' },
])

const employmentTypes = computed(() => [
  { label: t('hr.fullTime'), value: 'full_time' },
  { label: t('hr.partTime'), value: 'part_time' },
  { label: t('hr.contract'), value: 'contract' },
])

const monthOptions = computed(() => [
  { label: 'Januari', value: 1 },
  { label: 'Februari', value: 2 },
  { label: 'Maret', value: 3 },
  { label: 'April', value: 4 },
  { label: 'Mei', value: 5 },
  { label: 'Juni', value: 6 },
  { label: 'Juli', value: 7 },
  { label: 'Agustus', value: 8 },
  { label: 'September', value: 9 },
  { label: 'Oktober', value: 10 },
  { label: 'November', value: 11 },
  { label: 'Desember', value: 12 },
])

const paymentMethodOptions = computed(() => [
  { label: t('hr.bankTransfer'), value: 'bank_transfer' },
  { label: t('hr.cash'), value: 'cash' },
])

const fetchAttendances = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/attendances`, { params: { user_id: userId.value } })
    attendances.value = response.data || []
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchTodayStatus = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/attendances/today/${userId.value}`)
    todayStatus.value = response.data
  } catch (error) {
    console.error('Failed to fetch today status:', error)
  }
}

const fetchLeaveRequests = async () => {
  loading.value = true
  try {
    // Fetch all leave requests (not filtered by user_id) so managers can see and approve them
    const response = await api.get(`/outlets/${outletId}/leave-requests`)
    leaveRequests.value = response.data || []
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchLeaveBalance = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/leave-requests/balance/${userId.value}`)
    leaveBalance.value = response.data[0] || null
  } catch (error) {
    console.error('Failed to fetch leave balance:', error)
  }
}

const fetchPayrolls = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/payrolls`, { params: { user_id: userId.value } })
    payrolls.value = response.data || []
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const handleClockIn = async () => {
  try {
    await api.post(`/outlets/${outletId}/attendances/clock-in`, { user_id: userId.value })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.clockInSuccess'), life: 3000 })
    fetchTodayStatus()
    fetchAttendances()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

// ── Clock Out dengan dialog alasan lembur ───────────────────────────────
const clockOutDialogVisible = ref(false)
const clockingOut = ref(false)
const clockOutOvertimeHours = ref(0)
const clockOutForm = ref({ notes: '', overtime_reason: '' })

const openClockOutDialog = async () => {
  // Hitung estimasi overtime berdasarkan jam clock-in vs sekarang
  clockOutOvertimeHours.value = 0
  if (todayStatus.value.attendance?.clock_in) {
    const clockIn   = new Date(todayStatus.value.attendance.clock_in)
    const now       = new Date()
    const totalMin  = Math.floor((now - clockIn) / 60000)
    const stdMin    = (payrollSettings.value.work_hours_per_day || 8) * 60
    const otMin     = Math.max(0, totalMin - stdMin)
    clockOutOvertimeHours.value = Math.floor(otMin / 60)
  }
  clockOutForm.value = { notes: '', overtime_reason: '' }
  clockOutDialogVisible.value = true
}

const submitClockOut = async () => {
  if (clockOutOvertimeHours.value > 0 && !clockOutForm.value.overtime_reason?.trim()) {
    toast.add({ severity: 'warn', summary: 'Perhatian', detail: 'Alasan lembur wajib diisi', life: 3000 })
    return
  }
  clockingOut.value = true
  try {
    const payload = {
      photo: todayStatus.value.attendance?.clock_in_photo || '',
      latitude: 0,
      longitude: 0,
      accuracy: 0,
      notes: clockOutForm.value.notes,
      overtime_reason: clockOutForm.value.overtime_reason || undefined,
    }
    // Gunakan geolocation jika tersedia
    if (navigator.geolocation) {
      await new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
          (pos) => {
            payload.latitude  = pos.coords.latitude
            payload.longitude = pos.coords.longitude
            payload.accuracy  = pos.coords.accuracy
            resolve()
          },
          () => resolve() // fallback jika ditolak
        )
      })
    }
    const res = await api.post(`/outlets/${outletId}/attendances/clock-out`, payload)
    clockOutDialogVisible.value = false
    if (res.data.needs_approval) {
      toast.add({ severity: 'info', summary: 'Clock Out Berhasil', detail: `Lembur ${res.data.overtime_hours} jam menunggu approval manajer`, life: 5000 })
    } else {
      toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.clockOutSuccess'), life: 3000 })
    }
    fetchTodayStatus()
    fetchAttendances()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 4000 })
  } finally {
    clockingOut.value = false
  }
}

// Alias lama untuk kompatibilitas (tidak dipakai setelah refactor)
const handleClockOut = openClockOutDialog



const openLeaveDialog = () => {
  leaveForm.value = { leave_type: 'annual', start_date: null, end_date: null, reason: '' }
  leaveDialogVisible.value = true
}

const submitLeaveRequest = async () => {
  if (!leaveForm.value.start_date || !leaveForm.value.end_date || !leaveForm.value.reason) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.fillRequired'), life: 3000 })
    return
  }

  saving.value = true
  try {
    await api.post(`/outlets/${outletId}/leave-requests`, {
      user_id: userId.value,
      ...leaveForm.value,
      start_date: formatDateForAPI(leaveForm.value.start_date),
      end_date: formatDateForAPI(leaveForm.value.end_date)
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.leaveRequestCreated'), life: 3000 })
    leaveDialogVisible.value = false
    fetchLeaveRequests()
    fetchLeaveBalance()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const approveLeave = async (leave) => {
  try {
    await api.put(`/outlets/${outletId}/leave-requests/${leave.id}/status`, {
      status: 'approved',
      review_notes: 'Approved'
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.leaveRequestApproved'), life: 3000 })
    fetchLeaveRequests()
    fetchLeaveBalance()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const rejectLeave = async (leave) => {
  try {
    await api.put(`/outlets/${outletId}/leave-requests/${leave.id}/status`, {
      status: 'rejected',
      review_notes: 'Rejected'
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.leaveRequestRejected'), life: 3000 })
    fetchLeaveRequests()
    fetchLeaveBalance()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const viewLeaveDetail = (leave) => {
  selectedLeave.value = leave
  leaveDetailVisible.value = true
}

const viewAttendanceDetail = (attendance) => {
  selectedAttendance.value = attendance
  attendanceDetailVisible.value = true
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta' })
}

const formatTime = (datetime) => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })
}

const formatDateForAPI = (date) => {
  if (!date) return null
  const d = new Date(date)
  return d.toISOString().split('T')[0]
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount)
}

const getMonthName = (month) => {
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
  return months[month - 1]
}

const getStatusSeverity = (status) => {
  const map = { present: 'success', late: 'warn', absent: 'danger', leave: 'info', half_day: 'secondary' }
  return map[status] || 'secondary'
}

const getLeaveStatusSeverity = (status) => {
  const map = { pending: 'warn', approved: 'success', rejected: 'danger', cancelled: 'secondary' }
  return map[status] || 'secondary'
}

const getPayrollStatusSeverity = (status) => {
  const map = { draft: 'secondary', approved: 'info', paid: 'success' }
  return map[status] || 'secondary'
}

const getEmploymentTypeLabel = (type) => {
  if (!type) return '-'
  const map = {
    'full_time': t('hr.fullTime'),
    'part_time': t('hr.partTime'),
    'contract': t('hr.contract')
  }
  return map[type] || type
}

const fetchEmployees = async () => {
  loading.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/employees`)
    employees.value = response.data || []
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchPayrollSettings = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/payroll-settings`)
    if (response.data) {
      payrollSettings.value = response.data
      // Init map after settings loaded
      if (activeTab.value === 'settings') {
        setTimeout(() => initMap(), 100)
      }
    }
  } catch (error) {
    console.error('Failed to fetch payroll settings:', error)
  }
}

const initMap = () => {
  if (!mapElement.value || map) return

  // Default location (Jakarta)
  const defaultLat = payrollSettings.value.attendance_location_lat || -6.2088
  const defaultLng = payrollSettings.value.attendance_location_lng || 106.8456

  map = L.map(mapElement.value).setView([defaultLat, defaultLng], 15)

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map)

  // Add draggable marker
  marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map)

  // Add circle for radius
  circle = L.circle([defaultLat, defaultLng], {
    color: '#3b82f6',
    fillColor: '#3b82f6',
    fillOpacity: 0.2,
    radius: payrollSettings.value.attendance_radius || 100
  }).addTo(map)

  // Update coordinates when marker is dragged
  marker.on('dragend', (e) => {
    const position = e.target.getLatLng()
    payrollSettings.value.attendance_location_lat = position.lat
    payrollSettings.value.attendance_location_lng = position.lng
    circle.setLatLng(position)
  })

  // Click on map to move marker
  map.on('click', (e) => {
    marker.setLatLng(e.latlng)
    circle.setLatLng(e.latlng)
    payrollSettings.value.attendance_location_lat = e.latlng.lat
    payrollSettings.value.attendance_location_lng = e.latlng.lng
  })
}

const updateCircleRadius = () => {
  if (circle) {
    circle.setRadius(payrollSettings.value.attendance_radius || 100)
  }
}

const onSearchInput = () => {
  // Clear previous timeout
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }

  // Hide suggestions if search is empty
  if (!searchAddress.value.trim()) {
    showSuggestions.value = false
    searchSuggestions.value = []
    return
  }

  // Debounce search
  searchTimeout = setTimeout(async () => {
    await fetchSearchSuggestions()
  }, 300)
}

const fetchSearchSuggestions = async () => {
  if (!searchAddress.value.trim()) {
    searchSuggestions.value = []
    showSuggestions.value = false
    return
  }

  try {
    // Using Nominatim with countrycodes=id to limit to Indonesia
    const response = await fetch(
      `https://nominatim.openstreetmap.org/search?` +
      `format=json&` +
      `q=${encodeURIComponent(searchAddress.value)}&` +
      `countrycodes=id&` + // Limit to Indonesia only
      `limit=5&` +
      `addressdetails=1`
    )
    const data = await response.json()

    if (data && data.length > 0) {
      searchSuggestions.value = data.map(item => ({
        display_name: item.display_name,
        lat: parseFloat(item.lat),
        lon: parseFloat(item.lon),
        type: item.type,
        address: item.address
      }))
      showSuggestions.value = true
    } else {
      searchSuggestions.value = []
      showSuggestions.value = false
    }
  } catch (error) {
    console.error('Suggestion fetch error:', error)
    searchSuggestions.value = []
    showSuggestions.value = false
  }
}

const selectSuggestion = (suggestion) => {
  searchAddress.value = suggestion.display_name
  showSuggestions.value = false

  // Update map
  if (map && marker && circle) {
    map.setView([suggestion.lat, suggestion.lon], 15)
    marker.setLatLng([suggestion.lat, suggestion.lon])
    circle.setLatLng([suggestion.lat, suggestion.lon])
    
    // Update settings
    payrollSettings.value.attendance_location_lat = suggestion.lat
    payrollSettings.value.attendance_location_lng = suggestion.lon

    toast.add({ 
      severity: 'success', 
      summary: t('messages.success'), 
      detail: t('hr.locationFound'), 
      life: 3000 
    })
  }
}

const closeSuggestions = () => {
  setTimeout(() => {
    showSuggestions.value = false
  }, 200)
}

const searchLocation = async () => {
  if (!searchAddress.value.trim()) {
    toast.add({ 
      severity: 'warn', 
      summary: t('messages.warning'), 
      detail: t('hr.enterAddress'), 
      life: 3000 
    })
    return
  }

  searchingLocation.value = true
  showSuggestions.value = false
  
  try {
    // Using Nominatim (OpenStreetMap) geocoding API with Indonesia filter
    const response = await fetch(
      `https://nominatim.openstreetmap.org/search?` +
      `format=json&` +
      `q=${encodeURIComponent(searchAddress.value)}&` +
      `countrycodes=id&` + // Limit to Indonesia only
      `limit=1`
    )
    const data = await response.json()

    if (data && data.length > 0) {
      const lat = parseFloat(data[0].lat)
      const lon = parseFloat(data[0].lon)

      // Update map
      if (map && marker && circle) {
        map.setView([lat, lon], 15)
        marker.setLatLng([lat, lon])
        circle.setLatLng([lat, lon])
        
        // Update settings
        payrollSettings.value.attendance_location_lat = lat
        payrollSettings.value.attendance_location_lng = lon

        toast.add({ 
          severity: 'success', 
          summary: t('messages.success'), 
          detail: t('hr.locationFound'), 
          life: 3000 
        })
      }
    } else {
      toast.add({ 
        severity: 'error', 
        summary: t('messages.error'), 
        detail: t('hr.locationNotFound'), 
        life: 3000 
      })
    }
  } catch (error) {
    console.error('Search error:', error)
    toast.add({ 
      severity: 'error', 
      summary: t('messages.error'), 
      detail: t('hr.searchError'), 
      life: 3000 
    })
  } finally {
    searchingLocation.value = false
  }
}

const useCurrentLocation = () => {
  // Show permission dialog first
  locationPermissionDialogVisible.value = true
}

const confirmUseCurrentLocation = () => {
  if (!navigator.geolocation) {
    toast.add({ 
      severity: 'error', 
      summary: t('messages.error'), 
      detail: t('hr.geolocationNotSupported'), 
      life: 3000 
    })
    locationPermissionDialogVisible.value = false
    return
  }

  gettingLocation.value = true

  const options = {
    enableHighAccuracy: true,
    timeout: 15000,
    maximumAge: 0
  }

  navigator.geolocation.getCurrentPosition(
    (position) => {
      const lat = position.coords.latitude
      const lon = position.coords.longitude
      const accuracy = position.coords.accuracy

      console.log('Location received:', { lat, lon, accuracy })

      // Warn if accuracy is poor (> 100m)
      if (accuracy > 100) {
        confirm.require({
          message: t('hr.lowAccuracyWarning', { accuracy: Math.round(accuracy) }),
          header: t('hr.lowAccuracyTitle'),
          icon: 'pi pi-exclamation-triangle',
          acceptLabel: t('hr.useAnyway'),
          rejectLabel: t('common.cancel'),
          accept: () => {
            updateMapLocation(lat, lon, accuracy)
          },
          reject: () => {
            gettingLocation.value = false
          }
        })
      } else {
        updateMapLocation(lat, lon, accuracy)
      }

      locationPermissionDialogVisible.value = false
    },
    (error) => {
      console.error('Geolocation error:', error)
      let errorMessage = t('hr.locationError')
      let severity = 'error'
      
      switch (error.code) {
        case error.PERMISSION_DENIED:
          errorMessage = t('hr.locationPermissionDenied')
          severity = 'warn'
          // Keep dialog open to show instructions
          gettingLocation.value = false
          return
        case error.POSITION_UNAVAILABLE:
          errorMessage = t('hr.locationUnavailable')
          break
        case error.TIMEOUT:
          errorMessage = t('hr.locationTimeout')
          break
      }
      
      toast.add({ 
        severity: severity, 
        summary: t('messages.error'), 
        detail: errorMessage, 
        life: 5000 
      })
      gettingLocation.value = false
      locationPermissionDialogVisible.value = false
    },
    options
  )
}

const updateMapLocation = (lat, lon, accuracy) => {
  // Update map
  if (map && marker && circle) {
    map.setView([lat, lon], 15)
    marker.setLatLng([lat, lon])
    circle.setLatLng([lat, lon])
    
    // Update settings
    payrollSettings.value.attendance_location_lat = lat
    payrollSettings.value.attendance_location_lng = lon

    const accuracyText = accuracy ? ` (${t('hr.accuracy')}: ${Math.round(accuracy)}m)` : ''
    
    toast.add({ 
      severity: 'success', 
      summary: t('messages.success'), 
      detail: t('hr.locationUpdated') + accuracyText, 
      life: 4000 
    })
  }

  gettingLocation.value = false
}

const openEmployeeDialog = (employee) => {
  employeeForm.value = {
    id: employee.id,
    username: employee.username || '',
    photo: employee.photo || null,
    photoPreview: null,
    photoFile: null,
    employee_code: employee.employee_code || '',
    join_date: employee.join_date ? new Date(employee.join_date) : null,
    employment_type: employee.employment_type || 'full_time',
    basic_salary: employee.basic_salary || 0,
    overtime_rate: employee.overtime_rate || 0,
    bank_name: employee.bank_name || '',
    bank_account: employee.bank_account || '',
    bank_account_name: employee.bank_account_name || '',
    address: employee.address || ''
  }
  if (photoInputRef.value) photoInputRef.value.value = ''
  employeeDialogVisible.value = true
}

const saveEmployee = async () => {
  saving.value = true
  try {
    const userId = employeeForm.value.id

    // 1. Simpan info karyawan
    await api.put(`/outlets/${outletId}/employees/${userId}/info`, {
      employee_code: employeeForm.value.employee_code,
      join_date: employeeForm.value.join_date ? formatDateForAPI(employeeForm.value.join_date) : null,
      employment_type: employeeForm.value.employment_type,
      basic_salary: employeeForm.value.basic_salary,
      overtime_rate: employeeForm.value.overtime_rate,
      bank_name: employeeForm.value.bank_name,
      bank_account: employeeForm.value.bank_account,
      bank_account_name: employeeForm.value.bank_account_name,
      address: employeeForm.value.address
    })

    // 2. Update username jika diisi
    if (employeeForm.value.username && employeeForm.value.username.trim()) {
      await api.put(`/outlets/${outletId}/employees/${userId}/username`, {
        username: employeeForm.value.username.trim()
      })
    }

    // 3. Upload foto jika ada file baru dipilih
    if (employeeForm.value.photoPreview) {
      await api.post(`/outlets/${outletId}/employees/${userId}/photo`, {
        photo: employeeForm.value.photoPreview // data URL base64
      })
    }

    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.employeeUpdated'), life: 3000 })
    employeeDialogVisible.value = false
    fetchEmployees()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const savePayrollSettings = async () => {
  saving.value = true
  try {
    await api.put(`/outlets/${outletId}/payroll-settings`, payrollSettings.value)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.settingsUpdated'), life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const openGenerateDialog = () => {
  generateForm.value = {
    month: new Date().getMonth() + 1,
    year: new Date().getFullYear()
  }
  generateDialogVisible.value = true
}

const generatePayroll = async () => {
  saving.value = true
  try {
    await api.post(`/outlets/${outletId}/payrolls/generate`, generateForm.value)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.payrollGenerated'), life: 3000 })
    generateDialogVisible.value = false
    fetchPayrolls()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const openEditPayrollDialog = (payroll) => {
  payrollForm.value = {
    id: payroll.id,
    allowances: payroll.allowances || 0,
    bonuses: payroll.bonuses || 0,
    deductions: payroll.deductions || 0,
    notes: payroll.notes || ''
  }
  editPayrollDialogVisible.value = true
}

const savePayroll = async () => {
  saving.value = true
  try {
    await api.put(`/outlets/${outletId}/payrolls/${payrollForm.value.id}`, {
      allowances: payrollForm.value.allowances,
      bonuses: payrollForm.value.bonuses,
      deductions: payrollForm.value.deductions,
      notes: payrollForm.value.notes
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.payrollUpdated'), life: 3000 })
    editPayrollDialogVisible.value = false
    fetchPayrolls()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const approvePayroll = async (payroll) => {
  try {
    await api.post(`/outlets/${outletId}/payrolls/${payroll.id}/approve`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.payrollApproved'), life: 3000 })
    fetchPayrolls()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const openMarkPaidDialog = (payroll) => {
  selectedPayroll.value = payroll
  paidForm.value = {
    payment_date: new Date(),
    payment_method: 'bank_transfer'
  }
  markPaidDialogVisible.value = true
}

const markPayrollAsPaid = async () => {
  saving.value = true
  try {
    await api.post(`/outlets/${outletId}/payrolls/${selectedPayroll.value.id}/mark-paid`, {
      payment_date: formatDateForAPI(paidForm.value.payment_date),
      payment_method: paidForm.value.payment_method
    })
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('hr.payrollPaid'), life: 3000 })
    markPaidDialogVisible.value = false
    fetchPayrolls()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    saving.value = false
  }
}

const viewPayrollDetail = async (payroll) => {
  try {
    const response = await api.get(`/outlets/${outletId}/payrolls/${payroll.id}`)
    selectedPayroll.value = response.data
    detailDialogVisible.value = true
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

onMounted(() => {
  fetchTodayStatus()
  fetchAttendances()
  fetchLeaveRequests()
  fetchLeaveBalance()
  fetchPayrolls()
  fetchEmployees()
  fetchPayrollSettings()
  fetchKasbonList()
  fetchKasbonSettings()
})

watch(activeTab, async (newTab) => {
  if (newTab === 'settings') {
    await nextTick()
    initMap()
  }
})

// Kasbon functions
const fetchKasbonList = async () => {
  loadingKasbon.value = true
  try {
    const response = await api.get(`/outlets/${outletId}/kasbon`)
    kasbonList.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loadingKasbon.value = false
  }
}

const fetchKasbonSettings = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/kasbon/settings`)
    kasbonSettings.value = response.data
  } catch (error) {
    console.error('Failed to fetch kasbon settings:', error)
  }
}

const onEmployeeChange = async () => {
  if (!kasbonForm.value.user_id) {
    employeeSummary.value = null
    return
  }
  
  try {
    const response = await api.get(`/outlets/${outletId}/kasbon/user/${kasbonForm.value.user_id}/summary`)
    employeeSummary.value = response.data
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  }
}

const openAddKasbonDialog = async () => {
  kasbonForm.value = {
    request_date: new Date(),
    amount: 0,
    reason: ''
  }
  employeeSummary.value = null
  // Langsung fetch summary untuk user yang login
  try {
    const response = await api.get(`/outlets/${outletId}/kasbon/user/${userId.value}/summary`)
    employeeSummary.value = response.data
  } catch (error) {
    console.error('Failed to fetch kasbon summary:', error)
  }
  addKasbonDialogVisible.value = true
}

const createKasbon = async () => {
  if (!kasbonForm.value.amount) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.fillRequired'), life: 3000 })
    return
  }

  savingKasbon.value = true
  try {
    const toLocalDate = (d) => {
      const y = d.getFullYear()
      const m = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${y}-${m}-${day}`
    }
    await api.post(`/outlets/${outletId}/kasbon`, {
      request_date: toLocalDate(kasbonForm.value.request_date),
      amount: kasbonForm.value.amount,
      reason: kasbonForm.value.reason
    })

    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('kasbon.kasbonCreated'), life: 3000 })
    addKasbonDialogVisible.value = false
    fetchKasbonList()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    savingKasbon.value = false
  }
}

const approveKasbon = async (kasbon) => {
  selectedKasbon.value = kasbon
  approvalProof.value = null
  approveDialogVisible.value = true
}

const submitApproveKasbon = async () => {
  if (!approvalProof.value) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('kasbon.uploadApprovalProof'), life: 3000 })
    return
  }

  savingKasbon.value = true
  try {
    const reader = new FileReader()
    reader.onload = async (e) => {
      try {
        await api.post(`/outlets/${outletId}/kasbon/${selectedKasbon.value.id}/approve`, {
          approval_proof: e.target.result
        })
        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('kasbon.kasbonApproved'), life: 3000 })
        approveDialogVisible.value = false
        fetchKasbonList()
      } catch (error) {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
      } finally {
        savingKasbon.value = false
      }
    }
    reader.readAsDataURL(approvalProof.value)
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
    savingKasbon.value = false
  }
}

const openRejectDialog = (kasbon) => {
  selectedKasbon.value = kasbon
  rejectionReason.value = ''
  rejectDialogVisible.value = true
}

const rejectKasbon = async () => {
  if (!rejectionReason.value) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.fillRequired'), life: 3000 })
    return
  }

  savingKasbon.value = true
  try {
    await api.post(`/outlets/${outletId}/kasbon/${selectedKasbon.value.id}/reject`, {
      rejection_reason: rejectionReason.value
    })

    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('kasbon.kasbonRejected'), life: 3000 })
    rejectDialogVisible.value = false
    fetchKasbonList()
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    savingKasbon.value = false
  }
}

const openMarkKasbonPaidDialog = (kasbon) => {
  selectedKasbon.value = kasbon
  repaymentForm.value = {
    repayment_amount: kasbon.amount,
    repayment_date: new Date(),
    repayment_proof: null,
    notes: ''
  }
  markPaidDialogVisible.value = true
}

const markKasbonAsPaid = async () => {
  if (!repaymentForm.value.repayment_proof) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('kasbon.uploadRepaymentProof'), life: 3000 })
    return
  }

  savingKasbon.value = true
  try {
    const reader = new FileReader()
    reader.onload = async (e) => {
      try {
        await api.post(`/outlets/${outletId}/kasbon/${selectedKasbon.value.id}/mark-paid`, {
          repayment_amount: repaymentForm.value.repayment_amount,
          repayment_date: repaymentForm.value.repayment_date.toISOString().split('T')[0],
          repayment_proof: e.target.result,
          notes: repaymentForm.value.notes
        })

        toast.add({ severity: 'success', summary: t('messages.success'), detail: t('kasbon.kasbonPaid'), life: 3000 })
        markPaidDialogVisible.value = false
        fetchKasbonList()
      } catch (error) {
        toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
      } finally {
        savingKasbon.value = false
      }
    }
    reader.readAsDataURL(repaymentForm.value.repayment_proof)
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
    savingKasbon.value = false
  }
}

const openKasbonSettings = () => {
  kasbonSettingsDialogVisible.value = true
}

const saveKasbonSettings = async () => {
  savingKasbon.value = true
  try {
    await api.put(`/outlets/${outletId}/kasbon/settings`, kasbonSettings.value)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('kasbon.settingsUpdated'), life: 3000 })
    kasbonSettingsDialogVisible.value = false
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    savingKasbon.value = false
  }
}

const getKasbonStatusSeverity = (status) => {
  const map = { pending: 'warn', approved: 'success', rejected: 'danger' }
  return map[status] || 'secondary'
}

const onApprovalProofSelect = (event) => {
  approvalProof.value = event.files[0]
}

const onRepaymentProofSelect = (event) => {
  repaymentForm.value.repayment_proof = event.files[0]
}

const getLocationCoords = (locationJson) => {
  if (!locationJson) return '-'
  try {
    const location = typeof locationJson === 'string' ? JSON.parse(locationJson) : locationJson
    return `${location.latitude.toFixed(6)}, ${location.longitude.toFixed(6)}`
  } catch (e) {
    return '-'
  }
}

const getLocationAccuracy = (locationJson) => {
  if (!locationJson) return '-'
  try {
    const location = typeof locationJson === 'string' ? JSON.parse(locationJson) : locationJson
    return Math.round(location.accuracy)
  } catch (e) {
    return '-'
  }
}

</script>

<style scoped>
.hr-view {
  padding: 1.5rem;
}

.page-header {
  margin-bottom: 1.5rem;
}

.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }

.tabs-container {
  margin-bottom: 1.5rem;
  border-bottom: 2px solid #e5e7eb;
}

.tabs {
  display: flex;
  gap: 0.5rem;
}

.tab {
  padding: 0.75rem 1.5rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: #6b7280;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: -2px;
}

.tab:hover { color: #3b82f6; }
.tab.active { color: #3b82f6; border-bottom-color: #3b82f6; }

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.section-header h3 { margin: 0; }

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.today-status {
  margin-bottom: 1.5rem;
}

.status-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.status-item {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.status-item i {
  font-size: 2rem;
  color: #3b82f6;
}

.status-label {
  font-size: 0.875rem;
  color: #6b7280;
}

.status-value {
  font-size: 1.25rem;
  font-weight: 600;
}

.balance-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}

.balance-card {
  border: 1px solid #e5e7eb;
}

.balance-info {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.balance-info i {
  font-size: 2rem;
  color: #3b82f6;
}

.balance-label {
  font-size: 0.875rem;
  color: #6b7280;
}

.balance-value {
  font-size: 1.5rem;
  font-weight: 700;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-field label { font-weight: 600; font-size: 0.875rem; }
.form-field.full-width { grid-column: 1 / -1; }

.form-actions {
  margin-top: 1.5rem;
  display: flex;
  justify-content: flex-end;
}

.action-buttons {
  display: flex;
  gap: 0.25rem;
}

.payroll-detail {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.detail-section h4 {
  margin: 0 0 1rem 0;
  font-size: 1rem;
  color: #1f2937;
  border-bottom: 2px solid #e5e7eb;
  padding-bottom: 0.5rem;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.detail-label {
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 500;
}

.detail-value {
  font-size: 1rem;
  color: #1f2937;
  font-weight: 600;
}

.salary-breakdown {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.breakdown-item {
  display: flex;
  justify-content: space-between;
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 6px;
}

.breakdown-item.subtotal {
  background: #eff6ff;
  font-weight: 600;
  color: #1e40af;
}

.breakdown-item.deduction {
  background: #fef2f2;
  color: #991b1b;
}

.breakdown-item.total {
  background: #f0fdf4;
  font-weight: 700;
  font-size: 1.125rem;
  color: #166534;
  border: 2px solid #22c55e;
}

.employee-summary-card {
  margin: 1rem 0;
  background: #f8fafc;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.summary-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.summary-label {
  font-size: 0.875rem;
  color: #64748b;
}

.summary-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: #1e293b;
}

.summary-value.success {
  color: #10b981;
}

.summary-value.warn {
  color: #f59e0b;
}

.summary-value.primary {
  color: #3b82f6;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  padding: 3rem;
  color: #6b7280;
}

.attendance-location-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.location-actions {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.search-field-wrapper {
  flex: 1;
  position: relative;
}

.search-field {
  display: flex;
  gap: 0.5rem;
}

.search-suggestions {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
  max-height: 300px;
  overflow-y: auto;
  z-index: 1000;
  margin-top: 0.25rem;
}

.suggestion-item {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  cursor: pointer;
  transition: background-color 0.2s;
  border-bottom: 1px solid #f3f4f6;
}

.suggestion-item:last-child {
  border-bottom: none;
}

.suggestion-item:hover {
  background-color: #f9fafb;
}

.suggestion-item i {
  color: #3b82f6;
  font-size: 1rem;
  margin-top: 0.25rem;
  flex-shrink: 0;
}

.suggestion-content {
  flex: 1;
  min-width: 0;
}

.suggestion-name {
  font-size: 0.875rem;
  color: #1f2937;
  font-weight: 500;
  margin-bottom: 0.25rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.suggestion-type {
  font-size: 0.75rem;
  color: #6b7280;
  text-transform: capitalize;
}

.search-hint {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #6b7280;
  font-size: 0.75rem;
  margin-top: 0.5rem;
}

.search-hint i {
  font-size: 0.875rem;
}

.permission-instructions {
  font-size: 0.875rem;
}

.permission-instructions strong {
  display: block;
  margin-bottom: 0.5rem;
}

.permission-instructions ol {
  margin: 0;
  padding-left: 1.5rem;
}

.permission-instructions ul {
  margin: 0;
  padding-left: 1.5rem;
}

.permission-instructions li {
  margin-bottom: 0.5rem;
  line-height: 1.5;
}

.mt-3 {
  margin-top: 1rem;
}

.map-container {
  width: 100%;
  height: 400px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e5e7eb;
}

.leaflet-map {
  width: 100%;
  height: 100%;
}

.map-instructions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: #eff6ff;
  border-radius: 6px;
  color: #1e40af;
  font-size: 0.875rem;
}

.map-instructions i {
  font-size: 1.25rem;
}

.mb-4 {
  margin-bottom: 1.5rem;
}

.photo-thumbnails {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.photo-thumb {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border-radius: 4px;
  cursor: pointer;
  border: 2px solid #e5e7eb;
  transition: all 0.2s;
}

.photo-thumb:hover {
  border-color: #3b82f6;
  transform: scale(1.1);
}

.no-photo {
  color: #9ca3af;
  font-size: 0.875rem;
}

.attendance-detail {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.photos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.photo-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.photo-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1f2937;
}

.photo-full {
  width: 100%;
  max-height: 400px;
  object-fit: contain;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
}

.no-photos {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  padding: 2rem;
  color: #9ca3af;
  grid-column: 1 / -1;
}

.no-photos i {
  font-size: 3rem;
}

.location-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
}

.location-item {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.location-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1f2937;
}

.location-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.location-coord,
.location-accuracy {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #4b5563;
}

.location-coord i {
  color: #3b82f6;
}

.location-accuracy i {
  color: #6b7280;
}

.notes-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.note-item {
  padding: 1rem;
  background: #f9fafb;
  border-radius: 8px;
  border-left: 4px solid #3b82f6;
}

.note-item strong {
  display: block;
  margin-bottom: 0.5rem;
  color: #1f2937;
  font-size: 0.875rem;
}

.note-item p {
  margin: 0;
  color: #4b5563;
  font-size: 0.875rem;
  line-height: 1.5;
}
</style>

