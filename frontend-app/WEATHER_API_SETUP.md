# Weather Widget Setup

## ✅ Menggunakan Open-Meteo API (GRATIS, TANPA API KEY!)

Widget cuaca sekarang menggunakan **Open-Meteo API** - API cuaca yang **100% GRATIS** dan **TIDAK MEMERLUKAN API KEY**!

### Tentang Open-Meteo

- **Website**: https://open-meteo.com/
- **Biaya**: 100% GRATIS untuk penggunaan non-komersial
- **API Key**: TIDAK DIPERLUKAN ✅
- **Rate Limit**: Hingga 10,000 API calls per hari (gratis)
- **Sumber Data**: Layanan cuaca nasional di seluruh dunia
- **Resolusi**: Resolusi tinggi (1-11 km)
- **Update**: Update setiap jam
- **Data Historis**: 80+ tahun data cuaca tersedia
- **Open Source**: Kode sumber lengkap tersedia di GitHub

### Fitur

✅ Tidak perlu registrasi  
✅ Tidak perlu API key  
✅ Tidak perlu kartu kredit  
✅ Data cuaca real-time  
✅ Prakiraan akurat dari layanan cuaca nasional  
✅ Update setiap jam  
✅ Waktu sunrise/sunset  
✅ Kecepatan dan arah angin  
✅ Kelembaban, tekanan, tutupan awan  
✅ Kode cuaca dengan deskripsi  

### API Endpoint yang Digunakan

```
https://api.open-meteo.com/v1/forecast
```

### Parameter

- `latitude` & `longitude`: Koordinat kota
- `current`: Parameter cuaca saat ini (suhu, kelembaban, angin, dll)
- `daily`: Data harian (sunrise, sunset)
- `timezone`: Asia/Jakarta

### Kota yang Didukung

Widget mencakup 10 kota besar di Indonesia:
1. Jakarta
2. Surabaya
3. Bandung
4. Medan
5. Semarang
6. Makassar
7. Palembang
8. Tangerang
9. Depok
10. Bekasi

## Cara Kerja

### Di Halaman Utilities (Weather Tab)

1. **Pilih Kota**: User memilih kota dari dropdown (10 kota besar Indonesia)
2. **Gunakan Lokasi Saya**: User klik tombol untuk menggunakan GPS lokasi saat ini
3. App mengambil data cuaca dari Open-Meteo API menggunakan koordinat
4. Data cuaca ditampilkan dengan ikon, suhu, dan detail lengkap
5. Data auto-refresh setiap 10 menit

### Di Dashboard Outlet (Weather Widget)

Widget cuaca di dashboard **otomatis menggunakan lokasi Anda saat ini**:

1. Saat halaman dimuat, browser meminta izin akses lokasi
2. Jika diizinkan, app mendapatkan koordinat GPS Anda
3. App mengambil cuaca untuk lokasi Anda dari Open-Meteo API
4. App mendapatkan nama kota dari koordinat menggunakan **Nominatim Reverse Geocoding** (OpenStreetMap)
5. Widget menampilkan: "Nama Kota Anda - 28°C - Berawan"
6. Jika lokasi gagal/ditolak, fallback ke Jakarta sebagai default

### Fitur "Gunakan Lokasi Saya"

Tombol ini menggunakan browser Geolocation API untuk:
- Mendapatkan koordinat GPS Anda saat ini (latitude & longitude)
- Mengambil data cuaca real-time untuk lokasi Anda
- Menampilkan cuaca lokal tanpa perlu memilih kota

**Catatan**: Browser akan meminta izin akses lokasi saat pertama kali digunakan.

### Reverse Geocoding (Koordinat → Nama Kota)

Menggunakan **Nominatim API** dari OpenStreetMap:
- **Gratis** dan **tanpa API key**
- Mengkonversi latitude/longitude menjadi nama kota
- Mendukung bahasa Indonesia
- Fallback hierarchy: city → town → village → county → state

## Tidak Perlu Setup!

Karena Open-Meteo tidak memerlukan API key, widget cuaca langsung berfungsi tanpa konfigurasi apapun. Tinggal jalankan aplikasi dan akan langsung bekerja!

## Dokumentasi

Untuk informasi lebih lanjut:

### Open-Meteo API (Weather Data)
- Dokumentasi: https://open-meteo.com/en/docs
- GitHub: https://github.com/open-meteo/open-meteo
- Lisensi: AGPL-3.0 (Open Source)
- Lisensi Data: CC BY 4.0

### Nominatim API (Reverse Geocoding)
- Dokumentasi: https://nominatim.org/release-docs/latest/api/Reverse/
- Website: https://nominatim.openstreetmap.org/
- Lisensi: ODbL 1.0 (OpenStreetMap)
- Usage Policy: https://operations.osmfoundation.org/policies/nominatim/

## Penggunaan Komersial

Jika Anda memerlukan penggunaan komersial atau lebih dari 10,000 API calls per hari, Open-Meteo menawarkan subscription berbayar mulai dari €10/bulan. Kunjungi https://open-meteo.com/en/pricing untuk detail.

## Atribusi

Saat menggunakan API ini, mohon berikan kredit yang sesuai:

**Weather Data:**
"Data cuaca oleh Open-Meteo.com"

**Location Data:**
"Data lokasi © OpenStreetMap contributors"

## Keuntungan Dibanding OpenWeatherMap

| Fitur | Open-Meteo | OpenWeatherMap |
|-------|------------|----------------|
| API Key | ❌ Tidak perlu | ✅ Perlu |
| Registrasi | ❌ Tidak perlu | ✅ Perlu |
| Biaya | 💯 Gratis | 💰 Gratis terbatas |
| Rate Limit | 10,000/hari | 60/menit |
| Aktivasi | ⚡ Instant | ⏰ 10 menit - 2 jam |
| Open Source | ✅ Ya | ❌ Tidak |

## Troubleshooting

**Tidak ada masalah!** 🎉

Karena tidak ada API key, tidak ada masalah aktivasi, tidak ada error 401, dan tidak ada rate limit yang ketat. Semuanya langsung bekerja!

Jika ada error koneksi, pastikan:
- Koneksi internet aktif
- Firewall tidak memblokir api.open-meteo.com
