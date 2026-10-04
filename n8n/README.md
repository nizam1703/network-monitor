# n8n

Workflow:

```text
Webhook -> AI Agent -> Telegram
```

Webhook:

```text
POST /webhook/network-alert
```

Prompt AI singkat:

```text
Anda adalah AI Network Monitoring.
Analisis event jaringan berikut dalam SATU kalimat.
Maksimal 15 kata.
Gunakan Bahasa Indonesia.
Jangan gunakan daftar atau penjelasan tambahan.

Event: {{$json.body.event}}
Host: {{$json.body.host}}
Status: {{$json.body.status}}
Latency: {{$json.body.latency}}
Duration: {{$json.body.duration_text}}
```

Jangan commit token Telegram, API key Gemini, atau credential n8n.
