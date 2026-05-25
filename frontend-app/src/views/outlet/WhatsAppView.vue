<template>
  <div class="wa-view">
    <div v-if="!wahaEnabled" class="wa-disabled-banner">
      <i class="pi pi-info-circle"></i>
      <div>
        <strong>WhatsApp gateway is not configured.</strong>
        <div class="wa-disabled-hint">
          Set <code>VITE_WAHA_ENABLED=true</code> and provide a real
          <code>VITE_WAHA_API_KEY</code> (not <code>change-me</code>), then rebuild the frontend image.
          See DOCKER.md for details.
        </div>
      </div>
    </div>
    <div class="page-header">
      <div class="page-header-left">
        <h2><i class="pi pi-whatsapp" style="color:#25d366"></i> WhatsApp</h2>
        <Tag :value="sessionStatus || 'STOPPED'" :severity="statusSeverity" class="ml-2" />
      </div>
      <div class="header-actions">
        <Button
          icon="pi pi-send"
          text rounded size="small"
          v-tooltip.bottom="'Send Message'"
          :class="{ 'action-active': tab === 'send' }"
          @click="tab = 'send'"
        />
        <Button
          icon="pi pi-users"
          text rounded size="small"
          v-tooltip.bottom="'Contacts'"
          :class="{ 'action-active': tab === 'contacts' }"
          @click="tab = 'contacts'; fetchContacts()"
        />
        <Button
          icon="pi pi-mobile"
          text rounded size="small"
          v-tooltip.bottom="'Session Management'"
          :class="{ 'action-active': tab === 'session' }"
          @click="tab = 'session'"
        />
        <Button icon="pi pi-refresh" text rounded size="small" v-tooltip.bottom="'Refresh'" @click="init" :loading="loading" />
      </div>
    </div>

    <!-- ===================== QR BANNER (muncul di semua tab saat belum konek) ===================== -->
    <Card v-if="wahaEnabled && (sessionStatus === 'SCAN_QR_CODE' || sessionStatus === 'STOPPED' || sessionStatus === 'STARTING')" class="qr-banner mb-3">
      <template #content>
        <div class="qr-banner-inner">
          <div class="qr-banner-info">
            <h3 class="mt-0 mb-1"><i class="pi pi-qrcode mr-2"></i>Aktifkan WhatsApp</h3>
            <p class="text-muted mb-2">Scan QR code ini dengan WhatsApp di HP Anda untuk mengaktifkan layanan notifikasi.</p>
            <div class="flex gap-2 align-items-center flex-wrap">
              <Tag :value="sessionStatus || 'STOPPED'" :severity="statusSeverity" />
              <Button v-if="sessionStatus === 'STOPPED'" label="Start Session" icon="pi pi-play" size="small" severity="success" @click="startSession" :loading="actioning" />
              <Button label="Refresh QR" icon="pi pi-refresh" size="small" outlined @click="fetchQr" :loading="loadingQr" />
            </div>
            <small v-if="qrExpireCountdown > 0" class="text-muted mt-2 block">QR kedaluwarsa dalam {{ qrExpireCountdown }} detik</small>
          </div>
          <div class="qr-banner-code">
            <div v-if="qrImage" class="qr-box">
              <img :src="qrImage" alt="QR Code" style="width:200px;height:200px;object-fit:contain" />
            </div>
            <div v-else class="qr-placeholder" style="width:200px;height:200px">
              <i class="pi pi-qrcode" style="font-size:3rem;color:#ccc"></i>
              <p class="text-muted">{{ sessionStatus === 'STOPPED' ? 'Start session dulu' : 'Memuat QR...' }}</p>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- ===================== SESSION TAB ===================== -->
    <div v-if="tab === 'session'" class="tab-content">
      <!-- Status cards -->
      <div class="status-grid">
        <div class="status-card" :class="serverOnline ? 'ok' : 'err'">
          <i class="pi pi-server"></i>
          <div>
            <div class="sc-label">WAHA Server</div>
            <div class="sc-val">{{ serverOnline ? 'Online' : 'Offline' }}</div>
          </div>
        </div>
        <div class="status-card" :class="sessionStatus === 'WORKING' ? 'ok' : sessionStatus === 'SCAN_QR_CODE' ? 'warn' : 'err'">
          <i class="pi pi-whatsapp"></i>
          <div>
            <div class="sc-label">Session</div>
            <div class="sc-val">{{ sessionStatus || 'STOPPED' }}</div>
          </div>
        </div>
        <div class="status-card" :class="sessionInfo ? 'ok' : 'off'">
          <i class="pi pi-user"></i>
          <div>
            <div class="sc-label">Connected As</div>
            <div class="sc-val">{{ sessionInfo?.me?.pushName || '-' }}</div>
          </div>
        </div>
        <div class="status-card off">
          <i class="pi pi-phone"></i>
          <div>
            <div class="sc-label">Phone</div>
            <div class="sc-val">{{ sessionInfo?.me?.id?.replace('@c.us','') || '-' }}</div>
          </div>
        </div>
      </div>

      <!-- QR Code -->
      <Card v-if="sessionStatus === 'SCAN_QR_CODE'" class="mb-3">
        <template #title>Scan QR Code</template>
        <template #content>
          <div class="qr-wrap">
            <div v-if="qrImage" class="qr-box">
              <img :src="qrImage" alt="QR" />
            </div>
            <div v-else class="qr-placeholder">
              <i class="pi pi-qrcode"></i>
              <p>Loading QR...</p>
            </div>
            <Button label="Refresh QR" icon="pi pi-refresh" outlined @click="fetchQr" :loading="loadingQr" />
          </div>
        </template>
      </Card>

      <!-- Session controls -->
      <Card>
        <template #title>Session Controls</template>
        <template #content>
          <div class="session-name-row">
            <label>Session Name</label>
            <InputText v-model="sessionName" placeholder="default" style="width:200px" />
          </div>
          <div class="btn-row mt-2">
            <Button label="Start" icon="pi pi-play" severity="success" @click="startSession" :loading="actioning" />
            <Button label="Stop" icon="pi pi-stop" severity="secondary" @click="stopSession" :loading="actioning" />
            <Button label="Restart" icon="pi pi-refresh" @click="restartSession" :loading="actioning" />
            <Button label="Logout" icon="pi pi-sign-out" severity="warning" @click="logoutSession" :loading="actioning" />
            <Button label="Delete" icon="pi pi-trash" severity="danger" @click="deleteSession" :loading="actioning" />
          </div>
        </template>
      </Card>

      <!-- All sessions list -->
      <Card class="mt-3">
        <template #title>All Sessions</template>
        <template #content>
          <DataTable :value="sessions" :loading="loading" stripedRows size="small">
            <Column field="name" header="Name" />
            <Column field="status" header="Status">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="data.status === 'WORKING' ? 'success' : data.status === 'SCAN_QR_CODE' ? 'warn' : 'secondary'" />
              </template>
            </Column>
            <Column field="engine.engine" header="Engine" />
            <Column header="Actions" style="width:160px">
              <template #body="{ data }">
                <div class="btn-row-sm">
                  <Button icon="pi pi-play" size="small" text severity="success" @click="startSessionByName(data.name)" />
                  <Button icon="pi pi-stop" size="small" text severity="secondary" @click="stopSessionByName(data.name)" />
                  <Button icon="pi pi-trash" size="small" text severity="danger" @click="deleteSessionByName(data.name)" />
                </div>
              </template>
            </Column>
          </DataTable>
          <div class="mt-2">
            <Button label="New Session" icon="pi pi-plus" size="small" @click="createSession" :loading="actioning" />
          </div>
        </template>
      </Card>
    </div>

    <!-- ===================== SEND TAB ===================== -->
    <div v-if="tab === 'send'" class="tab-content">
      <div class="send-grid">
        <!-- Send Text -->
        <Card>
          <template #title>Send Text</template>
          <template #content>
            <div class="form-col">
              <div class="form-field">
                <label>Session</label>
                <InputText v-model="sendForm.session" placeholder="default" fluid />
              </div>
              <div class="form-field">
                <label>Chat ID</label>
                <InputText v-model="sendForm.chatId" placeholder="628123456789@c.us" fluid />
                <small>Format: phone@c.us or groupid@g.us</small>
              </div>
              <div class="form-field">
                <label>Message</label>
                <Textarea v-model="sendForm.text" rows="4" fluid placeholder="Type your message..." />
              </div>
              <Button label="Send" icon="pi pi-send" @click="sendText" :loading="sending" fluid />
            </div>
          </template>
        </Card>

        <!-- Send Image -->
        <Card>
          <template #title>Send Image</template>
          <template #content>
            <div class="form-col">
              <div class="form-field">
                <label>Session</label>
                <InputText v-model="mediaForm.session" placeholder="default" fluid />
              </div>
              <div class="form-field">
                <label>Chat ID</label>
                <InputText v-model="mediaForm.chatId" placeholder="628123456789@c.us" fluid />
              </div>
              <div class="form-field">
                <label>Image URL</label>
                <InputText v-model="mediaForm.url" placeholder="https://..." fluid />
              </div>
              <div class="form-field">
                <label>Caption (optional)</label>
                <InputText v-model="mediaForm.caption" fluid />
              </div>
              <Button label="Send Image" icon="pi pi-image" @click="sendImage" :loading="sending" fluid />
            </div>
          </template>
        </Card>

        <!-- Send File -->
        <Card>
          <template #title>Send File / Document</template>
          <template #content>
            <div class="form-col">
              <div class="form-field">
                <label>Session</label>
                <InputText v-model="fileForm.session" placeholder="default" fluid />
              </div>
              <div class="form-field">
                <label>Chat ID</label>
                <InputText v-model="fileForm.chatId" placeholder="628123456789@c.us" fluid />
              </div>
              <div class="form-field">
                <label>File URL</label>
                <InputText v-model="fileForm.url" placeholder="https://..." fluid />
              </div>
              <div class="form-field">
                <label>Filename</label>
                <InputText v-model="fileForm.filename" placeholder="document.pdf" fluid />
              </div>
              <Button label="Send File" icon="pi pi-file" @click="sendFile" :loading="sending" fluid />
            </div>
          </template>
        </Card>

        <!-- Send Location -->
        <Card>
          <template #title>Send Location</template>
          <template #content>
            <div class="form-col">
              <div class="form-field">
                <label>Session</label>
                <InputText v-model="locationForm.session" placeholder="default" fluid />
              </div>
              <div class="form-field">
                <label>Chat ID</label>
                <InputText v-model="locationForm.chatId" placeholder="628123456789@c.us" fluid />
              </div>
              <div class="form-field">
                <label>Latitude</label>
                <InputText v-model="locationForm.latitude" placeholder="-6.2088" fluid />
              </div>
              <div class="form-field">
                <label>Longitude</label>
                <InputText v-model="locationForm.longitude" placeholder="106.8456" fluid />
              </div>
              <div class="form-field">
                <label>Title (optional)</label>
                <InputText v-model="locationForm.title" fluid />
              </div>
              <Button label="Send Location" icon="pi pi-map-marker" @click="sendLocation" :loading="sending" fluid />
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- ===================== CHATS TAB ===================== -->
    <div v-if="tab === 'chats'" class="tab-content">
      <div class="chats-layout">
        <!-- Chat list -->
        <div class="chat-list-panel">
          <div class="panel-header">
            <span>Chats</span>
            <div class="btn-row-sm">
              <InputText v-model="chatSession" placeholder="session" style="width:100px" size="small" />
              <Button icon="pi pi-refresh" size="small" text @click="fetchChats" :loading="loadingChats" />
            </div>
          </div>
          <div v-if="loadingChats" class="loading-center"><ProgressSpinner style="width:32px;height:32px" /></div>
          <div v-else class="chat-items">
            <div
              v-for="chat in chats"
              :key="chat.id"
              class="chat-item"
              :class="{ active: selectedChat?.id === chat.id }"
              @click="selectChat(chat)"
            >
              <div class="chat-avatar">
                <div class="avatar-wrap">
                  <img v-if="chat._pictureUrl" :src="chat._pictureUrl" :alt="chat.name" class="avatar-img" />
                  <Avatar v-else :label="chat.name?.charAt(0) || '?'" shape="circle" size="normal" />
                  <Badge
                    v-if="getUnread(chat) > 0"
                    :value="getUnread(chat)"
                    severity="danger"
                    class="unread-badge"
                  />
                </div>
              </div>
              <div class="chat-info">
                <div class="chat-name">{{ chat.name || chat.id }}</div>
                <div class="chat-last" :class="{ 'chat-last-unread': getUnread(chat) > 0 }">
                  {{ chat.lastMessage?.body?.substring(0, 40) || '' }}
                </div>
              </div>
              <div class="chat-meta">
                <span v-if="chat.lastMessage?.timestamp" class="chat-time">
                  {{ formatMsgTime(chat.lastMessage.timestamp) }}
                </span>
              </div>            </div>
            <div v-if="!chats.length" class="empty-hint">No chats found</div>
          </div>
        </div>

        <!-- Messages panel -->
        <div class="messages-panel">
          <div v-if="!selectedChat" class="empty-center">
            <i class="pi pi-comments"></i>
            <p>Select a chat to view messages</p>
          </div>
          <template v-else>
            <div class="panel-header">
              <span>{{ selectedChat.name || selectedChat.id }}</span>
              <div class="btn-row-sm">
                <Button icon="pi pi-check-circle" size="small" text v-tooltip="'Mark as read'" @click="markRead(selectedChat)" />
                <Button icon="pi pi-refresh" size="small" text @click="fetchMessages(getChatId(selectedChat))" :loading="loadingMessages" />
              </div>
            </div>
            <div class="messages-panel-body">
              <div class="messages-list" ref="messagesEl" @scroll="onMessagesScroll">
                <div v-if="loadingMessages" class="loading-center"><ProgressSpinner style="width:32px;height:32px" /></div>
                <template v-else>
                  <div
                    v-for="msg in messages"
                    :key="msg.id"
                    class="msg-bubble"
                    :class="[msg.fromMe ? 'from-me' : 'from-them', { 'has-image': msg.hasMedia && msg.media?.mimetype?.startsWith('image/') && msg.media?._blobUrl }]"
                  >                  <!-- Media -->
                    <div v-if="msg.hasMedia && msg.media" class="msg-media">
                      <img
                        v-if="msg.media.mimetype?.startsWith('image/') && msg.media._blobUrl"
                        :src="msg.media._blobUrl"
                        class="msg-image"
                        @click="openMedia(msg.media._blobUrl)"
                      />
                      <div v-else-if="msg.media.mimetype?.startsWith('image/') && !msg.media._blobUrl" class="msg-media-loading">
                        <i class="pi pi-image"></i> Image
                      </div>
                      <a
                        v-else-if="msg.media.url"
                        :href="msg.media.url"
                        target="_blank"
                        class="msg-file"
                      >
                        <i class="pi pi-file"></i>
                        {{ msg.media.filename || 'File' }}
                      </a>
                    </div>                  <!-- Text body -->
                    <div v-if="msg.body" class="msg-body" :class="{ 'msg-body-caption': msg.hasMedia }">{{ msg.body }}</div>
                    <!-- No content fallback -->
                    <div v-if="!msg.body && !msg.hasMedia" class="msg-body msg-unsupported">
                      <i class="pi pi-info-circle"></i> Unsupported message type
                    </div>
                    <div class="msg-time">{{ formatMsgTime(msg.timestamp) }}</div>
                  </div>
                  <div v-if="!messages.length" class="empty-hint">No messages</div>
                  <div ref="msgBottomEl"></div>
                </template>
              </div>

              <!-- Scroll to bottom button -->
              <Transition name="scroll-btn">
                <button v-if="showScrollBtn" class="scroll-down-btn" @click="scrollToBottom">
                  <i class="pi pi-chevron-down"></i>
                </button>
              </Transition>
            </div>
            <!-- Quick reply -->
            <div class="reply-bar">
              <InputText v-model="replyText" placeholder="Type a message..." @keyup.enter="sendReply" fluid />
              <Button icon="pi pi-send" @click="sendReply" :loading="sending" :disabled="!replyText" />
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- ===================== CONTACTS TAB ===================== -->
    <div v-if="tab === 'contacts'" class="tab-content">
      <div class="contacts-toolbar">
        <InputText v-model="contactSearch" placeholder="Search contacts..." style="width:250px" />
        <InputText v-model="contactSession" placeholder="session" style="width:120px" />
        <Button label="Load" icon="pi pi-search" @click="fetchContacts" :loading="loadingContacts" />
        <Button label="Check Number" icon="pi pi-check" outlined @click="checkNumberDialog = true" />
      </div>

      <DataTable :value="filteredContacts" :loading="loadingContacts" paginator :rows="20" stripedRows size="small" class="mt-2">
        <Column header="Avatar" style="width:60px">
          <template #body="{ data }">
            <Avatar :label="data.name?.charAt(0) || '?'" shape="circle" />
          </template>
        </Column>
        <Column field="name" header="Name" sortable />
        <Column field="id" header="ID / Phone" sortable />
        <Column field="isMyContact" header="Contact" style="width:80px">
          <template #body="{ data }">
            <Tag :value="data.isMyContact ? 'Yes' : 'No'" :severity="data.isMyContact ? 'success' : 'secondary'" />
          </template>
        </Column>
        <Column header="Actions" style="width:120px">
          <template #body="{ data }">
            <Button icon="pi pi-send" size="small" text @click="quickSendTo(data.id)" v-tooltip="'Send message'" />
          </template>
        </Column>
      </DataTable>
    </div>
  </div>

  <!-- Image Preview Dialog — outside all tabs so it always works -->
  <Dialog
    v-model:visible="imagePreviewVisible"
    modal
    :closable="true"
    :showHeader="false"
    :style="{ width: 'auto', maxWidth: '92vw', padding: 0 }"
    :contentStyle="{ padding: 0, background: '#000000cc', borderRadius: '8px' }"
    :pt="{
      root: { style: 'background: transparent; box-shadow: none; border: none;' },
      content: { style: 'padding: 0; background: transparent;' },
      mask: { style: 'backdrop-filter: blur(4px);' }
    }"
  >
    <div class="img-preview-wrap">
      <img :src="imagePreviewUrl" class="img-preview-full" />
      <Button icon="pi pi-times" rounded severity="secondary" class="img-preview-close" @click="imagePreviewVisible = false" />
    </div>
  </Dialog>

  <!-- Check number dialog -->
  <Dialog v-model:visible="checkNumberDialog" header="Check WhatsApp Number" modal style="width:400px">
    <div class="form-col">
      <div class="form-field">
        <label>Session</label>
        <InputText v-model="checkForm.session" placeholder="default" fluid />
      </div>
      <div class="form-field">
        <label>Phone Number</label>
        <InputText v-model="checkForm.phone" placeholder="628123456789" fluid />
      </div>
    </div>
    <template #footer>
      <Button label="Cancel" text @click="checkNumberDialog = false" />
      <Button label="Check" icon="pi pi-search" @click="checkNumber" :loading="checking" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useToast } from 'primevue/usetoast'
