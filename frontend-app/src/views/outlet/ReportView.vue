<template>
  <div class="report-view">
    <div class="page-header">
      <div>
        <h2>{{ $t('report.title') }}</h2>
        <p class="text-muted">{{ $t('report.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <DatePicker v-model="dateRange" selectionMode="range" :manualInput="false" showIcon dateFormat="dd/mm/yy" />
        <Button :label="$t('common.refresh')" icon="pi pi-refresh" @click="loadAllReports" :loading="loading" />
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
      <Card class="summary-card">
        <template #content>
          <div class="summary-content">
            <Avatar icon="pi pi-shopping-cart" size="large" class="summary-avatar revenue" />
            <div class="summary-info">
              <div class="summary-label">{{ $t('report.totalRevenue') }}</div>
              <div class="summary-value">{{ formatCurrency(summary.totalRevenue) }}</div>
            </div>
          </div>
        </template>
      </Card>

      <Card class="summary-card">
        <template #content>
          <div class="summary-content">
            <Avatar icon="pi pi-receipt" size="large" class="summary-avatar orders" />
            <div class="summary-info">
              <div class="summary-label">{{ $t('report.totalOrders') }}</div>
              <div class="summary-value">{{ summary.totalOrders }}</div>
            </div>
          </div>
        </template>
      </Card>

      <Card class="summary-card">
        <template #content>
          <div class="summary-content">
            <Avatar icon="pi pi-chart-line" size="large" class="summary-avatar average" />
            <div class="summary-info">
              <div class="summary-label">{{ $t('report.averageOrder') }}</div>
              <div class="summary-value">{{ formatCurrency(summary.averageOrder) }}</div>
            </div>
          </div>
        </template>
      </Card>

      <Card class="summary-card">
        <template #content>
          <div class="summary-content">
            <Avatar icon="pi pi-users" size="large" class="summary-avatar customers" />
            <div class="summary-info">
              <div class="summary-label">{{ $t('report.totalCustomers') }}</div>
              <div class="summary-value">{{ summary.totalCustomers }}</div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Tabs -->
    <Tabs value="0">
      <TabList>
        <Tab value="0">
          <i class="pi pi-chart-bar"></i>
          {{ $t('report.salesReport') }}
        </Tab>
        <Tab value="1">
          <i class="pi pi-book"></i>
          {{ $t('report.menuReport') }}
        </Tab>
        <Tab value="2">
          <i class="pi pi-cloud"></i>
          {{ $t('report.weatherReport') }}
        </Tab>
        <Tab value="3">
          <i class="pi pi-box"></i>
          {{ $t('report.inventoryReport') }}
        </Tab>
        <Tab value="4">
          <i class="pi pi-wallet"></i>
          {{ $t('report.expenseReport') }}
        </Tab>
      </TabList>

      <TabPanels>
        <!-- Sales Report -->
        <TabPanel value="0">
          <div class="report-section">
            <Card>
              <template #title>{{ $t('report.salesTrend') }}</template>
              <template #content>
                <Chart type="line" :data="salesChartData" :options="chartOptions" />
              </template>
            </Card>

            <div class="chart-grid">
              <Card>
                <template #title>{{ $t('report.salesByHour') }}</template>
                <template #content>
                  <Chart type="bar" :data="hourlyChartData" :options="chartOptions" />
                </template>
              </Card>

              <Card>
                <template #title>{{ $t('report.paymentMethods') }}</template>
                <template #content>
                  <Chart type="doughnut" :data="paymentChartData" :options="doughnutOptions" />
                </template>
              </Card>
            </div>
          </div>
        </TabPanel>

        <!-- Menu Report -->
        <TabPanel value="1">
          <div class="report-section">
            <Card>
              <template #title>{{ $t('report.topSellingMenu') }}</template>
              <template #content>
                <DataTable :value="topMenus" stripedRows>
                  <Column field="rank" :header="$t('report.rank')" style="width: 80px">
                    <template #body="slotProps">
                      <Tag :value="slotProps.data.rank" :severity="getRankSeverity(slotProps.data.rank)" />
                    </template>
                  </Column>
                  <Column field="name" :header="$t('report.menuName')" />
                  <Column field="category" :header="$t('report.category')" />
                  <Column field="quantity" :header="$t('report.quantitySold')" />
                  <Column field="revenue" :header="$t('report.revenue')">
                    <template #body="slotProps">
                      {{ formatCurrency(slotProps.data.revenue) }}
                    </template>
                  </Column>
                </DataTable>
              </template>
            </Card>

            <Card class="mt-4">
              <template #title>{{ $t('report.menuPerformance') }}</template>
              <template #content>
                <Chart type="bar" :data="menuChartData" :options="horizontalChartOptions" />
              </template>
            </Card>
          </div>
        </TabPanel>

        <!-- Weather Report -->
        <TabPanel value="2">
          <div class="report-section">
            <Card>
              <template #title>{{ $t('report.salesByWeatherCondition') }}</template>
              <template #content>
                <Chart type="bar" :data="weatherConditionChartData" :options="chartOptions" />
              </template>
            </Card>

            <div class="chart-grid">
              <Card>
                <template #title>{{ $t('report.todayWeather') }}</template>
                <template #content>
                  <Chart type="line" :data="weatherSalesChartData" :options="multiAxisOptions" />
                </template>
              </Card>

              <Card>
                <template #title>{{ $t('report.weatherConditionStats') }}</template>
                <template #content>
                  <DataTable :value="weatherStats" stripedRows>
                    <Column field="condition" :header="$t('report.condition')" />
                    <Column field="avgTemp" :header="$t('report.avgTemp')">
                      <template #body="slotProps">
                        {{ slotProps.data.avgTemp }}°C
                      </template>
                    </Column>
                    <Column field="orders" :header="$t('report.orders')" />
                    <Column field="revenue" :header="$t('report.revenue')">
                      <template #body="slotProps">
                        {{ formatCurrency(slotProps.data.revenue) }}
                      </template>
                    </Column>
                  </DataTable>
                </template>
              </Card>
            </div>
          </div>
        </TabPanel>

        <!-- Inventory Report -->
        <TabPanel value="3">
          <div class="report-section">
            <Card>
              <template #title>{{ $t('report.stockStatus') }}</template>
              <template #content>
                <div class="stock-summary">
                  <div class="stock-item in-stock">
                    <Avatar icon="pi pi-check-circle" size="large" />
                    <div>
                      <div class="stock-value">{{ inventoryStats.inStock }}</div>
                      <div class="stock-label">{{ $t('report.inStock') }}</div>
                    </div>
                  </div>
                  <div class="stock-item low-stock">
                    <Avatar icon="pi pi-exclamation-triangle" size="large" />
                    <div>
                      <div class="stock-value">{{ inventoryStats.lowStock }}</div>
                      <div class="stock-label">{{ $t('report.lowStock') }}</div>
                    </div>
                  </div>
                  <div class="stock-item out-of-stock">
                    <Avatar icon="pi pi-times-circle" size="large" />
                    <div>
                      <div class="stock-value">{{ inventoryStats.outOfStock }}</div>
                      <div class="stock-label">{{ $t('report.outOfStock') }}</div>
                    </div>
                  </div>
                </div>
              </template>
            </Card>

            <Card class="mt-4">
              <template #title>{{ $t('report.lowStockItems') }}</template>
              <template #content>
                <DataTable :value="lowStockItems" stripedRows>
                  <Column field="name" :header="$t('report.itemName')" />
                  <Column field="current_stock" :header="$t('report.currentStock')" />
                  <Column field="min_stock" :header="$t('report.minStock')" />
                  <Column field="satuan" :header="$t('report.unit')" />
                  <Column field="status" :header="$t('report.status')">
                    <template #body="slotProps">
                      <Tag :value="$t(`report.${slotProps.data.stock_status}`)" :severity="getStockSeverity(slotProps.data.stock_status)" />
                    </template>
                  </Column>
                </DataTable>
              </template>
            </Card>
          </div>
        </TabPanel>

        <!-- Expense Report -->
        <TabPanel value="4">
          <div class="report-section">
            <Card>
              <template #title>{{ $t('report.expenseBreakdown') }}</template>
              <template #content>
                <Chart type="doughnut" :data="expenseChartData" :options="doughnutOptions" />
              </template>
            </Card>

            <Card class="mt-4">
              <template #title>{{ $t('report.expenseDetails') }}</template>
              <template #content>
                <DataTable :value="expenseDetails" stripedRows>
                  <Column field="category" :header="$t('report.category')" />
                  <Column field="count" :header="$t('report.transactions')" />
                  <Column field="total" :header="$t('report.total')">
                    <template #body="slotProps">
                      {{ formatCurrency(slotProps.data.total) }}
                    </template>
                  </Column>
                  <Column field="percentage" :header="$t('report.percentage')">
                    <template #body="slotProps">
                      {{ slotProps.data.percentage }}%
                    </template>
                  </Column>
                </DataTable>
              </template>
            </Card>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Avatar from 'primevue/avatar'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import DatePicker from 'primevue/datepicker'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import { useCurrency } from '@/composables/useCurrency'

const route = useRoute()
const toast = useToast()
const { t } = useI18n()
const { formatCurrency } = useCurrency()

const outletId = route.params.outletId
const loading = ref(false)
const dateRange = ref([new Date(new Date().setDate(new Date().getDate() - 30)), new Date()])

// Summary data
const summary = ref({
  totalRevenue: 0,
  totalOrders: 0,
  averageOrder: 0,
  totalCustomers: 0
})

// Sales data
const salesChartData = ref({})
const hourlyChartData = ref({})
const paymentChartData = ref({})

// Menu data
const topMenus = ref([])
const menuChartData = ref({})

// Weather data
const weatherSalesChartData = ref({})
const weatherConditionChartData = ref({})
const weatherStats = ref([])

// Inventory data
const inventoryStats = ref({
  inStock: 0,
  lowStock: 0,
  outOfStock: 0
})
const lowStockItems = ref([])

// Expense data
const expenseChartData = ref({})
const expenseDetails = ref([])

// Chart options
const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: true,
      position: 'top'
    }
  }
})

const doughnutOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: true,
      position: 'bottom'
    }
  }
})

const horizontalChartOptions = ref({
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  }
})

const multiAxisOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false
  },
  plugins: {
    legend: {
      display: true,
      position: 'top'
    }
  },
  scales: {
    y: {
      type: 'linear',
      display: true,
      position: 'left'
    },
    y1: {
      type: 'linear',
      display: true,
      position: 'right',
      grid: {
        drawOnChartArea: false
      }
    }
  }
})

const getRankSeverity = (rank) => {
  if (rank === 1) return 'success'
  if (rank <= 3) return 'info'
  if (rank <= 5) return 'warn'
  return 'secondary'
}

const getStockSeverity = (status) => {
  if (status === 'in_stock') return 'success'
  if (status === 'low_stock') return 'warn'
  return 'danger'
}

const loadSalesReport = async () => {
  try {
    const [startDate, endDate] = dateRange.value
    const params = {
      start_date: startDate.toISOString().split('T')[0],
      end_date: endDate.toISOString().split('T')[0]
    }

    const response = await api.get(`/outlets/${outletId}/orders`, { params })
    const orders = response.data.filter(o => o.status === 'paid')

    // Calculate summary
    summary.value.totalOrders = orders.length
    summary.value.totalRevenue = orders.reduce((sum, o) => sum + parseFloat(o.total_amount), 0)
    summary.value.averageOrder = orders.length > 0 ? summary.value.totalRevenue / orders.length : 0
    
    // Get unique customers
    const uniqueCustomers = new Set(orders.filter(o => o.member_id).map(o => o.member_id))
    summary.value.totalCustomers = uniqueCustomers.size

    // Sales trend by date
    const salesByDate = {}
    orders.forEach(order => {
      const date = new Date(order.created_at).toLocaleDateString('id-ID')
      salesByDate[date] = (salesByDate[date] || 0) + parseFloat(order.total_amount)
    })

    salesChartData.value = {
      labels: Object.keys(salesByDate),
      datasets: [{
        label: t('report.revenue'),
        data: Object.values(salesByDate),
        borderColor: '#667eea',
        backgroundColor: 'rgba(102, 126, 234, 0.1)',
        tension: 0.4,
        fill: true
      }]
    }

    // Sales by hour
    const salesByHour = {}
    orders.forEach(order => {
      const hour = new Date(order.created_at).getHours()
      salesByHour[hour] = (salesByHour[hour] || 0) + parseFloat(order.total_amount)
    })

    hourlyChartData.value = {
      labels: Object.keys(salesByHour).map(h => `${h}:00`),
      datasets: [{
        label: t('report.revenue'),
        data: Object.values(salesByHour),
        backgroundColor: '#667eea'
      }]
    }

    // Payment methods
    const paymentMethods = {}
    orders.forEach(order => {
      const method = order.payment_method || 'Cash'
      paymentMethods[method] = (paymentMethods[method] || 0) + 1
    })

    paymentChartData.value = {
      labels: Object.keys(paymentMethods),
      datasets: [{
        data: Object.values(paymentMethods),
        backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe']
      }]
    }
  } catch (error) {
    console.error('Failed to load sales report:', error)
  }
}

