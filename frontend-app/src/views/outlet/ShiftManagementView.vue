    <template>
  <div class="shift-management-view">
    <!-- Loading Overlay -->
    <div v-if="deleting" class="loading-overlay">
      <div class="loading-content">
        <ProgressSpinner style="width: 60px; height: 60px" strokeWidth="4" animationDuration="1s" />
        <p class="loading-text">{{ $t('shift.deletingSchedules') }}</p>
      </div>
    </div>

    <div class="page-header">
      <div>
        <h2>{{ $t('shift.title') }}</h2>
        <p class="text-muted">{{ $t('shift.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <Button :label="$t('shift.viewAllDayOffs')" icon="pi pi-calendar-times" severity="secondary" outlined @click="openViewAllDayOffsDialog" />
      </div>
    </div>

    <!-- Month Navigation -->
    <div class="month-navigation">
      <Button icon="pi pi-chevron-left" text @click="previousMonth" />
      <h3>{{ currentMonthLabel }}</h3>
      <Button icon="pi pi-chevron-right" text @click="nextMonth" />
      <Button :label="$t('common.today')" text @click="goToToday" />
    </div>

    <!-- Legend -->
    <div class="legend">
      <div class="legend-item" v-for="shift in shifts" :key="shift.id">
        <div class="legend-color" :style="{ backgroundColor: shift.color }"></div>
        <span>{{ shift.name }} ({{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }})</span>
      </div>
      <div class="legend-item">
        <div class="legend-color" style="background-color: #94a3b8;"></div>
        <span>{{ $t('shift.dayOff') }}</span>
      </div>
      <div class="legend-item">
        <div class="legend-color" style="background-color: #ef4444;"></div>
        <span>{{ $t('shift.onLeave') }}</span>
      </div>
    </div>

    <!-- Calendar View -->
    <div class="calendar-container">
      <div class="calendar-header">
        <div class="calendar-cell" v-for="day in weekDays" :key="day">
          {{ day }}
        </div>
      </div>
      
      <div class="calendar-body">
        <div 
          v-for="(week, weekIndex) in calendarWeeks" 
          :key="weekIndex" 
          class="calendar-week"
        >
          <div 
            v-for="(day, dayIndex) in week" 
            :key="dayIndex"
            class="calendar-day"
            :class="{ 
              'other-month': !day.isCurrentMonth,
              'today': day.isToday,
              'weekend': day.isWeekend
            }"
          >
            <div class="day-header">
              <span class="day-number">{{ day.date.getDate() }}</span>
              <div class="day-header-actions">
                <Button 
                  icon="pi pi-plus" 
                  text 
                  rounded 
                  size="small" 
                  severity="secondary"
                  @click.stop="openDayScheduleDialog(day)"
                  class="add-schedule-btn"
                  v-tooltip.top="$t('shift.manageSchedule')"
                />
                <Button 
                  icon="pi pi-trash" 
                  text 
                  rounded 
                  size="small" 
                  severity="danger"
                  @click.stop="clearDaySchedule(day)"
                  class="delete-schedule-btn"
                  v-if="(day.assignments && day.assignments.length > 0) || (day.dayOffs && day.dayOffs.length > 0)"
                  v-tooltip.top="$t('shift.clearDaySchedule')"
                />
              </div>
            </div>
            
            <div class="day-content" @click="openDayScheduleDialog(day)">
              <!-- Day Offs -->
              <div 
                v-for="dayOff in day.dayOffs" 
                :key="'dayoff-' + dayOff.id"
                class="day-off-item"
              >
                <i class="pi pi-moon"></i>
                <span>{{ dayOff.user_name }}</span>
              </div>

              <!-- Shift Assignments -->
              <div 
                v-for="assignment in day.assignments" 
                :key="assignment.id"
                class="assignment-item"
                :style="{ backgroundColor: assignment.shift_color + '20', borderLeft: `3px solid ${assignment.shift_color}` }"
              >
                <div class="assignment-name">{{ assignment.user_name }}</div>
                <div class="assignment-shift">{{ assignment.shift_name }}</div>
              </div>

              <!-- Leave Requests -->
              <div 
                v-for="(leave, index) in day.leaves" 
                :key="'leave-' + index"
                class="leave-item"
              >
                <i class="pi pi-calendar-times"></i>
                <span>{{ leave.user_name }}</span>
                <Tag v-if="leave.status === 'pending'" :value="$t('hr.pending')" severity="warn" size="small" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Day Schedule Dialog -->
    <Dialog v-model:visible="dayScheduleDialogVisible" :header="dayScheduleDialogTitle" modal :style="{ width: '900px' }" class="day-schedule-dialog">
      <div class="day-schedule-content">
        <!-- Shifts Section -->
        <div class="schedule-section">
          <h3>{{ $t('shift.shifts') }}</h3>
          <div v-for="shift in shifts" :key="shift.id" class="shift-section">
            <div class="shift-header" :style="{ borderLeftColor: shift.color }">
              <div>
                <strong>{{ shift.name }}</strong>
                <span class="shift-time">{{ formatTime(shift.start_time) }} - {{ formatTime(shift.end_time) }}</span>
              </div>
              <div class="shift-actions">
                <Button 
                  :label="$t('shift.addEmployee')" 
                  icon="pi pi-plus" 
                  size="small" 
                  @click="openAddEmployeeToShift(shift)"
                />
                <Button 
                  :label="$t('shift.clearAll')" 
                  icon="pi pi-trash" 
                  size="small" 
                  severity="danger"
                  outlined
                  @click="clearShiftAssignments(shift.id)"
                  v-if="getShiftAssignments(shift.id).length > 0"
                />
              </div>
            </div>
            <div class="shift-employees">
              <Chip 
                v-for="assignment in getShiftAssignments(shift.id)" 
                :key="assignment.id"
                :label="assignment.user_name"
                removable
                @remove="removeAssignment(assignment.id)"
              />
              <span v-if="getShiftAssignments(shift.id).length === 0" class="empty-text">
                {{ $t('shift.noEmployeesAssigned') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Day Off Section -->
        <div class="schedule-section">
          <div class="section-header">
            <h3>{{ $t('shift.dayOff') }}</h3>
            <div class="section-actions">
              <Button 
                :label="$t('shift.addDayOff')" 
                icon="pi pi-plus" 
                size="small" 
                severity="secondary"
                @click="openAddDayOff"
              />
              <Button 
                :label="$t('shift.clearAll')" 
                icon="pi pi-trash" 
                size="small" 
                severity="danger"
                outlined
                @click="clearAllDayOffs"
                v-if="currentDayOffs.length > 0"
              />
            </div>
          </div>
          <div class="day-off-list">
            <Chip 
              v-for="dayOff in currentDayOffs" 
              :key="dayOff.id"
              :label="dayOff.user_name"
              removable
              @remove="removeDayOff(dayOff.id)"
              class="day-off-chip-item"
            />
            <span v-if="currentDayOffs.length === 0" class="empty-text">
              {{ $t('shift.noDayOffs') }}
            </span>
          </div>
        </div>

        <!-- Copy to Week -->
        <div class="schedule-section">
          <div class="copy-week-section">
            <div>
              <strong>{{ $t('shift.copyToWeek') }}</strong>
              <p class="text-muted">{{ $t('shift.copyToWeekHint') }}</p>
            </div>
            <Button 
              :label="$t('shift.copyFor7Days')" 
              icon="pi pi-copy" 
              severity="info"
              @click="copyToWeek"
              :loading="copying"
            />
          </div>
        </div>
      </div>

      <template #footer>
        <Button :label="$t('common.close')" text @click="dayScheduleDialogVisible = false" />
      </template>
    </Dialog>

    <!-- Add Employee to Shift Dialog -->
    <Dialog v-model:visible="addEmployeeDialogVisible" :header="$t('shift.addEmployee')" modal :style="{ width: '400px' }">
      <div class="form-field">
        <label>{{ $t('shift.selectEmployee') }} *</label>
        <MultiSelect 
          v-model="selectedEmployeesToAdd" 
          :options="availableEmployees" 
          optionLabel="name" 
          optionValue="id"
          :placeholder="$t('shift.selectEmployee')"
          fluid
          filter
          display="chip"
        />
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="addEmployeeDialogVisible = false" />
        <Button :label="$t('common.add')" @click="addEmployeesToShift" :loading="saving" />
      </template>
    </Dialog>

    <!-- Add Day Off Dialog -->
    <Dialog v-model:visible="addDayOffDialogVisible" :header="$t('shift.addDayOff')" modal :style="{ width: '400px' }">
      <div class="form-field">
        <label>{{ $t('shift.selectEmployee') }} *</label>
        <MultiSelect 
          v-model="selectedEmployeesForDayOff" 
          :options="availableEmployeesForDayOff" 
          optionLabel="name" 
          optionValue="id"
          :placeholder="$t('shift.selectEmployee')"
          fluid
          filter
          display="chip"
        />
      </div>
      <template #footer>
        <Button :label="$t('common.cancel')" text @click="addDayOffDialogVisible = false" />
        <Button :label="$t('common.add')" @click="addDayOffs" :loading="saving" />
      </template>
    </Dialog>

    <!-- View All Day Offs Dialog -->
    <Dialog v-model:visible="viewAllDayOffsDialogVisible" :header="$t('shift.allDayOffs')" modal :style="{ width: '900px' }">
      <div class="all-day-offs-content">
        <DataTable :value="allDayOffsGrouped" stripedRows>
          <Column field="date" :header="$t('common.date')" style="min-width: 120px;">
            <template #body="slotProps">
              <strong>{{ formatDate(slotProps.data.date) }}</strong>
            </template>
          </Column>
          <Column field="employees" :header="$t('shift.employeesOnDayOff')" style="min-width: 400px;">
            <template #body="slotProps">
              <div class="employee-chips">
                <Chip 
                  v-for="emp in slotProps.data.employees" 
                  :key="emp.id"
                  :label="emp.user_name"
                  class="employee-chip"
                />
              </div>
            </template>
          </Column>
          <Column field="count" :header="$t('shift.totalEmployees')" style="min-width: 100px; text-align: center;">
            <template #body="slotProps">
              <Tag :value="slotProps.data.count" severity="info" />
            </template>
          </Column>
        </DataTable>
      </div>
      <template #footer>
        <Button :label="$t('common.close')" text @click="viewAllDayOffsDialogVisible = false" />
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
import Checkbox from 'primevue/checkbox'
import Message from 'primevue/message'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Chip from 'primevue/chip'
import MultiSelect from 'primevue/multiselect'
import api from '@/services/api'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'

const route = useRoute()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const outletId = route.params.outletId

const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const currentDate = ref(new Date())
const shifts = ref([])
const employees = ref([])
const assignments = ref([])
const leaves = ref({})
const dayOffs = ref([]) // New: day offs data
const dayScheduleDialogVisible = ref(false)
const addEmployeeDialogVisible = ref(false)
const addDayOffDialogVisible = ref(false)
const viewAllDayOffsDialogVisible = ref(false)

const selectedDate = ref(null)
const selectedShiftForAdd = ref(null)
const selectedEmployeesToAdd = ref([])
const selectedEmployeesForDayOff = ref([])
const currentDayOffs = ref([])
const copying = ref(false)

const dayOffScheduleData = ref([])
const loadingDayOff = ref(false)

const dayOffScheduleForm = ref({
  start_date: new Date(),
  end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)
})

const weekDays = computed(() => ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'])

const currentMonthLabel = computed(() => {
  const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
  return `${months[currentDate.value.getMonth()]} ${currentDate.value.getFullYear()}`
})

const calendarWeeks = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  
  const startDate = new Date(firstDay)
  startDate.setDate(startDate.getDate() - firstDay.getDay())
  
  const endDate = new Date(lastDay)
  endDate.setDate(endDate.getDate() + (6 - lastDay.getDay()))
  
  const weeks = []
  let currentWeek = []
  
  for (let date = new Date(startDate); date <= endDate; date.setDate(date.getDate() + 1)) {
    const dateStr = formatDateForAPI(date)
    const dayAssignments = assignments.value.filter(a => a.date === dateStr)
    const dayLeaves = leaves.value[dateStr] || []
    const dayDayOffs = dayOffs.value.filter(d => d.date === dateStr)
    
    currentWeek.push({
      date: new Date(date),
      isCurrentMonth: date.getMonth() === month,
      isToday: isToday(date),
      isWeekend: date.getDay() === 0 || date.getDay() === 6,
      assignments: dayAssignments,
      leaves: dayLeaves,
      dayOffs: dayDayOffs
    })
    
    if (currentWeek.length === 7) {
      weeks.push(currentWeek)
      currentWeek = []
    }
  }
  
  return weeks
})

const dayScheduleDialogTitle = computed(() => {
  if (!selectedDate.value) return t('shift.manageSchedule')
  return `${t('shift.manageSchedule')} - ${formatDate(selectedDate.value)}`
})

const availableEmployees = computed(() => {
  if (!selectedDate.value || !selectedShiftForAdd.value) return []
  
  const dateStr = formatDateForAPI(selectedDate.value)
  const assignedUserIds = assignments.value
    .filter(a => a.date === dateStr && a.shift_id === selectedShiftForAdd.value.id)
    .map(a => a.user_id)
  
  const dayOffUserIds = dayOffs.value
    .filter(d => d.date === dateStr)
    .map(d => d.user_id)
  
  return employees.value.filter(e => 
    !assignedUserIds.includes(e.id) && !dayOffUserIds.includes(e.id)
  )
})

const availableEmployeesForDayOff = computed(() => {
  if (!selectedDate.value) return []
  
  const dateStr = formatDateForAPI(selectedDate.value)
  const assignedUserIds = assignments.value
    .filter(a => a.date === dateStr)
    .map(a => a.user_id)
  
  const dayOffUserIds = dayOffs.value
    .filter(d => d.date === dateStr)
    .map(d => d.user_id)
  
  return employees.value.filter(e => 
    !assignedUserIds.includes(e.id) && !dayOffUserIds.includes(e.id)
  )
})

const allDayOffsGrouped = computed(() => {
  const grouped = {}
  
  dayOffs.value.forEach(dayOff => {
    if (!grouped[dayOff.date]) {
      grouped[dayOff.date] = {
        date: dayOff.date,
        employees: [],
        count: 0
      }
    }
    grouped[dayOff.date].employees.push(dayOff)
    grouped[dayOff.date].count++
  })
  
  return Object.values(grouped).sort((a, b) => a.date.localeCompare(b.date))
})

const fetchShifts = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/shifts`)
    shifts.value = response.data || []
  } catch (error) {
    console.error('Failed to fetch shifts:', error)
  }
}

const fetchEmployees = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/employees`)
    employees.value = response.data || []
  } catch (error) {
    console.error('Failed to fetch employees:', error)
  }
}

