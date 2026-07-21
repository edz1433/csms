<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SidebarComposer
{
    /**
     * Share the authenticated user's access array + role with the layout
     * so the sidebar partial can conditionally render nav links.
     */
    public function compose(View $view): void
    {
        $user = Auth::user();

        $view->with([
            'access' => $user?->access ?? [],
            'role' => $user?->role,
        ]);
    }
}