const loadMenuReport = async () => {
  try {
    const [startDate, endDate] = dateRange.value
    const params = {
      start_date: startDate.toISOString().split('T')[0],
      end_date: endDate.toISOString().split('T')[0]
    }

    const response = await api.get(`/outlets/${outletId}/orders`, { params })
    const orders = response.data.filter(o => o.status === 'paid')

    // Aggregate menu sales
    const menuSales = {}
    orders.forEach(order => {
      if (order.items) {
        order.items.forEach(item => {
          if (!menuSales[item.menu_id]) {
            menuSales[item.menu_id] = {
              name: item.menu_name,
              category: item.category || 'N/A',
              quantity: 0,
              revenue: 0
            }
          }
          menuSales[item.menu_id].quantity += item.quantity
          menuSales[item.menu_id].revenue += parseFloat(item.subtotal)
        })
      }
    })

    // Sort by revenue and add rank
    topMenus.value = Object.values(menuSales)
      .sort((a, b) => b.revenue - a.revenue)
      .slice(0, 10)
      .map((item, index) => ({ ...item, rank: index + 1 }))

    // Menu performance chart
    menuChartData.value = {
      labels: topMenus.value.map(m => m.name),
      datasets: [{
        label: t('report.revenue'),
        data: topMenus.value.map(m => m.revenue),
        backgroundColor: '#667eea'
      }]
    }
  } catch (error) {
    console.error('Failed to load menu report:', error)
  }
}