const fetchCalendar = async () => {
  loading.value = true
  try {
    const year = currentDate.value.getFullYear()
    const month = currentDate.value.getMonth()
    const startDate = new Date(year, month, 1)
    const endDate = new Date(year, month + 1, 0)
    
    const response = await api.get(`/outlets/${outletId}/shifts/calendar`, {
      params: {
        start_date: formatDateForAPI(startDate),
        end_date: formatDateForAPI(endDate)
      }
    })
    
    assignments.value = response.data.assignments || []
    leaves.value = response.data.leaves || {}
    dayOffs.value = response.data.day_offs || []
  } catch (error) {
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message, life: 3000 })
  } finally {
    loading.value = false
  }
}

const previousMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
  fetchCalendar()
}

const nextMonth = () => {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
  fetchCalendar()
}

const goToToday = () => {
  currentDate.value = new Date()
  fetchCalendar()
}

const openDayScheduleDialog = (day) => {
  selectedDate.value = day.date
  currentDayOffs.value = day.dayOffs || []
  dayScheduleDialogVisible.value = true
}

const refreshDayScheduleData = async () => {
  if (!selectedDate.value) return
  
  // Refresh calendar data
  await fetchCalendar()
  
  // Update currentDayOffs with fresh data
  const dateStr = formatDateForAPI(selectedDate.value)
  currentDayOffs.value = dayOffs.value.filter(d => d.date === dateStr)
}

