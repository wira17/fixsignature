# FixSignature  
**Aplikasi Tanda Tangan Elektronik (TTE) Non Sertifikasi**

FixSignature adalah aplikasi berbasis web untuk melakukan **Tanda Tangan Elektronik (TTE) Non Sertifikasi** pada dokumen digital (PDF).  
Aplikasi ini dirancang untuk kebutuhan internal instansi/organisasi, dengan fokus pada **keaslian dokumen, integritas data, dan kemudahan verifikasi**.

---

## 📌 Dasar Hukum Tanda Tangan Elektronik

Penggunaan Tanda Tangan Elektronik Non Sertifikasi dalam aplikasi ini mengacu pada peraturan perundang-undangan di Indonesia, antara lain:

1. **Undang-Undang Republik Indonesia Nomor 11 Tahun 2008**  
   tentang **Informasi dan Transaksi Elektronik (UU ITE)**

2. **Undang-Undang Republik Indonesia Nomor 19 Tahun 2016**  
   tentang **Perubahan atas UU Nomor 11 Tahun 2008 tentang ITE**

3. **Peraturan Pemerintah Republik Indonesia Nomor 71 Tahun 2019**  
   tentang **Penyelenggaraan Sistem dan Transaksi Elektronik (PSTE)**

Dalam regulasi tersebut disebutkan bahwa:
- Tanda Tangan Elektronik terdiri dari **TTE Tersertifikasi** dan **TTE Tidak Tersertifikasi**
- TTE Non Sertifikasi **tetap memiliki kekuatan hukum dan akibat hukum yang sah**, selama memenuhi persyaratan keabsahan tanda tangan elektronik

---

## 🎯 Fitur Utama

### 👤 Menu Pengguna
- **Dokumen Saya**  
  Melihat dan mengelola dokumen TTE milik pengguna
- **Profil**  
  Mengelola data profil pengguna
- **Generate TTE**  
  Membuat TTE Non Sertifikasi untuk dokumen
- **Cek Keabsahan TTE**  
  Memeriksa keaslian dan validitas TTE pada dokumen
- **TTE Dokumen**  
  Proses pembubuhan TTE pada file PDF
- **Kirim Dokumen TTE via Email (SMTP)**  
  Mengirim dokumen yang telah ditandatangani melalui email

---

### 🛠️ Menu Admin
- **Kelola Pengguna**
- **Dokumen TTE**
- **Kelola TTE**
- **Hak Akses**
- **Log Aktivitas**
- **Mail Settings**
- **Laporan**

---

## 🔐 Metode Tanda Tangan Elektronik (TTE)

Aplikasi FixSignature menggunakan metode **TTE Non Sertifikasi** dengan mekanisme sebagai berikut:

1. Sistem melakukan **generate TTE** dalam bentuk **barcode (QR Code)**  
2. Barcode dilengkapi dengan **metadata TTE**, antara lain:
   - Identitas penandatangan
   - Waktu penandatanganan
   - Informasi dokumen
   - Token unik verifikasi
3. Setelah proses berhasil:
   - **Barcode TTE dibubuhkan langsung ke file PDF**
   - **Metadata TTE disematkan (embedded) ke dalam file PDF**
4. Sistem menyediakan fitur **Cek Keabsahan TTE** untuk validasi dokumen

---

## ✅ Mekanisme Keabsahan Dokumen

- Jika **file PDF atau TTE dimodifikasi/dipalsukan**:
  - Barcode tidak lagi sesuai
  - Metadata tidak valid
  - Sistem akan menampilkan status **TTE Tidak Valid**
- Jika dokumen **masih asli dan tidak berubah**:
  - Barcode terdeteksi
  - Metadata sesuai
  - Sistem menampilkan status **TTE Valid**

Dengan mekanisme ini, integritas dan keaslian dokumen dapat terjaga meskipun menggunakan **TTE Non Sertifikasi**.

---

## 📞 Kontak & Dukungan

Jika ada pertanyaan, saran, atau ingin mendukung pengembangan **FixSignature**, silakan hubungi melalui:

### 💰 Donasi / Dukungan Finansial
- **Bank**: BSI  
- **No. Rekening**: 7134197557  
- **Atas Nama**: M. Wira Satria Buana  

### 📧 Kontak
- **Email**: wiramuhammad16@gmail.com  
- **Telepon / WhatsApp**: 0821 7784 6209  

Terima kasih atas dukungan dan kontribusinya 🙏



## 📄 Catatan
Aplikasi ini ditujukan untuk kebutuhan internal organisasi/instansi dan **bukan merupakan Penyelenggara Sertifikat Elektronik (PSrE)**.

---