import axios from 'axios'
import { clearWahaUnread, onWahaMessage } from '@/composables/useWahaSocket'
import Card from 'primevue/card'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Avatar from 'primevue/avatar'
import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'
import Badge from 'primevue/badge'

const toast = useToast()

// WAHA config — reads from Vite env or falls back to defaults
const WAHA_URL = import.meta.env.VITE_WAHA_URL || 'http://localhost:3000'
const WAHA_KEY = import.meta.env.VITE_WAHA_API_KEY || ''

// WAHA is opt-in: only call the gateway when explicitly enabled and a real key
// is configured. Avoids the noisy WS/HTTP errors against /waha when the user
// has not yet provisioned WhatsApp.
const RAW_WAHA_ENABLED = String(import.meta.env.VITE_WAHA_ENABLED ?? 'false').toLowerCase()
const PLACEHOLDER_WAHA_KEYS = new Set(['', 'change-me', 'your-waha-api-key'])
const wahaEnabled =
  (RAW_WAHA_ENABLED === 'true' || RAW_WAHA_ENABLED === '1') && !PLACEHOLDER_WAHA_KEYS.has(WAHA_KEY)

const waha = axios.create({
  baseURL: WAHA_URL,
  headers: { 'X-Api-Key': WAHA_KEY, 'Content-Type': 'application/json' },
  timeout: 10000,
})

