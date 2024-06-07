<?php
ini_set('display_errors', true);


/* Gunakan bot library */
require_once './bot.php';

/* Setup onesender gateway */
$bot = Bot::setup('https://gateway.com/api/v1/messages', '_YOUR_ONESENDER_API_KEY_');

/* Masukkan api key gemini */
$bot->withGeminiAI('_YOUR_GEMINI_API_KEY_');

/* Jawaban otomatis untuk intent: "salam" 

Intent adalah maksud atau tujuan dari satu pesan

contoh:
- assalamulaikum: salam
- Selamat pagi: salam
- Apa kabar?: salam
- Saya mau beli produk: beli_produk
*/
$bot->onIntent('salam')
    ->reply('Waalaikum salam');

$bot->onIntent('beli_produk')
    ->reply('Silahkan diborong. Senin harga naik');


/* Kirim pesan */
$bot->send();

