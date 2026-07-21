<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Item;
use App\Models\Release;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_items' => Item::where('is_active', true)->count(),
            'on_hand_units' => (float) Item::sum('on_hand_qty'),
            'releases_this_month' => Release::whereBetween('released_at', [
                Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(),
            ])->count(),
            'pending_payments' => Delivery::where('is_paid', false)->count(),
        ];

        $lowStock = Item::with('unit')
            ->where('is_active', true)
            ->orderBy('on_hand_qty')
            ->limit(6)
            ->get();

        $recentReleases = Release::with('location')
            ->latest('released_at')
            ->limit(6)
            ->get();

        return view('dashboard', compact('stats', 'lowStock', 'recentReleases'));
    }
}
