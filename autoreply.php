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
