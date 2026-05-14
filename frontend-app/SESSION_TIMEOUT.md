# Session Timeout & Auto Logout

## Konfigurasi

### Backend (Laravel)
- **Session Lifetime**: 120 menit (default)
- Konfigurasi: `backend-api/config/session.php`
- Environment variable: `SESSION_LIFETIME`

### Frontend (Vue.js)
- **Idle Timeout**: 1 menit (saat ini)
- Konfigurasi: `frontend-app/src/layouts/DashboardLayout.vue`
- Composable: `frontend-app/src/composables/useIdleTimeout.js`

## Cara Kerja

1. **Idle Detection**: Sistem mendeteksi aktivitas user (mouse, keyboard, scroll, touch, click)
2. **Timer Reset**: Setiap ada aktivitas, timer akan direset
3. **Auto Logout**: Jika tidak ada aktivitas selama waktu yang ditentukan, user akan otomatis logout
4. **Session Expired Flag**: Saat logout otomatis, flag `session_expired` disimpan di localStorage
5. **Modal Notification**: Saat user redirect ke login, modal akan muncul memberitahu session expired

## Mengubah Timeout Duration

### Untuk mengubah timeout di frontend:

Edit file `frontend-app/src/layouts/DashboardLayout.vue`:

```javascript
// Ubah angka 1 menjadi durasi yang diinginkan (dalam menit)
useIdleTimeout(1)  // 1 menit
useIdleTimeout(5)  // 5 menit
useIdleTimeout(15) // 15 menit
useIdleTimeout(30) // 30 menit
```

### Untuk mengubah timeout di backend:

Edit file `backend-api/.env`:

```env
SESSION_LIFETIME=120  # dalam menit
```

## Events yang Dimonitor

Sistem mendeteksi aktivitas user melalui events berikut:
- `mousedown` - Klik mouse
- `mousemove` - Gerakan mouse
- `keypress` - Ketikan keyboard
- `scroll` - Scroll halaman
- `touchstart` - Touch pada mobile
- `click` - Klik umum

## Testing

Untuk testing, set timeout ke 1 menit:
```javascript
useIdleTimeout(1)
```

Kemudian:
1. Login ke aplikasi
2. Jangan lakukan aktivitas apapun selama 1 menit
3. Sistem akan otomatis logout
4. Redirect ke halaman login dengan modal "Session Expired"

## Notes

- Timer hanya aktif saat user sudah login (authenticated)
- Timer akan dibersihkan saat component unmounted
- Session expired flag akan dihapus setelah modal ditampilkan
- Logout otomatis juga memanggil API logout ke backend