const getShiftAssignments = (shiftId) => {
  if (!selectedDate.value) return []
  const dateStr = formatDateForAPI(selectedDate.value)
  return assignments.value.filter(a => a.date === dateStr && a.shift_id === shiftId)
}

const openAddEmployeeToShift = (shift) => {
  selectedShiftForAdd.value = shift
  selectedEmployeesToAdd.value = []
  addEmployeeDialogVisible.value = true
}

const openAddDayOff = () => {
  selectedEmployeesForDayOff.value = []
  addDayOffDialogVisible.value = true
}

const addEmployeesToShift = async () => {
  if (selectedEmployeesToAdd.value.length === 0) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.fillRequired'), life: 3000 })
    return
  }

  saving.value = true
  try {
    const dateStr = formatDateForAPI(selectedDate.value)
    
    for (const userId of selectedEmployeesToAdd.value) {
      await api.post(`/outlets/${outletId}/shifts/assign`, {
        user_id: userId,
        shift_id: selectedShiftForAdd.value.id,
        date: dateStr,
        status: 'scheduled'
      })
    }
    
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('shift.employeesAdded'), life: 3000 })
    addEmployeeDialogVisible.value = false
    await refreshDayScheduleData()
  } catch (error) {
    console.error('Error adding employees:', error)
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.errorOccurred'), life: 3000 })
  } finally {
    saving.value = false
  }
}