// ── state ──────────────────────────────────────────────────────────────────
const tab           = ref('chats')
const loading       = ref(false)
const actioning     = ref(false)
const sending       = ref(false)
const serverOnline  = ref(false)
const sessionName   = ref('default')
const sessionStatus = ref('STOPPED')
const sessionInfo   = ref(null)
const sessions      = ref([])
const qrImage           = ref(null)
const loadingQr         = ref(false)
const qrExpireCountdown = ref(0)
let qrCountdownInterval = null
let qrAutoRefreshTimer  = null

const startQrCountdown = (seconds = 60) => {
  // Bersihkan timer lama
  if (qrCountdownInterval) clearInterval(qrCountdownInterval)
  if (qrAutoRefreshTimer)  clearTimeout(qrAutoRefreshTimer)

  qrExpireCountdown.value = seconds
  qrCountdownInterval = setInterval(() => {
    qrExpireCountdown.value--
    if (qrExpireCountdown.value <= 0) {
      clearInterval(qrCountdownInterval)
      // Auto refresh QR saat kedaluwarsa
      if (sessionStatus.value === 'SCAN_QR_CODE') fetchQr()
    }
  }, 1000)
}

// send forms
const sendForm     = ref({ session: 'default', chatId: '', text: '' })
const mediaForm    = ref({ session: 'default', chatId: '', url: '', caption: '' })
const fileForm     = ref({ session: 'default', chatId: '', url: '', filename: '' })
const locationForm = ref({ session: 'default', chatId: '', latitude: '', longitude: '', title: '' })