const loadWeatherReport = async () => {
  try {
    const [startDate, endDate] = dateRange.value
    
    // Get outlet location (assuming Jakarta for now, should be from outlet data)
    const latitude = -6.2088
    const longitude = 106.8456
    
    // Fetch real-time weather data from Open-Meteo API
    const weatherUrl = `https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&hourly=temperature_2m,relative_humidity_2m,weather_code&timezone=Asia/Jakarta&forecast_days=1`
    
    const weatherResponse = await fetch(weatherUrl)
    const weatherData = await weatherResponse.json()
    
    // Fetch orders for correlation
    const ordersResponse = await api.get(`/outlets/${outletId}/orders`, {
      params: {
        start_date: startDate.toISOString().split('T')[0],
        end_date: endDate.toISOString().split('T')[0]
      }
    })
    const orders = ordersResponse.data.filter(o => o.status === 'paid')

    // WMO Weather interpretation codes
    const getWeatherCondition = (code) => {
      if (code === 0) return 'Cerah'
      if (code <= 3) return 'Berawan'
      if (code <= 48) return 'Berkabut'
      if (code <= 67) return 'Hujan Ringan'
      if (code <= 77) return 'Hujan'
      if (code <= 82) return 'Hujan Lebat'
      if (code <= 86) return 'Hujan Salju'
      if (code <= 99) return 'Badai'
      return 'Lainnya'
    }

    // Process hourly weather data
    const hourlyData = weatherData.hourly
    const hours = hourlyData.time.map(t => t.substring(11, 16)) // Extract HH:MM
    const temperatures = hourlyData.temperature_2m
    const humidity = hourlyData.relative_humidity_2m
    const weatherCodes = hourlyData.weather_code
    
    // Calculate sales by hour
    const salesByHour = {}
    const today = new Date().toISOString().split('T')[0]
    
    orders.forEach(order => {
      const orderDate = new Date(order.created_at)
      const orderDateStr = orderDate.toISOString().split('T')[0]
      
      // Only count today's orders for hourly correlation
      if (orderDateStr === today) {
        const hourKey = `${String(orderDate.getHours()).padStart(2, '0')}:00`
        salesByHour[hourKey] = (salesByHour[hourKey] || 0) + parseFloat(order.total_amount)
      }
    })

    // Group by weather condition
    const weatherConditions = {}
    const conditionSales = {}
    
    weatherCodes.forEach((code, index) => {
      const condition = getWeatherCondition(code)
      const hour = hours[index]
      const temp = temperatures[index]
      
      if (!weatherConditions[condition]) {
        weatherConditions[condition] = {
          condition,
          avgTemp: 0,
          tempSum: 0,
          count: 0,
          orders: 0,
          revenue: 0
        }
      }
      
      weatherConditions[condition].tempSum += temp
      weatherConditions[condition].count++
      
      // Add sales data if available for this hour
      if (salesByHour[hour]) {
        weatherConditions[condition].orders++
        weatherConditions[condition].revenue += salesByHour[hour]
      }
    })

    // Calculate averages
    Object.values(weatherConditions).forEach(wc => {
      wc.avgTemp = (wc.tempSum / wc.count).toFixed(1)
    })

    weatherStats.value = Object.values(weatherConditions).filter(wc => wc.count > 0)

    // Chart 1: Temperature & Humidity over time
    weatherSalesChartData.value = {
      labels: hours,
      datasets: [
        {
          label: t('report.temperature') + ' (°C)',
          data: temperatures,
          borderColor: '#f59e0b',
          backgroundColor: 'rgba(245, 158, 11, 0.1)',
          yAxisID: 'y',
          tension: 0.4,
          fill: true
        },
        {
          label: t('report.humidity') + ' (%)',
          data: humidity,
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          yAxisID: 'y1',
          tension: 0.4,
          fill: true
        }
      ]
    }

    // Chart 2: Sales by Weather Condition
    const conditions = Object.keys(weatherConditions)
    const conditionColors = {
      'Cerah': '#fbbf24',
      'Berawan': '#94a3b8',
      'Berkabut': '#64748b',
      'Hujan Ringan': '#60a5fa',
      'Hujan': '#3b82f6',
      'Hujan Lebat': '#1e40af',
      'Hujan Salju': '#0ea5e9',
      'Badai': '#7c3aed',
      'Lainnya': '#6b7280'
    }
    
    weatherConditionChartData.value = {
      labels: conditions,
      datasets: [{
        label: t('report.revenue'),
        data: conditions.map(c => weatherConditions[c].revenue),
        backgroundColor: conditions.map(c => conditionColors[c] || '#6b7280')
      }]
    }
  } catch (error) {
    console.error('Failed to load weather report:', error)
    toast.add({
      severity: 'error',
      summary: t('messages.error'),
      detail: t('report.weatherDataNotAvailable'),
      life: 3000
    })
  }
}

