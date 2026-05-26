<?php
// includes/firebase.php — lightweight Firebase Realtime Database REST sync
// Called after MySQL writes; silently fails so the site never breaks if Firebase is down.

class Firebase {
    private static string $baseUrl = 'https://expedia-ph-default-rtdb.asia-southeast1.firebasedatabase.app';

    /**
     * Write (PUT) a single record to Firebase at /{collection}/{id}.json
     * Overwrites that node entirely — safe for inserts and updates.
     */
    public static function set(string $collection, int|string $id, array $data): void {
        self::request('PUT', "/{$collection}/{$id}.json", $data);
    }

    /**
     * Patch (PATCH) only the supplied fields on /{collection}/{id}.json
     * Use this for partial updates (e.g. status change on a booking).
     */
    public static function patch(string $collection, int|string $id, array $data): void {
        self::request('PATCH', "/{$collection}/{$id}.json", $data);
    }

    /**
     * Delete a node at /{collection}/{id}.json
     */
    public static function delete(string $collection, int|string $id): void {
        self::request('DELETE', "/{$collection}/{$id}.json", null);
    }

    // ── internal HTTP helper ──────────────────────────────────────────
    private static function request(string $method, string $path, ?array $body): void {
        try {
            $url  = self::$baseUrl . $path;
            $json = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

            $opts = [
                'http' => [
                    'method'        => $method,
                    'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($json),
                    'content'       => $json,
                    'timeout'       => 5,          // 5 s max — never blocks the user
                    'ignore_errors' => true,
                ],
            ];

            // Use cURL if available (faster), fall back to file_get_contents
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_CUSTOMREQUEST  => $method,
                    CURLOPT_POSTFIELDS     => $json,
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 5,
                ]);
                curl_exec($ch);
                curl_close($ch);
            } else {
                @file_get_contents($url, false, stream_context_create($opts));
            }
        } catch (Throwable $e) {
            // Silently swallow — Firebase being down must never break the PHP site
            error_log('[Firebase sync error] ' . $e->getMessage());
        }
    }

    // ── Convenience builders ──────────────────────────────────────────

    /** Sync a full user row (strip password hash for safety) */
    public static function syncUser(array $row): void {
        $safe = $row;
        unset($safe['password']);                          // never send password hash to Firebase
        self::set('users', $row['id'], $safe);
    }

    /** Sync a full booking row */
    public static function syncBooking(array $row): void {
        self::set('bookings', $row['id'], $row);
    }

    /** Sync a support ticket row */
    public static function syncTicket(array $row): void {
        self::set('support_tickets', $row['id'], $row);
    }

    /** Sync a hotel application row */
    public static function syncApplication(array $row): void {
        self::set('hotel_applications', $row['id'], $row);
    }
}