// chats
const chats          = ref([])
const loadingChats   = ref(false)
const chatSession    = ref('default')
const selectedChat   = ref(null)
const messages       = ref([])
const loadingMessages = ref(false)
const replyText      = ref('')
const messagesEl     = ref(null)
const msgBottomEl    = ref(null)
const showScrollBtn  = ref(false)

// contacts
const contacts          = ref([])
const loadingContacts   = ref(false)
const contactSearch     = ref('')
const contactSession    = ref('default')
const checkNumberDialog = ref(false)
const checking          = ref(false)
const checkForm         = ref({ session: 'default', phone: '' })

// ── computed ───────────────────────────────────────────────────────────────
const statusSeverity = computed(() => {
  if (sessionStatus.value === 'WORKING') return 'success'
  if (sessionStatus.value === 'SCAN_QR_CODE') return 'warn'
  return 'secondary'
})

const filteredContacts = computed(() => {
  if (!contactSearch.value) return contacts.value
  const q = contactSearch.value.toLowerCase()
  return contacts.value.filter(c =>
    c.name?.toLowerCase().includes(q) || c.id?.toLowerCase().includes(q)
  )
})

// ── helpers ────────────────────────────────────────────────────────────────
const ok  = (msg) => toast.add({ severity: 'success', summary: 'Success', detail: msg, life: 3000 })
const err = (e, fallback = 'Error') => toast.add({ severity: 'error', summary: 'Error', detail: e?.response?.data?.message || fallback, life: 4000 })