const loadInventoryReport = async () => {
  try {
    const response = await api.get(`/outlets/${outletId}/bahan-baku`)
    const materials = response.data

    inventoryStats.value = {
      inStock: materials.filter(m => m.stock_status === 'in_stock').length,
      lowStock: materials.filter(m => m.stock_status === 'low_stock').length,
      outOfStock: materials.filter(m => m.stock_status === 'out_of_stock').length
    }

    lowStockItems.value = materials
      .filter(m => m.stock_status !== 'in_stock')
      .sort((a, b) => a.current_stock - b.current_stock)
  } catch (error) {
    console.error('Failed to load inventory report:', error)
  }
}

const loadExpenseReport = async () => {
  try {
    const [startDate, endDate] = dateRange.value
    const params = {
      start_date: startDate.toISOString().split('T')[0],
      end_date: endDate.toISOString().split('T')[0]
    }

    const response = await api.get(`/outlets/${outletId}/expenses`, { params })
    const expenses = response.data

    const expenseByCategory = {}
    let totalExpense = 0

    expenses.forEach(expense => {
      const category = expense.category || 'Other'
      if (!expenseByCategory[category]) {
        expenseByCategory[category] = {
          category,
          count: 0,
          total: 0
        }
      }
      expenseByCategory[category].count++
      expenseByCategory[category].total += parseFloat(expense.amount)
      totalExpense += parseFloat(expense.amount)
    })

    // Calculate percentages
    Object.values(expenseByCategory).forEach(cat => {
      cat.percentage = ((cat.total / totalExpense) * 100).toFixed(1)
    })

    expenseDetails.value = Object.values(expenseByCategory)

    expenseChartData.value = {
      labels: Object.keys(expenseByCategory),
      datasets: [{
        data: Object.values(expenseByCategory).map(c => c.total),
        backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe']
      }]
    }
  } catch (error) {
    console.error('Failed to load expense report:', error)
  }
}

