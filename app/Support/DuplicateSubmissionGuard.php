<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

/**
 * Backend duplicate-submission safeguard for transactional stores (RULE 7).
 *
 * The frontend (button disabling) is P4's responsibility; this guard is the
 * server-side idempotency check that prevents a double-clicked form from
 * writing the same transaction twice. A successful submission is fingerprinted
 * (scope + user + validated payload) and remembered in the session for a short
 * window; an identical submission inside that window is rejected.
 *
 * Callers must {@see release()} the fingerprint if the surrounding transaction
 * fails, so a legitimate retry after a real validation error is never blocked.
 */
class DuplicateSubmissionGuard
{
    /**
     * How long (seconds) a successful fingerprint blocks an identical retry.
     */
    public const WINDOW_SECONDS = 5;

    /**
     * Remember the submission and reject an identical one inside the window.
     *
     * @param  array<string, mixed>  $payload
     * @return string The fingerprint, to be released on failure.
     *
     * @throws ValidationException
     */
    public static function assertFresh(string $scope, array $payload, string $errorBag = 'default'): string
    {
        $fingerprint = self::fingerprint($scope, $payload);
        $key = self::sessionKey($scope);
        $seen = self::remembered($key);

        if (array_key_exists($fingerprint, $seen)) {
            throw ValidationException::withMessages([
                'submission' => 'This transaction was already submitted. Please wait a moment before trying again.',
            ])->errorBag($errorBag);
        }

        $seen[$fingerprint] = now()->getTimestamp();
        Session::put($key, $seen);

        return $fingerprint;
    }

    /**
     * Forget a fingerprint whose transaction failed, so it can be retried.
     */
    public static function release(string $scope, string $fingerprint): void
    {
        $key = self::sessionKey($scope);
        $seen = self::remembered($key);

        unset($seen[$fingerprint]);

        Session::put($key, $seen);
    }

    /**
     * Pruned list of remembered fingerprints for a scope.
     *
     * @return array<string, int>
     */
    private static function remembered(string $key): array
    {
        $now = now()->getTimestamp();

        return array_filter(
            (array) Session::get($key, []),
            fn ($timestamp) => ($now - (int) $timestamp) <= self::WINDOW_SECONDS,
        );
    }

    private static function fingerprint(string $scope, array $payload): string
    {
        return hash('sha256', $scope.'|'.(Auth::id() ?? 'guest').'|'.json_encode(self::normalize($payload)));
    }

    /**
     * Stable payload ordering so an equivalent double-click has the same hash.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function normalize(array $payload): array
    {
        ksort($payload);

        return $payload;
    }

    private static function sessionKey(string $scope): string
    {
        return 'submission_guard.'.$scope;
    }
}