const formatMsgTime = (ts) => {
  if (!ts) return ''
  return new Date(ts * 1000).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' })
}

// ── session ────────────────────────────────────────────────────────────────
const init = async () => {
  loading.value = true
  try {
    // ping server
    await waha.get('/api/server/status')
    serverOnline.value = true
  } catch {
    serverOnline.value = false
    loading.value = false
    return
  }

  try {
    const res = await waha.get('/api/sessions')
    sessions.value = res.data || []

    // find default session
    const def = sessions.value.find(s => s.name === sessionName.value)
    if (def) {
      sessionStatus.value = def.status
      if (def.status === 'WORKING') {
        const me = await waha.get(`/api/sessions/${sessionName.value}`)
        sessionInfo.value = me.data
      }
      if (def.status === 'SCAN_QR_CODE') fetchQr()
    }
  } catch (e) {
    err(e, 'Failed to load sessions')
  } finally {
    loading.value = false
  }
}

const fetchQr = async () => {
  loadingQr.value = true
  try {
    const res = await waha.get(`/api/${sessionName.value}/auth/qr`, { params: { format: 'image' }, responseType: 'blob' })
    qrImage.value = URL.createObjectURL(res.data)
    startQrCountdown(60)
  } catch {
    // try base64 format
    try {
      const res2 = await waha.get(`/api/${sessionName.value}/auth/qr`)
      const val = res2.data?.value || res2.data?.qr
      if (val) {
        qrImage.value = val.startsWith('data:') ? val : `data:image/png;base64,${val}`
        startQrCountdown(60)
      }
    } catch (e2) {
      err(e2, 'Failed to load QR')
    }
  } finally {
    loadingQr.value = false
  }
}

const createSession = async () => {
  actioning.value = true
  try {
    await waha.post('/api/sessions', { name: sessionName.value })
    ok('Session created')
    await init()
  } catch (e) { err(e, 'Failed to create session') }
  finally { actioning.value = false }
}

const startSession = async () => {
  actioning.value = true
  try {
    await waha.post(`/api/sessions/${sessionName.value}/start`)
    ok('Session started')
    setTimeout(() => init(), 2000)
  } catch (e) { err(e, 'Failed to start session') }
  finally { actioning.value = false }
}

const stopSession = async () => {
  actioning.value = true
  try {
    await waha.post(`/api/sessions/${sessionName.value}/stop`)
    ok('Session stopped')
    await init()
  } catch (e) { err(e, 'Failed to stop session') }
  finally { actioning.value = false }
}

const restartSession = async () => {
  actioning.value = true
  try {
    await waha.post(`/api/sessions/${sessionName.value}/restart`)
    ok('Session restarted')
    setTimeout(() => init(), 2000)
  } catch (e) { err(e, 'Failed to restart session') }
  finally { actioning.value = false }
}

const logoutSession = async () => {
  actioning.value = true
  try {
    await waha.post(`/api/sessions/${sessionName.value}/logout`)
    ok('Logged out')
    await init()
  } catch (e) { err(e, 'Failed to logout') }
  finally { actioning.value = false }
}

const deleteSession = async () => {
  actioning.value = true
  try {
    await waha.delete(`/api/sessions/${sessionName.value}`)
    ok('Session deleted')
    await init()
  } catch (e) { err(e, 'Failed to delete session') }
  finally { actioning.value = false }
}

const startSessionByName  = (name) => { sessionName.value = name; startSession() }
const stopSessionByName   = (name) => { sessionName.value = name; stopSession() }
const deleteSessionByName = (name) => { sessionName.value = name; deleteSession() }

// ── send ───────────────────────────────────────────────────────────────────
const sendText = async () => {
  if (!sendForm.value.chatId || !sendForm.value.text) {
    toast.add({ severity: 'warn', summary: 'Warning', detail: 'Fill in Chat ID and message', life: 3000 })
    return
  }
  sending.value = true
  try {
    await waha.post('/api/sendText', {
      session: sendForm.value.session,
      chatId:  sendForm.value.chatId,
      text:    sendForm.value.text,
    })
    ok('Message sent!')
    sendForm.value.text = ''
  } catch (e) { err(e, 'Failed to send message') }
  finally { sending.value = false }
}

