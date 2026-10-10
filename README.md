# Network Monitoring Dashboard

Project monitoring jaringan berbasis PHP, MySQL, Chart.js, n8n, Gemini, dan Telegram.

## Struktur

```text
network-monitor/
├── index.php
├── ping.php
├── config.example.php
├── database.sql
├── .gitignore
├── README.md
└── n8n/
    └── worfkflow.json   --> bisa langsung di import pada n8n
```

## Instalasi

1. Letakkan project di `C:\laragon\www\network-monitor`.
2. Jalankan Apache dan MySQL di Laragon.
3. Import `database.sql`.
4. Salin `config.example.php` menjadi `config.php`.
5. Sesuaikan database, URL n8n, daftar host, dan IP VM.
6. Buka `http://localhost/network-monitor/`.

## Cara kerja

`index.php` memanggil `ping.php` secara realtime. `ping.php` melakukan ping ke semua host, menyimpan history ke MySQL, mendeteksi perubahan UP/DOWN, menghitung downtime, dan mengirim webhook ke n8n hanya saat INCIDENT atau RECOVERY.

## Catatan

Pada desain ini, monitoring dipicu oleh dashboard. Jika dashboard ditutup, `ping.php` berhenti dipanggil. Untuk monitoring 24/7, tahap berikutnya adalah memisahkan proses ping menjadi worker/service terjadwal.
