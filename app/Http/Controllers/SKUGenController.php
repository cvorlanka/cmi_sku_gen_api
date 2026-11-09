<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SKUGenController extends Controller
{
    /**
     * POST /skugen
     *
     * Body: JSON array of request objects:
     * [
     *   {
     *     "collection": "...", "model": "...", "color": "...", "size": "...",
     *     "release_date": "...", "mode": "...", "debug_flag": "...", "source": "..."
     *   },
     *   ...
     * ]
     */
    public function skuGen(Request $request): JsonResponse
    {
        $items = $request->json()->all();

        // Each field must be a plain value (string/number/bool) or null.
        $scalar = function (string $attribute, mixed $value, \Closure $fail) {
            if (! is_scalar($value)) {
                $fail("The {$attribute} field must be a string or number.");
            }
        };

        validator(['items' => $items], [
            'items' => ['required', 'array', 'list'],
            'items.*' => ['required', 'array'],
            'items.*.collection' => ['nullable', $scalar],
            'items.*.model' => ['nullable', $scalar],
            'items.*.color' => ['nullable', $scalar],
            'items.*.size' => ['nullable', $scalar],
            'items.*.release_date' => ['nullable', $scalar],
            'items.*.mode' => ['nullable', $scalar],
            'items.*.debug_flag' => ['nullable', $scalar],
            'items.*.source' => ['nullable', $scalar],
        ])->validate();

        // The stored procedure loop can run long; don't let PHP cut it off.
        set_time_limit(config('skugen.lock_seconds'));

        $db = DB::connection(config('skugen.connection'));

        // cvo_tmp_sku_gen is a shared table, so only one SKU generation run may
        // execute at a time; otherwise concurrent callers would truncate each
        // other's results.
        $lock = Cache::lock('skugen-run', config('skugen.lock_seconds'));

        try {
            $lock->block(config('skugen.lock_wait_seconds'));
        } catch (Throwable) {
            return response()->json([
                'message' => 'Another SKU generation is in progress. Please retry shortly.',
            ], 409);
        }

        try {
            // 1) Truncate temp table
            $db->statement('TRUNCATE TABLE cvo_tmp_sku_gen');

            // 2) + 3) Run the stored procedure for each request object
            foreach ($items as $item) {
                $db->statement(
                    'SET NOCOUNT ON; EXEC cvo_cmi_sku_generate_2026_sp ?, ?, ?, ?, ?, ?, ?, ?',
                    [
                        $item['collection'] ?? null,
                        $item['model'] ?? null,
                        $item['color'] ?? null,
                        $item['size'] ?? null,
                        $item['release_date'] ?? null,
                        $item['mode'] ?? null,
                        $item['debug_flag'] ?? null,
                        $item['source'] ?? null,
                    ]
                );
            }

            // 4) Return generated rows
            $rows = $db->select(
                "SELECT * FROM cvo_tmp_sku_gen WHERE collection <> 'coll' ORDER BY severity, model, part_no"
            );

            return response()->json($rows);
        } catch (Throwable $e) {
            Log::error('SKU generation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'SKU generation failed.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        } finally {
            $lock->release();
        }
    }
}