const sendImage = async () => {
  sending.value = true
  try {
    await waha.post('/api/sendImage', {
      session: mediaForm.value.session,
      chatId:  mediaForm.value.chatId,
      file:    { url: mediaForm.value.url },
      caption: mediaForm.value.caption,
    })
    ok('Image sent!')
  } catch (e) { err(e, 'Failed to send image') }
  finally { sending.value = false }
}

const sendFile = async () => {
  sending.value = true
  try {
    await waha.post('/api/sendFile', {
      session:  fileForm.value.session,
      chatId:   fileForm.value.chatId,
      file:     { url: fileForm.value.url },
      filename: fileForm.value.filename,
    })
    ok('File sent!')
  } catch (e) { err(e, 'Failed to send file') }
  finally { sending.value = false }
}

const sendLocation = async () => {
  sending.value = true
  try {
    await waha.post('/api/sendLocation', {
      session:   locationForm.value.session,
      chatId:    locationForm.value.chatId,
      latitude:  parseFloat(locationForm.value.latitude),
      longitude: parseFloat(locationForm.value.longitude),
      title:     locationForm.value.title,
    })
    ok('Location sent!')
  } catch (e) { err(e, 'Failed to send location') }
  finally { sending.value = false }
}

// ── chats ──────────────────────────────────────────────────────────────────
const fetchChats = async () => {
  loadingChats.value = true
  try {
    const res = await waha.get(`/api/${chatSession.value}/chats/overview`, {
      params: { limit: 50 }
    })
    const rawChats = res.data || []
    // Fetch pictures with auth header only if from WAHA server
    chats.value = await Promise.all(rawChats.map(async (chat) => {
      if (chat.picture) {
        const isWahaUrl = chat.picture.includes('localhost') || chat.picture.startsWith('/api/')
        if (isWahaUrl) {
          try {
            const url = chat.picture.startsWith('http') ? chat.picture : `${WAHA_URL}${chat.picture}`
            const picRes = await waha.get(url, { responseType: 'blob' })
            chat._pictureUrl = URL.createObjectURL(picRes.data)
          } catch {
            chat._pictureUrl = null
          }
        } else {
          // External URL (WhatsApp CDN) — use directly, no auth header needed
          chat._pictureUrl = chat.picture
        }
      }
      return chat
    }))
  } catch (e) { err(e, 'Failed to load chats') }
  finally { loadingChats.value = false }
}

const selectChat = async (chat) => {
  selectedChat.value = chat
  const chatId = typeof chat.id === 'object' ? chat.id.user + '@' + (chat.id.server || 'c.us') : chat.id
  await fetchMessages(chatId)
}

const fetchMessages = async (chatId) => {
  if (!chatId || typeof chatId === 'object') return
  loadingMessages.value = true
  try {
    const res = await waha.get(`/api/${chatSession.value}/chats/${encodeURIComponent(chatId)}/messages`, {
      params: { limit: 50, downloadMedia: true }
    })
    const rawMsgs = (res.data || []).reverse()

    // Fetch media blobs with auth header
    messages.value = await Promise.all(rawMsgs.map(async (msg) => {
      if (msg.hasMedia && msg.media?.url && msg.media?.mimetype?.startsWith('image/')) {
        const mediaUrl = msg.media.url.startsWith('http') ? msg.media.url : `${WAHA_URL}${msg.media.url}`
        const isWahaUrl = mediaUrl.includes('localhost') || msg.media.url.startsWith('/api/')
        if (isWahaUrl) {
          try {
            const mediaRes = await waha.get(mediaUrl, { responseType: 'blob' })
            msg.media._blobUrl = URL.createObjectURL(mediaRes.data)
          } catch {
            msg.media._blobUrl = mediaUrl
          }
        } else {
          // External CDN — use directly
          msg.media._blobUrl = mediaUrl
        }
      }
      return msg
    }))

    await nextTick()
    // Always show scroll button after load, then auto-scroll
    showScrollBtn.value = true
    setTimeout(() => scrollToBottom(), 50)
  } catch (e) { err(e, 'Failed to load messages') }
  finally { loadingMessages.value = false }
}

const imagePreviewVisible = ref(false)
const imagePreviewUrl     = ref('')

const scrollToBottom = () => {
  if (msgBottomEl.value) {
    msgBottomEl.value.scrollIntoView({ block: 'end' })
    return
  }
  const el = messagesEl.value
  if (el) el.scrollTop = el.scrollHeight
}

const onMessagesScroll = () => {
  const el = messagesEl.value
  if (!el) return
  // Show button when more than 100px from bottom
  showScrollBtn.value = el.scrollHeight - el.scrollTop - el.clientHeight > 100
}

const openMedia = (url) => {  imagePreviewUrl.value    = url
  imagePreviewVisible.value = true
}

// WAHA returns unreadCount in different fields depending on engine
const getUnread = (chat) => {
  return chat.unreadCount ?? chat._chat?.unreadCount ?? chat.unread ?? 0
}

const getChatId = (chat) => {
  if (!chat) return ''
  if (typeof chat.id === 'object') return chat.id.user + '@' + (chat.id.server || 'c.us')
  return chat.id
}

const markRead = async (chat) => {
  const chatId = getChatId(chat)
  try {
    await waha.post('/api/sendSeen', { session: chatSession.value, chatId })
    ok('Marked as read')
  } catch (e) { err(e) }
}

