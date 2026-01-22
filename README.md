# FixSignature  
**Aplikasi Tanda Tangan Elektronik (TTE) Non Sertifikasi**

FixSignature adalah aplikasi berbasis web untuk melakukan **Tanda Tangan Elektronik (TTE) Non Sertifikasi** pada dokumen digital (PDF).  
Aplikasi ini dirancang untuk kebutuhan internal instansi/organisasi, dengan fokus pada **keaslian dokumen, integritas data, dan kemudahan verifikasi**.
Turorila : https://youtu.be/vWP870AWZcE?si=qDa8RuDZgvKyNNDv

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

<img width="1437" height="792" alt="Screen Shot 2026-01-20 at 13 23 34" src="https://github.com/user-attachments/assets/0980b0f9-7e00-4d7e-add8-3d30c49a9bc6" />

## 🎯 Fitur Utama

### 👤 Menu Pengguna
- **Dokumen Saya**  
  Melihat dan mengelola dokumen TTE milik pengguna
- **Profil**
  <img width="1440" height="806" alt="Screen Shot 2026-01-20 at 13 23 55" src="https://github.com/user-attachments/assets/cfd031f2-86ed-4aee-8d87-dc3e004bacf5" />

  Mengelola data profil pengguna
- **Generate TTE**
  <img width="1440" height="797" alt="Screen Shot 2026-01-20 at 13 24 19" src="https://github.com/user-attachments/assets/30083987-38ae-4cf6-ae90-49c952a52566" />

  Membuat TTE Non Sertifikasi untuk dokumen
- **Cek Keabsahan TTE**
  <img width="1439" height="809" alt="Screen Shot 2026-01-20 at 13 25 36" src="https://github.com/user-attachments/assets/11953921-f278-4ad2-899f-8cab1633d205" />
 
  Memeriksa keaslian dan validitas TTE pada dokumen
- **TTE Dokumen**
  <img width="1440" height="795" alt="Screen Shot 2026-01-20 at 13 24 47" src="https://github.com/user-attachments/assets/b4c3b56b-40e2-44c2-a82b-71b7b67338d6" />
  Proses pembubuhan TTE pada file PDF
  
- **Kirim Dokumen TTE via Email (SMTP)**
  <img width="1440" height="801" alt="Screen Shot 2026-01-20 at 13 26 06" src="https://github.com/user-attachments/assets/6ca57b07-c8ce-4cc3-b10f-196efec13d10" />
 
  Mengirim dokumen yang telah ditandatangani melalui email
  <img width="1440" height="799" alt="Screen Shot 2026-01-20 at 13 24 59" src="https://github.com/user-attachments/assets/ccde7ba3-084b-4ffd-a9b5-8e6614cc5781" />

  <img width="615" height="868" alt="Screen Shot 2026-01-20 at 13 27 17" src="https://github.com/user-attachments/assets/58aed369-ead7-4403-b424-eb37624a72bc" />


---

### 🛠️ Menu Admin
<img width="1440" height="811" alt="Screen Shot 2026-01-20 at 13 27 45" src="https://github.com/user-attachments/assets/aaca2bd6-b2df-4443-8944-318d0787448b" />

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
