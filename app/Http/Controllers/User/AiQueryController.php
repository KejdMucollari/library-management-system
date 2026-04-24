<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AiQueryLog;
use App\Services\Ai\AiQueryService;
use Illuminate\Http\Request;

class AiQueryController extends Controller
{
    public function query(Request $request, AiQueryService $service)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        $started = hrtime(true);
        $log = AiQueryLog::create([
            'user_id' => $user->id,
            'question' => $data['question'],
            'success' => false,
        ]);

        try {
            $spec = $service->translateToSpec($data['question'], isAdmin: false);

            // If translation returns a user-facing message (columns/rows empty),
            // skip execution and just render the summary.
            if (
                isset($spec['summary'], $spec['columns'], $spec['rows']) &&
                is_string($spec['summary']) &&
                is_array($spec['columns']) &&
                is_array($spec['rows'])
            ) {
                $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
                $log->update([
                    'query_spec' => $spec,
                    'success' => true,
                    'duration_ms' => $durationMs,
                    'error_message' => null,
                ]);

                return redirect()
                    ->route('books.index')
                    ->with('ai.result', $spec);
            }

            $result = $service->execute($spec, $user, $data['question']);

            $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
            $log->update([
                'query_spec' => $spec,
                'success' => true,
                'duration_ms' => $durationMs,
                'error_message' => null,
            ]);

            return redirect()
                ->route('books.index')
                ->with('ai.result', $result->toArray());
        } catch (\Throwable $e) {
            $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
            $log->update([
                'success' => false,
                'duration_ms' => $durationMs,
                'error_message' => $e->getMessage(),
            ]);

            return back()
                ->withErrors([
                    'question' => $e->getMessage(),
                ])
                ->withInput();
        }
    }
}