const sendReply = async () => {
  if (!replyText.value || !selectedChat.value) return
  const chatId = getChatId(selectedChat.value)
  const text   = replyText.value
  sending.value = true

  // Optimistic update
  const optimisticMsg = {
    id: `optimistic-${Date.now()}`,
    fromMe: true,
    body: text,
    timestamp: Math.floor(Date.now() / 1000),
    hasMedia: false,
  }
  messages.value.push(optimisticMsg)
  replyText.value = ''
  await nextTick()
  showScrollBtn.value = true
  setTimeout(() => scrollToBottom(), 30)

  try {
    await waha.post('/api/sendText', { session: chatSession.value, chatId, text })
    setTimeout(async () => {
      await fetchMessages(chatId)
      fetchChats()
    }, 800)
  } catch (e) {
    messages.value = messages.value.filter(m => m.id !== optimisticMsg.id)
    replyText.value = text
    err(e, 'Failed to send')
  } finally {
    sending.value = false
  }
}

// ── contacts ───────────────────────────────────────────────────────────────
const fetchContacts = async () => {
  loadingContacts.value = true
  try {
    const res = await waha.get('/api/contacts/all', {
      params: { session: contactSession.value, limit: 200 }
    })
    contacts.value = res.data || []
  } catch (e) { err(e, 'Failed to load contacts') }
  finally { loadingContacts.value = false }
}

const checkNumber = async () => {
  checking.value = true
  try {
    const phone = checkForm.value.phone.replace(/\D/g, '')
    const res = await waha.get('/api/contacts/check-exists', {
      params: { phone: phone + '@c.us', session: checkForm.value.session }
    })
    const exists = res.data?.numberExists ?? res.data?.exists
    toast.add({
      severity: exists ? 'success' : 'warn',
      summary: exists ? 'Registered' : 'Not Registered',
      detail: exists ? `${checkForm.value.phone} is on WhatsApp` : `${checkForm.value.phone} is NOT on WhatsApp`,
      life: 4000
    })
  } catch (e) { err(e, 'Failed to check number') }
  finally { checking.value = false }
}

const quickSendTo = (chatId) => {
  sendForm.value.chatId = chatId
  tab.value = 'send'
}

onMounted(async () => {
  clearWahaUnread()
  if (!wahaEnabled) return
  await init()
  // Hanya load chat jika session sudah aktif
  if (sessionStatus.value === 'WORKING') {
    fetchChats()
  }
})

// Auto-update when incoming message arrives via WebSocket
let unsubWaha = null
onMounted(() => {
  if (!wahaEnabled) return
  unsubWaha = onWahaMessage((payload) => {
    const incomingChatId = payload.from

    // If this chat is currently open, add message optimistically + refresh
    if (selectedChat.value) {
      const openChatId = getChatId(selectedChat.value)
      const normalizedIncoming = incomingChatId?.replace(/@.*$/, '')
      const normalizedOpen     = openChatId?.replace(/@.*$/, '')

      if (normalizedIncoming === normalizedOpen) {
        // Add incoming message directly to UI
        messages.value.push({
          id:        payload.id || `ws-${Date.now()}`,
          fromMe:    false,
          body:      payload.body || '',
          timestamp: payload.timestamp || Math.floor(Date.now() / 1000),
          hasMedia:  payload.hasMedia || false,
          media:     payload.media || null,
        })
        nextTick(() => {
          showScrollBtn.value = true
          setTimeout(() => scrollToBottom(), 30)
        })
      }
    }

    // Refresh chat list hanya jika session aktif
    if (sessionStatus.value === 'WORKING') fetchChats()
  })
})

onUnmounted(() => {
  if (unsubWaha) unsubWaha()
  if (qrCountdownInterval) clearInterval(qrCountdownInterval)
  if (qrAutoRefreshTimer)  clearTimeout(qrAutoRefreshTimer)
})
</script>

<style scoped>
.wa-view { padding: 1.5rem; }

