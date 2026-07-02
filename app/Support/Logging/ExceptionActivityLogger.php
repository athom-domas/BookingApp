<?php

namespace App\Support\Logging;

use App\Models\ActivityLog;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExceptionActivityLogger
{
    public function report(Throwable $e): void
    {
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return;
        }

        try {
            $businessId = app()->bound('current_business_id') ? app('current_business_id') : null;
            $request    = request();

            activity()
                ->tap(function (ActivityLog $activity) use ($businessId, $request, $e) {
                    $activity->business_id = $businessId;
                    $activity->type        = 'error';
                    $activity->level       = $e instanceof \Error ? 'critical' : 'error';
                    $activity->source      = 'exception_reporter';
                    $activity->ip_address  = $request->ip();
                    $activity->user_agent  = substr((string) ($request->userAgent() ?? ''), 0, 500);
                    $activity->url         = substr($request->fullUrl(), 0, 2000);
                    $activity->method      = $request->method();
                })
                ->causedBy(auth()->user())
                ->withProperties([
                    'exception'     => class_basename($e),
                    'file'          => $e->getFile(),
                    'line'          => $e->getLine(),
                    'trace'         => collect(explode("\n", $e->getTraceAsString()))->take(10)->join("\n"),
                    'request_input' => $this->sanitizeContext($request->input()),
                ])
                ->log($e->getMessage() ?: get_class($e));
        } catch (Throwable) {
            // Never crash the app due to a logger error
        }
    }

    private function sanitizeContext(array $data): array
    {
        $blocked = [
            'password',
            'password_confirmation',
            '_token',
            'token',
            'authorization',
            'cookie',
            'stripe_response',
            'card',
            'card_number',
            'cvv',
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeContext($value);
            }
        }

        return $data;
    }
}
