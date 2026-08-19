<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminPublicDocumentController extends Controller
{
    public function index(): View
    {
        $documents = PublicDocument::orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.documents.index', compact('documents'));
    }

    public function create(): View
    {
        return view('admin.documents.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'published_at' => ['required', 'date'],
            'pdf_file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'thumbnail_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Judul dokumen wajib diisi.',
            'published_at.required' => 'Tanggal publikasi wajib diisi.',
            'pdf_file.required' => 'File PDF dokumen wajib diupload.',
            'pdf_file.mimes' => 'Format file harus berupa PDF.',
            'pdf_file.max' => 'Ukuran file PDF maksimal 20 MB.',
            'thumbnail_file.mimes' => 'Format thumbnail harus berupa JPG, PNG, atau WebP.',
            'thumbnail_file.max' => 'Ukuran thumbnail maksimal 2 MB.',
        ]);

        $pdfPath = $request->file('pdf_file')->store('documents', 'public');

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail_file') && $request->file('thumbnail_file')->isValid()) {
            $thumbnailPath = $request->file('thumbnail_file')->store('document-thumbnails', 'public');
        }

        PublicDocument::create([
            'title' => (string) $validated['title'],
            'published_at' => $validated['published_at'],
            'file_path' => $pdfPath,
            'thumbnail_path' => $thumbnailPath,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Dokumen berhasil ditambahkan.');
    }

    public function edit(PublicDocument $publicDocument): View
    {
        return view('admin.documents.edit', [
            'document' => $publicDocument,
        ]);
    }

    public function update(Request $request, PublicDocument $publicDocument): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'published_at' => ['required', 'date'],
            'pdf_file' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'thumbnail_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_thumbnail' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'title.required' => 'Judul dokumen wajib diisi.',
            'published_at.required' => 'Tanggal publikasi wajib diisi.',
            'pdf_file.mimes' => 'Format file harus berupa PDF.',
            'pdf_file.max' => 'Ukuran file PDF maksimal 20 MB.',
            'thumbnail_file.mimes' => 'Format thumbnail harus berupa JPG, PNG, atau WebP.',
            'thumbnail_file.max' => 'Ukuran thumbnail maksimal 2 MB.',
        ]);

        // Replacement PDF
        if ($request->hasFile('pdf_file') && $request->file('pdf_file')->isValid()) {
            $newPdfPath = $request->file('pdf_file')->store('documents', 'public');
            $oldPdfPath = $publicDocument->file_path;

            $publicDocument->file_path = $newPdfPath;

            // Delete old file if present in storage/public
            if (!empty($oldPdfPath) && Storage::disk('public')->exists($oldPdfPath)) {
                Storage::disk('public')->delete($oldPdfPath);
            }
        }

        // Thumbnail replacement or removal
        if ($request->boolean('remove_thumbnail') && !$request->hasFile('thumbnail_file')) {
            if (!empty($publicDocument->thumbnail_path) && Storage::disk('public')->exists($publicDocument->thumbnail_path)) {
                Storage::disk('public')->delete($publicDocument->thumbnail_path);
            }
            $publicDocument->thumbnail_path = null;
        } elseif ($request->hasFile('thumbnail_file') && $request->file('thumbnail_file')->isValid()) {
            $newThumbPath = $request->file('thumbnail_file')->store('document-thumbnails', 'public');
            $oldThumbPath = $publicDocument->thumbnail_path;

            $publicDocument->thumbnail_path = $newThumbPath;

            if (!empty($oldThumbPath) && Storage::disk('public')->exists($oldThumbPath)) {
                Storage::disk('public')->delete($oldThumbPath);
            }
        }

        $publicDocument->title = (string) $validated['title'];
        $publicDocument->published_at = $validated['published_at'];
        $publicDocument->is_active = $request->boolean('is_active');
        $publicDocument->save();

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Dokumen berhasil diperbarui.');
    }

    public function destroy(PublicDocument $publicDocument): RedirectResponse
    {
        // Safe delete files from disk
        if (!empty($publicDocument->file_path) && Storage::disk('public')->exists($publicDocument->file_path)) {
            Storage::disk('public')->delete($publicDocument->file_path);
        }

        if (!empty($publicDocument->thumbnail_path) && Storage::disk('public')->exists($publicDocument->thumbnail_path)) {
            Storage::disk('public')->delete($publicDocument->thumbnail_path);
        }

        $publicDocument->delete();

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Dokumen berhasil dihapus.');
    }
}
