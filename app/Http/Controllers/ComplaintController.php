<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:8', 'max:25', 'regex:/^[0-9+\s\-()]+$/'],
            'complaint_type' => ['required', 'string', 'in:Pelayanan Pendidikan,Data Pendidikan,Sekolah,Guru / Tenaga Kependidikan,Sarana Prasarana,Lainnya'],
            'complaint_text' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'phone.required' => 'Nomor HP / WhatsApp wajib diisi.',
            'phone.regex' => 'Format nomor HP / WhatsApp tidak valid.',
            'complaint_type.required' => 'Pilih jenis pengaduan.',
            'complaint_type.in' => 'Jenis pengaduan yang dipilih tidak valid.',
            'complaint_text.required' => 'Isi pengaduan wajib diisi.',
            'complaint_text.min' => 'Isi pengaduan minimal 10 karakter.',
            'attachment.mimes' => 'Format file lampiran harus berupa PDF, JPG, JPEG, atau PNG.',
            'attachment.max' => 'Ukuran file lampiran maksimal 5MB.',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            // Private local storage (not accessible publicly via URL)
            $attachmentPath = $request->file('attachment')->store('complaints', 'local');
        }

        $complaint = Complaint::create([
            'name' => (string) $validated['name'],
            'phone' => (string) $validated['phone'],
            'complaint_type' => (string) $validated['complaint_type'],
            'complaint_text' => (string) $validated['complaint_text'],
            'attachment_path' => $attachmentPath,
        ]);

        $successMessage = 'Pengaduan Anda berhasil dikirim.';

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'id' => $complaint->id,
            ], 201);
        }

        return redirect()->to(url()->previous() . '#pengaduan')->with('complaint_success', $successMessage);
    }
}
