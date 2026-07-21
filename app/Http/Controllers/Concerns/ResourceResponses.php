<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResourceResponses
{
    /**
     * JSON for AJAX callers (modal forms / AJAX delete), redirect-back for
     * classic form posts. Validation errors are handled automatically by
     * Laravel (422 JSON when the request wantsJson).
     */
    protected function ok(Request $request, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    protected function fail(Request $request, string $message, int $status = 422)
    {
        if ($request->wantsJson()) {
            return response()->json(['ok' => false, 'message' => $message], $status);
        }

        return back()->with('error', $message);
    }
}