const addDayOffs = async () => {
  if (selectedEmployeesForDayOff.value.length === 0) {
    toast.add({ severity: 'warn', summary: t('messages.warning'), detail: t('users.fillRequired'), life: 3000 })
    return
  }

  saving.value = true
  try {
    const dateStr = formatDateForAPI(selectedDate.value)
    
    for (const userId of selectedEmployeesForDayOff.value) {
      await api.post(`/outlets/${outletId}/shifts/day-offs`, {
        user_id: userId,
        date: dateStr
      })
    }
    
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('shift.dayOffsAdded'), life: 3000 })
    addDayOffDialogVisible.value = false
    await refreshDayScheduleData()
  } catch (error) {
    console.error('Error adding day offs:', error)
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.errorOccurred'), life: 3000 })
  } finally {
    saving.value = false
  }
}

const removeAssignment = async (assignmentId) => {
  try {
    await api.delete(`/outlets/${outletId}/shifts/assignments/${assignmentId}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('shift.assignmentRemoved'), life: 3000 })
    await refreshDayScheduleData()
  } catch (error) {
    console.error('Error removing assignment:', error)
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.errorOccurred'), life: 3000 })
  }
}

const removeDayOff = async (dayOffId) => {
  try {
    await api.delete(`/outlets/${outletId}/shifts/day-offs/${dayOffId}`)
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('shift.dayOffRemoved'), life: 3000 })
    await refreshDayScheduleData()
  } catch (error) {
    console.error('Error removing day off:', error)
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.errorOccurred'), life: 3000 })
  }
}

const clearShiftAssignments = async (shiftId) => {
  const dateStr = formatDateForAPI(selectedDate.value)
  const assignmentsToDelete = assignments.value.filter(a => a.date === dateStr && a.shift_id === shiftId)
  
  if (assignmentsToDelete.length === 0) return
  
  try {
    for (const assignment of assignmentsToDelete) {
      await api.delete(`/outlets/${outletId}/shifts/assignments/${assignment.id}`)
    }
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('shift.allAssignmentsCleared'), life: 3000 })
    await refreshDayScheduleData()
  } catch (error) {
    console.error('Error clearing assignments:', error)
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.errorOccurred'), life: 3000 })
  }
}

const clearAllDayOffs = async () => {
  if (currentDayOffs.value.length === 0) return
  
  try {
    for (const dayOff of currentDayOffs.value) {
      await api.delete(`/outlets/${outletId}/shifts/day-offs/${dayOff.id}`)
    }
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('shift.allDayOffsCleared'), life: 3000 })
    await refreshDayScheduleData()
  } catch (error) {
    console.error('Error clearing day offs:', error)
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.errorOccurred'), life: 3000 })
  }
}

const openViewAllDayOffsDialog = () => {
  viewAllDayOffsDialogVisible.value = true
}

const clearDaySchedule = async (day) => {
  const dateStr = formatDateForAPI(day.date)
  const assignmentsCount = day.assignments?.length || 0
  const dayOffsCount = day.dayOffs?.length || 0
  const totalCount = assignmentsCount + dayOffsCount
  
  console.log('Clear day schedule:', {
    date: dateStr,
    assignments: day.assignments,
    dayOffs: day.dayOffs,
    totalCount
  })
  
  if (totalCount === 0) {
    toast.add({ severity: 'info', summary: t('messages.info'), detail: t('shift.nothingToClear'), life: 3000 })
    return
  }
  
  confirm.require({
    message: t('shift.confirmClearDay', { date: formatDate(day.date), count: totalCount }),
    header: t('shift.clearDaySchedule'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('common.yes'),
    rejectLabel: t('common.no'),
    accept: async () => {
      deleting.value = true
      try {
        let deletedCount = 0
        
        // Delete all assignments for this date
        if (day.assignments && day.assignments.length > 0) {
          console.log('Deleting assignments:', day.assignments)
          for (const assignment of day.assignments) {
            if (assignment.id) {
              console.log('Deleting assignment:', assignment.id)
              await api.delete(`/outlets/${outletId}/shifts/assignments/${assignment.id}`)
              deletedCount++
            }
          }
        }
        
        // Delete all day offs for this date
        if (day.dayOffs && day.dayOffs.length > 0) {
          console.log('Deleting day offs:', day.dayOffs)
          for (const dayOff of day.dayOffs) {
            if (dayOff.id) {
              console.log('Deleting day off:', dayOff.id)
              await api.delete(`/outlets/${outletId}/shifts/day-offs/${dayOff.id}`)
              deletedCount++
            }
          }
        }
        
        if (deletedCount > 0) {
          await fetchCalendar()
          toast.add({ 
            severity: 'success', 
            summary: t('messages.success'), 
            detail: t('shift.dayScheduleCleared', { count: deletedCount }), 
            life: 3000 
          })
        } else {
          toast.add({ 
            severity: 'warn', 
            summary: t('messages.warning'), 
            detail: t('shift.noItemsDeleted'), 
            life: 3000 
          })
        }
      } catch (error) {
        console.error('Error clearing day schedule:', error)
        toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.errorOccurred'), life: 3000 })
      } finally {
        deleting.value = false
      }
    }
  })
}

const copyToWeek = async () => {
  if (!selectedDate.value) return
  
  copying.value = true
  try {
    const dateStr = formatDateForAPI(selectedDate.value)
    
    await api.post(`/outlets/${outletId}/shifts/copy-day`, {
      source_date: dateStr,
      days: 7
    })
    
    toast.add({ severity: 'success', summary: t('messages.success'), detail: t('shift.copiedToWeek'), life: 3000 })
    dayScheduleDialogVisible.value = false
    fetchCalendar()
  } catch (error) {
    console.error('Error copying to week:', error)
    toast.add({ severity: 'error', summary: t('messages.error'), detail: error.response?.data?.message || t('messages.errorOccurred'), life: 3000 })
  } finally {
    copying.value = false
  }
}

const isToday = (date) => {
  const today = new Date()
  return date.getDate() === today.getDate() &&
         date.getMonth() === today.getMonth() &&
         date.getFullYear() === today.getFullYear()
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

const formatTime = (time) => {
  if (!time) return '-'
  return time.substring(0, 5)
}

const formatDateForAPI = (date) => {
  if (!date) return null
  const d = new Date(date)
  return d.toISOString().split('T')[0]
}

const getStatusSeverity = (status) => {
  const map = { scheduled: 'info', completed: 'success', absent: 'danger', swapped: 'warn' }
  return map[status] || 'secondary'
}

const getLeaveStatusSeverity = (status) => {
  const map = { pending: 'warn', approved: 'success', rejected: 'danger' }
  return map[status] || 'secondary'
}

onMounted(() => {
  fetchShifts()
  fetchEmployees()
  fetchCalendar()
})
</script>

<style scoped>
.shift-management-view {
  padding: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.page-header h2 { margin: 0; }
.text-muted { color: #6b7280; font-size: 0.875rem; margin: 0; }

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.month-navigation {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.5rem;
  justify-content: center;
}

.month-navigation h3 {
  margin: 0;
  min-width: 200px;
  text-align: center;
}

.legend {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
}

.legend-color {
  width: 20px;
  height: 20px;
  border-radius: 4px;
}

.calendar-container {
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  overflow: hidden;
}

.calendar-header {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background: #f9fafb;
  border-bottom: 2px solid #e5e7eb;
}

.calendar-cell {
  padding: 1rem;
  text-align: center;
  font-weight: 600;
  font-size: 0.875rem;
  color: #6b7280;
}

.calendar-body {
  display: flex;
  flex-direction: column;
}

.calendar-week {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  border-bottom: 1px solid #e5e7eb;
}

.calendar-week:last-child {
  border-bottom: none;
}

.calendar-day {
  min-height: 120px;
  border-right: 1px solid #e5e7eb;
  padding: 0.5rem;
  background: white;
}

.calendar-day:last-child {
  border-right: none;
}

.calendar-day.other-month {
  background: #f9fafb;
  opacity: 0.5;
}

.calendar-day.today {
  background: #eff6ff;
}

.calendar-day.weekend {
  background: #fef3c7;
}

.day-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.day-number {
  font-weight: 600;
  font-size: 0.875rem;
  color: #1f2937;
}

.calendar-day.today .day-number {
  background: #3b82f6;
  color: white;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.day-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.assignment-item {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s;
}

.assignment-item:hover {
  transform: translateX(2px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.assignment-name {
  font-weight: 600;
  color: #1f2937;
}

.assignment-shift {
  font-size: 0.7rem;
  color: #6b7280;
}

.leave-item {
  padding: 0.25rem 0.5rem;
  background: #fef2f2;
  border-left: 3px solid #ef4444;
  border-radius: 4px;
  font-size: 0.75rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  transition: all 0.2s;
}

.leave-item:hover {
  transform: translateX(2px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.leave-item i {
  color: #ef4444;
  font-size: 0.7rem;
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

.checkbox-field {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.checkbox-field label {
  font-weight: 400;
  cursor: pointer;
}

.detail-content {
  display: flex;
  flex-direction: column;
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

.day-off-schedule-content {
  margin-top: 1rem;
}

.day-off-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.day-off-chip {
  font-size: 0.85rem;
}

.day-off-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.4rem 0.6rem;
  background-color: #94a3b820;
  border-left: 3px solid #94a3b8;
  border-radius: 4px;
  font-size: 0.85rem;
  margin-bottom: 0.25rem;
  cursor: pointer;
}

.day-off-item i {
  color: #64748b;
  font-size: 0.75rem;
}

.add-schedule-btn,
.delete-schedule-btn {
  opacity: 1;
}

.day-header-actions {
  display: flex;
  gap: 0.25rem;
}

.day-schedule-dialog .shift-section {
  margin-bottom: 1.5rem;
}

.day-schedule-dialog .shift-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background-color: #f8fafc;
  border-left: 4px solid;
  border-radius: 4px;
  margin-bottom: 0.5rem;
}

.day-schedule-dialog .shift-actions {
  display: flex;
  gap: 0.5rem;
}

.day-schedule-dialog .shift-time {
  margin-left: 0.5rem;
  color: #64748b;
  font-size: 0.85rem;
}

.day-schedule-dialog .shift-employees {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding: 0.5rem;
  min-height: 3rem;
  align-items: center;
}

.day-schedule-dialog .empty-text {
  color: #94a3b8;
  font-style: italic;
  font-size: 0.9rem;
}

.day-schedule-dialog .schedule-section {
  margin-bottom: 1.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.day-schedule-dialog .schedule-section:last-child {
  border-bottom: none;
}

.day-schedule-dialog .section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.day-schedule-dialog .section-actions {
  display: flex;
  gap: 0.5rem;
}

.day-schedule-dialog .day-off-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  min-height: 3rem;
  align-items: center;
}

.day-schedule-dialog .day-off-chip-item {
  background-color: #94a3b820;
}

.day-schedule-dialog .copy-week-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: #f0f9ff;
  border-radius: 8px;
}

.day-schedule-dialog .copy-week-section .text-muted {
  font-size: 0.85rem;
  color: #64748b;
  margin-top: 0.25rem;
}

.all-day-offs-content {
  margin-top: 1rem;
}

.employee-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.employee-chip {
  font-size: 0.85rem;
}

.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
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

.loading-text {
  color: #1f2937;
  font-size: 1rem;
  font-weight: 600;
  margin: 0;
}
</style>
