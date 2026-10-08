<?php

namespace Nsd7\AiMeter\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class Authorize
{
    public function handle(Request $request, Closure $next)
    {
        $gate = config('ai-meter.gate');

        if ($gate && Gate::has($gate)) {
            abort_unless(Gate::forUser($request->user())->check($gate), 403);
        }

        return $next($request);
    }
}