const loadAllReports = async () => {
  loading.value = true
  try {
    await Promise.all([
      loadSalesReport(),
      loadMenuReport(),
      loadWeatherReport(),
      loadInventoryReport(),
      loadExpenseReport()
    ])
  } finally {
    loading.value = false
  }
}

watch(dateRange, () => {
  if (dateRange.value && dateRange.value[0] && dateRange.value[1]) {
    loadAllReports()
  }
})

onMounted(() => {
  loadAllReports()
})

</script>

<style scoped>
.report-view {
  padding: 1.5rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  gap: 1rem;
}

.page-header h2 {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 600;
}

.text-muted {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0.25rem 0 0 0;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
  align-items: center;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.summary-card {
  border: 1px solid #e5e7eb;
  transition: all 0.3s ease;
}

.summary-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.summary-content {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.summary-avatar {
  flex-shrink: 0;
  width: 64px !important;
  height: 64px !important;
  font-size: 2rem;
}

.summary-avatar.revenue {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.summary-avatar.orders {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: white;
}

.summary-avatar.average {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  color: white;
}

.summary-avatar.customers {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  color: white;
}

.summary-info {
  flex: 1;
  min-width: 0;
}

.summary-label {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0.5rem;
}

.summary-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1f2937;
}

.report-section {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.chart-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
  gap: 1.5rem;
}

.report-section :deep(.p-chart) {
  height: 300px;
}

.chart-grid :deep(.p-chart) {
  height: 250px;
}

.stock-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
}

.stock-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: 8px;
  background: #f9fafb;
}

.stock-item.in-stock :deep(.p-avatar) {
  background: #22c55e;
  color: white;
}

.stock-item.low-stock :deep(.p-avatar) {
  background: #f97316;
  color: white;
}

.stock-item.out-of-stock :deep(.p-avatar) {
  background: #ef4444;
  color: white;
}

.stock-value {
  font-size: 2rem;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 0.25rem;
}

.stock-item.in-stock .stock-value {
  color: #22c55e;
}

.stock-item.low-stock .stock-value {
  color: #f97316;
}

.stock-item.out-of-stock .stock-value {
  color: #ef4444;
}

.stock-label {
  color: #6b7280;
  font-size: 0.875rem;
  font-weight: 500;
}

.mt-4 {
  margin-top: 1.5rem;
}

:deep(.p-tablist) {
  border-bottom: 2px solid #e5e7eb;
  margin-bottom: 1.5rem;
}

:deep(.p-tab) {
  padding: 0.75rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

:deep(.p-datatable) {
  font-size: 0.875rem;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
  background: #f9fafb;
  font-weight: 600;
  color: #374151;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    flex-direction: column;
    align-items: stretch;
  }

  .summary-grid {
    grid-template-columns: 1fr;
  }

  .chart-grid {
    grid-template-columns: 1fr;
  }

  .stock-summary {
    grid-template-columns: 1fr;
  }

  .summary-value {
    font-size: 1.5rem;
  }

  .summary-avatar {
    width: 56px !important;
    height: 56px !important;
    font-size: 1.75rem;
  }
}
</style>
