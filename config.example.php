<?php

return [
    'timezone' => 'Asia/Jakarta',
    'database' => [ //sesuaikan dengan db anda
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'network_monitor',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'n8n_webhook' => 'http://localhost:5678/webhook/network-alert', //sesuaikan dengan n8n anda
    'ping_timeout_ms' => 1000,
    'hosts' => [ //hosts dapat diganti sesuai kebutuhan
        'UNILA'        => 'unila.ac.id',
        'Kompas'       => 'kompas.com',
        'Google'       => 'google.com',
        'Vclass Unila' => 'vclass.unila.ac.id',
        'Chat GPT'     => 'chatgpt.com',
        'YouTube'      => 'youtube.com',
        'GitHub'       => 'github.com',
        'Test VM'      => '192.168.1.8',
    ],
];
