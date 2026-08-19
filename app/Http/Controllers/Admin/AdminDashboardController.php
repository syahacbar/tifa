<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\PublicDocument;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $activeDocumentsCount = PublicDocument::active()->count();
        $newComplaintsCount = Complaint::where('status', Complaint::STATUS_BARU)->count();

        return view('admin.dashboard', [
            'user' => $request->user(),
            'activeDocumentsCount' => $activeDocumentsCount,
            'newComplaintsCount' => $newComplaintsCount,
        ]);
    }
}
