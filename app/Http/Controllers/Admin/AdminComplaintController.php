<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminComplaintController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = (string) $request->query('status', 'all');

        $query = Complaint::query();

        if (in_array($statusFilter, [Complaint::STATUS_BARU, Complaint::STATUS_DIPROSES, Complaint::STATUS_SELESAI], true)) {
            $query->where('status', $statusFilter);
        }

        $complaints = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $totalCount = Complaint::count();
        $baruCount = Complaint::where('status', Complaint::STATUS_BARU)->count();
        $diprosesCount = Complaint::where('status', Complaint::STATUS_DIPROSES)->count();
        $selesaiCount = Complaint::where('status', Complaint::STATUS_SELESAI)->count();

        return view('admin.complaints.index', [
            'complaints' => $complaints,
            'currentFilter' => $statusFilter,
            'totalCount' => $totalCount,
            'baruCount' => $baruCount,
            'diprosesCount' => $diprosesCount,
            'selesaiCount' => $selesaiCount,
        ]);
    }

    public function show(Complaint $complaint): View
    {
        return view('admin.complaints.show', [
            'complaint' => $complaint,
        ]);
    }

    public function updateStatus(Request $request, Complaint $complaint): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', [Complaint::STATUS_BARU, Complaint::STATUS_DIPROSES, Complaint::STATUS_SELESAI])],
        ], [
            'status.required' => 'Status pengaduan wajib dipilih.',
            'status.in' => 'Pilihan status tidak valid.',
        ]);

        $complaint->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Status pengaduan berhasil diperbarui.');
    }

    public function downloadAttachment(Complaint $complaint): StreamedResponse
    {
        if (empty($complaint->attachment_path)) {
            abort(404, 'Pengaduan ini tidak memiliki lampiran.');
        }

        // Ensure path stays strictly within the complaints/ directory to prevent traversal
        if (!str_starts_with($complaint->attachment_path, 'complaints/')) {
            abort(404, 'Path berkas lampiran tidak sah.');
        }

        if (!Storage::disk('local')->exists($complaint->attachment_path)) {
            abort(404, 'Berkas lampiran tidak ditemukan pada sistem.');
        }

        $extension = pathinfo($complaint->attachment_path, PATHINFO_EXTENSION);
        $safeFilename = 'lampiran-pengaduan-' . $complaint->id . ($extension ? '.' . $extension : '');

        return Storage::disk('local')->download($complaint->attachment_path, $safeFilename);
    }
}
