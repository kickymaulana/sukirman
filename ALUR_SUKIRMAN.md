# Alur Lengkap Aplikasi SUKIRMAN
## Sistem Usulan Kebutuhan Barang Internal Manajemen

---

## 👤 1. Supervisor (Pembuat MR)

### Alur:
```
Login → Dashboard → klik "+" (FAB) → Buat MR → isi barang → Simpan
```

### Tombol & Aksi:
| Tombol | Lokasi | Apa yang Terjadi |
|--------|--------|------------------|
| **+** (FAB) | Dashboard kanan bawah | Buka form MR baru |
| **Simpan** | Form Create | MR tersimpan, status: **"Pending Manager"** |
| **Klik MR** | Daftar MR / Riwayat | Buka detail MR |
| **Edit & Kirim Ulang** | Detail MR (khusus status **Revision**) | Buka form edit, perbaiki barang, kirim ulang → status balik ke **"Pending Manager"** |

### Skenario Khusus — Revision:
```
Supervisor buka MR → lihat catatan revisi dari Direksi
→ klik "Edit & Kirim Ulang" → perbaiki barang → kirim
→ status balik ke "Pending Manager" → Manager review lagi
```

---

## 👤 2. Manager (Router)

### Alur:
```
Login → Dashboard → Klik card "Approval Manager" → klik MR 
→ lihat detail → klik "Forward ke Direksi" → pilih Direksi → Kirim
```

### Tombol & Aksi:
| Tombol | Lokasi | Apa yang Terjadi |
|--------|--------|------------------|
| **Card Approval Manager** | Dashboard | Buka daftar MR yang perlu di-forward |
| **Klik MR** | Daftar Approval | Buka detail MR |
| **Forward ke Direksi** | Detail MR | Muncul dialog pilih Direksi → klik Kirim → status jadi **"Pending FM/GM"** → notif ke FM/GM |

---

## 👤 3. FM / GM (Acknowledge)

### Alur:
```
Login → Dashboard → Klik card "Approval FM/GM" → klik MR 
→ lihat detail → klik "Acknowledge"
```

### Tombol & Aksi:
| Tombol | Lokasi | Apa yang Terjadi |
|--------|--------|------------------|
| **Card Approval FM/GM** | Dashboard | Buka daftar MR yang perlu di-acknowledge |
| **Klik MR** | Daftar Approval | Buka detail MR |
| **Acknowledge** | Detail MR | Status jadi **"Pending Direksi"** → notif ke Direksi yang dipilih |

---

## 👤 4. Direksi (Final Approver)

### Alur:
```
Login → Dashboard → Klik card "Approval Direksi" → klik MR 
→ lihat detail → pilih keputusan
```

### Tombol & Aksi:
| Tombol | Lokasi | Apa yang Terjadi |
|--------|--------|------------------|
| **Card Approval Direksi** | Dashboard | Buka daftar MR yang perlu keputusan |
| **Klik MR** | Daftar Approval | Buka detail MR |
| **Approve** | Detail MR | Status jadi **"Verifikasi Gudang"** → notif ke Gudang |
| **Reject** | Detail MR | Status jadi **"Rejected"** → notif ke Supervisor |
| **Revision** | Detail MR | Muncul dialog isi catatan → status jadi **"Revision"** → Supervisor bisa edit |

---

## 👤 5. Orang Gudang (Inventory Control)

### Alur:
```
Login → Dashboard → Klik card "Verifikasi Gudang" → klik MR 
→ lihat detail → cek stok fisik
```

### Tombol & Aksi:
| Tombol | Lokasi | Apa yang Terjadi |
|--------|--------|------------------|
| **Card Verifikasi Gudang** | Dashboard | Buka daftar MR yang perlu dicek stok |
| **Klik MR** | Daftar Approval | Buka detail MR |
| **Stok Tersedia** | Detail MR | Status jadi **"Fully Approved"** → notif ke Supervisor (barang siap) |
| **Stok Tidak Ada** | Detail MR | Status jadi **"Purchasing"** → notif ke Purchasing |

---

## 👤 6. Purchasing (Executor)

### Alur:
```
Login → Dashboard → Klik card "Purchasing" 
→ lihat daftar MR → klik "Download CSV" → import ke Accurate
```

### Tombol & Aksi:
| Tombol | Lokasi | Apa yang Terjadi |
|--------|--------|------------------|
| **Card Purchasing** | Dashboard | Buka daftar MR siap beli |
| **⬇ Download CSV** | Halaman Purchasing | Download file CSV untuk di-import ke Accurate Offline |

---

## 📊 Diagram Alur Lengkap

```
Supervisor ──buat MR──▶ Pending Manager
                            │
                      Manager ──forward──▶ Pending FM/GM
                            │               │
                                      FM/GM ──acknowledge──▶ Pending Direksi
                                                                │
                                                          ┌─────┼─────┐
                                                          │     │     │
                                                    Approve  Reject Revision
                                                          │     │     │
                                                          ▼     ▼     ▼
                                              Verifikasi Gudang  ✗  Kembali ke Supervisor
                                                    │
                                               ┌─────┴─────┐
                                               │           │
                                          Stok Ada   Stok Tak Ada
                                               │           │
                                               ▼           ▼
                                        Fully Approved  Purchasing
                                                            │
                                                            ▼
                                                    Download CSV → Accurate
```

---

## 🔔 Notifikasi (Bell di Bottom Nav)

| Kejadian | Diterima Oleh | Isi Notifikasi |
|----------|--------------|----------------|
| Manager forward MR | FM/GM | "MR MR-xxx menunggu acknowledge Anda" |
| FM/GM acknowledge | Direksi terpilih | "MR MR-xxx menunggu keputusan Anda" |
| Direksi approve | Gudang | "MR MR-xxx disetujui, perlu verifikasi gudang" |
| Direksi reject | Supervisor | "MR MR-xxx ditolak: [alasan]" |
| Gudang: stok tersedia | Supervisor | "MR MR-xxx — barang tersedia di gudang" |
| Gudang: stok tidak ada | Purchasing | "MR MR-xxx — stok tidak ada, perlu pembelian" |

Klik notifikasi → langsung buka detail MR yang bersangkutan.

---

## 🔐 Hak Akses Per Role

| Fitur | Supervisor | Manager | FM/GM | Direksi | Gudang | Purchasing |
|-------|:---------:|:-------:|:-----:|:-------:|:------:|:----------:|
| Buat MR | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Lihat MR sendiri | ✅ | ✅* | ✅* | ✅* | ✅* | ✅* |
| Forward | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Acknowledge | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Approve/Reject/Revision | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Verifikasi Stok | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Export CSV | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Edit MR Revision | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

*\* — yang sesuai dengan tahap workflow-nya*

---

*Dokumen alur aplikasi SUKIRMAN — PT Mark Dynamics Indonesia Tbk*
