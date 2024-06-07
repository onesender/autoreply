# Simple Autoreply

Library sederhana untuk fitur autoreply OneSender

## Quick Start

- Download dua file di libary ini. File: autoreply.php dan bot.php
- Edit file autoreply.php

```php
<?php
ini_set('display_errors', true);

/* Gunakan bot library */
require_once './bot.php';

/* Setup onesender gateway */
$bot = Bot::setup('https://gateway.com/api/v1/messages', '_YOUR_ONESENDER_API_KEY_');

/* Jawaban otomatis untuk pesan: "Halo Bos" */
$bot->on('halo bos')
    ->reply('Halo apa kabar');

/* Jawaban otomatis untuk pesan: "Mahalo" */
$bot->on('mahalo')
    ->reply('Sorry, I don\'t understand your talk.');


/* Kirim pesan */
$bot->send();

```

- Upload 2 file tsb ke webhosting anda. Contoh hasil akhir urlnya: `http://bot.web.id/botwa/autoreply.php`.
- Ubah link webhook di halaman pengaturan onesender. Menu setting. Field: `Webhook endpoint URL`.

```
http://bot.web.id/botwa/autoreply.php
```


## Gemini AI
Menambahkan gemini untuk menjawab otomatis.

```php
$bot->withGeminiAI('_YOUR_GEMINI_API_KEY_');

$bot->onIntent('salam')
    ->reply('Waalaikum salam');

$bot->onIntent('beli_produk')
    ->reply('Silahkan diborong. Senin harga naik');

```
Keterangan
Method | Args
---|---
onIntent | intent atau kategori pertanyaan
reply | Jawaban dari bot


Intent adalah kategori, maksud atau tujuan dari satu pesan.

contoh:
- assalamulaikum: salam
- Selamat pagi: salam
- Apa kabar?: salam
- Saya mau beli produk: beli_produk

## Security

### Header key
Header key digunakan untuk mengamankan link webhook dari akses yang tidak diizinkan.

```php
$bot->withSecurity('_KODE_HEADER_VALUE_);

```

Diisi dengan kode `Header value` di halaman pengaturan OneSender.