.wa-disabled-banner {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  margin-bottom: 1.25rem;
  border: 1px solid var(--p-yellow-300, #fde68a);
  background: var(--p-yellow-50, #fffbeb);
  color: var(--p-yellow-900, #713f12);
  border-radius: 6px;
}
.wa-disabled-banner i { font-size: 1.25rem; line-height: 1.5; }
.wa-disabled-hint { font-size: 0.875rem; margin-top: 0.25rem; }
.wa-disabled-banner code {
  padding: 0 0.25rem;
  background: rgba(0,0,0,0.06);
  border-radius: 3px;
  font-size: 0.85em;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.page-header-left { display: flex; align-items: center; gap: 0.75rem; }
.page-header h2 { margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.ml-2 { margin-left: 0.25rem; }
.header-actions { display: flex; align-items: center; gap: 0.25rem; }

.action-active {
  color: #25d366 !important;
  background: #f0fdf4 !important;
}

.tab-content { animation: fadeIn 0.15s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

/* Status grid */
.status-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.status-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: white;
}
.status-card i { font-size: 1.5rem; }
.status-card.ok  { border-color: #22c55e; background: #f0fdf4; }
.status-card.ok i { color: #22c55e; }
.status-card.warn { border-color: #f59e0b; background: #fffbeb; }
.status-card.warn i { color: #f59e0b; }
.status-card.err  { border-color: #ef4444; background: #fef2f2; }
.status-card.err i { color: #ef4444; }
.status-card.off  { border-color: #e5e7eb; background: #f9fafb; }
.status-card.off i { color: #9ca3af; }
.sc-label { font-size: 0.7rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
.sc-val   { font-weight: 600; font-size: 0.9rem; }

/* QR */
/* QR Banner (halaman utama) */
.qr-banner { margin-bottom: 1rem; border: 2px solid #25d366; }
.qr-banner-inner {
  display: flex;
  gap: 2rem;
  align-items: flex-start;
  flex-wrap: wrap;
}
.qr-banner-info { flex: 1; min-width: 220px; }
.qr-banner-code { flex-shrink: 0; }

.qr-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}
.qr-box { padding: 1rem; background: white; border: 1px solid #e5e7eb; border-radius: 8px; }
.qr-box img { width: 220px; height: 220px; display: block; }
.qr-placeholder {
  width: 220px; height: 220px;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  background: #f3f4f6; border-radius: 8px; color: #9ca3af;
}
.qr-placeholder i { font-size: 3rem; margin-bottom: 0.5rem; }

/* Session controls */
.session-name-row { display: flex; align-items: center; gap: 1rem; }
.session-name-row label { font-weight: 600; }
.btn-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.btn-row-sm { display: flex; gap: 0.25rem; }
.mt-2 { margin-top: 0.75rem; }
.mt-3 { margin-top: 1.5rem; }
.mb-3 { margin-bottom: 1.5rem; }

/* Send grid */
.send-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
}
.form-col { display: flex; flex-direction: column; gap: 0.875rem; }
.form-field { display: flex; flex-direction: column; gap: 0.35rem; }
.form-field label { font-weight: 600; font-size: 0.85rem; }
.form-field small { color: #6b7280; font-size: 0.75rem; }

/* Chats layout */
.chats-layout {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 1rem;
  height: 600px;
}
.chat-list-panel, .messages-panel {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #e5e7eb;
  font-weight: 600;
  background: #f9fafb;
}
.chat-items { flex: 1; overflow-y: auto; }
.chat-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  cursor: pointer;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.1s;
}
.chat-item:hover { background: #f9fafb; }
.chat-item.active { background: #f0fdf4; }
.chat-info { flex: 1; min-width: 0; }
.chat-name { font-weight: 600; font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-last { font-size: 0.75rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-meta { flex-shrink: 0; }

.messages-panel-body {
  flex: 1;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.messages-list {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  background: #f0f2f5;
  overflow-anchor: none;
}

.scroll-down-btn {
  position: absolute;
  bottom: 0.75rem;
  left: 50%;
  transform: translateX(-50%);
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: white;
  border: 1px solid #e5e7eb;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #374151;
  font-size: 0.9rem;
  transition: box-shadow 0.15s, transform 0.15s;
  z-index: 10;
}
.scroll-down-btn:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  transform: translateX(-50%) scale(1.08);
}

.scroll-btn-enter-active, .scroll-btn-leave-active { transition: opacity 0.2s, transform 0.2s; }
.scroll-btn-enter-from { opacity: 0; transform: translateX(-50%) translateY(8px); }
.scroll-btn-leave-to   { opacity: 0; transform: translateX(-50%) translateY(8px); }
.msg-bubble {
  max-width: 70%;
  padding: 0.5rem 0.75rem;
  border-radius: 8px;
  font-size: 0.875rem;
  width: fit-content;
}
.msg-bubble.has-image {
  padding: 0.25rem;
}
.from-me   { align-self: flex-end; background: #dcf8c6; border-radius: 8px 0 8px 8px; }
.from-them { align-self: flex-start; background: white; border-radius: 0 8px 8px 8px; }
.msg-body { word-break: break-word; }
.msg-body-caption { padding: 0.25rem 0.5rem 0; }
.msg-time  { font-size: 0.65rem; color: #9ca3af; text-align: right; margin-top: 0.2rem; padding: 0 0.25rem 0.25rem; }

.reply-bar {
  display: flex;
  gap: 0.5rem;
  padding: 0.75rem;
  border-top: 1px solid #e5e7eb;
  background: white;
}

.empty-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #9ca3af;
}
.empty-center i { font-size: 3rem; margin-bottom: 0.5rem; }
.empty-hint { text-align: center; padding: 2rem; color: #9ca3af; font-size: 0.875rem; }
.loading-center { display: flex; justify-content: center; padding: 2rem; }

/* Contacts */
.chat-avatar { flex-shrink: 0; width: 40px; height: 40px; }
.avatar-wrap { position: relative; width: 40px; height: 40px; display: inline-block; }
.avatar-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.unread-badge {
  position: absolute !important;
  top: -4px !important;
  right: -6px !important;
  min-width: 18px;
  height: 18px;
  font-size: 0.65rem;
  padding: 0 4px;
}
.chat-last-unread { font-weight: 600; color: #111827; }
.chat-time { font-size: 0.7rem; color: #9ca3af; white-space: nowrap; }

.msg-media { margin-bottom: 0.25rem; }
.msg-image {
  max-width: 280px;
  max-height: 360px;
  border-radius: 6px;
  cursor: pointer;
  display: block;
  width: auto;
  height: auto;
  object-fit: contain;
  transition: opacity 0.15s;
}
.msg-image:hover { opacity: 0.88; }
.msg-file {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.4rem 0.6rem;
  background: rgba(0,0,0,0.06);
  border-radius: 6px;
  font-size: 0.8rem;
  color: inherit;
  text-decoration: none;
}
.img-preview-wrap {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.img-preview-full {
  max-width: 85vw;
  max-height: 85vh;
  border-radius: 8px;
  display: block;
  object-fit: contain;
}
.img-preview-close {
  position: absolute;
  top: 0;
  right: 0;
}

.msg-media-loading {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.5rem;
  color: #9ca3af;
  font-size: 0.8rem;
}
.msg-unsupported { color: #9ca3af; font-size: 0.8rem; font-style: italic; }

.contacts-toolbar { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }

@media (max-width: 1024px) {
  .status-grid { grid-template-columns: repeat(2, 1fr); }
  .send-grid   { grid-template-columns: 1fr; }
  .chats-layout { grid-template-columns: 1fr; height: auto; }
}
</style>
