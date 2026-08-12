# Provider LLM TIFAA

TIFAA memakai provider LLM hanya untuk pemahaman intent sekolah yang belum dapat dikenali secara deterministik dan untuk percakapan umum. Statistik sekolah/guru tetap melalui service database dan formatter; record guru maupun identifier sensitif tidak dikirim ke provider.

## Pilihan provider

Pilih satu provider pada `.env`:

```env
TIFA_LLM_PROVIDER=ollama
# atau
TIFA_LLM_PROVIDER=groq
```

Ollama memakai `OLLAMA_BASE_URL`, `OLLAMA_MODEL`, dan `OLLAMA_TIMEOUT`.

Groq memakai:

```env
GROQ_API_KEY=
GROQ_BASE_URL=https://api.groq.com/openai/v1
GROQ_MODEL=
GROQ_TIMEOUT=15
```

Jangan memasukkan API key ke source control atau log. `TIFA_AI_PROVIDER` tetap didukung sementara sebagai nama konfigurasi lama, tetapi konfigurasi baru harus menggunakan `TIFA_LLM_PROVIDER`.

## Arsitektur

`TifaAssistantService` menjalankan greeting, privacy guard, local data intent, dan teacher analytics terlebih dahulu. Teacher analytics mengikuti `TeacherAnalyticsIntentService → TeacherDataTool → TeacherAnalyticsService → TifaResponseFormatter` tanpa LLM. Hanya fallback intent sekolah dan percakapan umum menggunakan `LlmProvider`, yang dapat berupa `OllamaLlmProvider` atau `GroqLlmProvider`.

## Pemeriksaan koneksi

Setelah mengisi model dan credential, jalankan:

```bash
php artisan config:clear
php artisan tifa:llm-status
```

Command menampilkan provider, model, status, dan latency tanpa API key.

## Troubleshooting

- **401/403:** periksa `GROQ_API_KEY` dan akses model.
- **429:** provider sedang rate limited; tunggu dan coba kembali.
- **Timeout/koneksi:** periksa jaringan, URL, serta `GROQ_TIMEOUT`/`OLLAMA_TIMEOUT`.
- **Invalid response:** periksa nama model dan respons provider; TIFAA tidak akan mengarang angka ketika intent tidak dapat diparse.
