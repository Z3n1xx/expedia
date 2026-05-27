<?php
// includes/firebase.php — Firebase Realtime Database REST sync + Auth account creation
// Called after MySQL writes; silently fails so the site never breaks if Firebase is down.

class Firebase {
    private static string $dbUrl   = 'https://expedia-ph-default-rtdb.asia-southeast1.firebasedatabase.app';
    private static string $project = 'expedia-ph';

    // ── Realtime Database: write / patch / delete ─────────────────────

    /** PUT — overwrite a full node at /{collection}/{id} */
    public static function set(string $collection, int|string $id, array $data): void {
        self::dbRequest('PUT', "/{$collection}/{$id}.json", $data);
    }

    /** PATCH — update only supplied fields on an existing node */
    public static function patch(string $collection, int|string $id, array $data): void {
        self::dbRequest('PATCH', "/{$collection}/{$id}.json", $data);
    }

    /** DELETE — remove a node */
    public static function delete(string $collection, int|string $id): void {
        self::dbRequest('DELETE', "/{$collection}/{$id}.json", null);
    }

    // ── Firebase Auth: create / update users ─────────────────────────

    /**
     * Creates a Firebase Auth account so the user can sign in from Android
     * using the same email + password they registered with on the PHP site.
     * Returns the Firebase UID on success, or null on failure.
     */
    public static function createAuthUser(string $email, string $password, string $displayName): ?string {
        try {
            $token = self::getAccessToken();
            if (!$token) return null;

            $url  = "https://identitytoolkit.googleapis.com/v1/projects/{$_}/accounts";
            $url  = "https://identitytoolkit.googleapis.com/v1/projects/" . self::$project . "/accounts";
            $body = json_encode([
                'email'         => $email,
                'password'      => $password,
                'displayName'   => $displayName,
                'emailVerified' => false,
                'disabled'      => false,
            ]);

            $response = self::curlPost($url, $body, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ]);

            $data = json_decode($response, true);
            return $data['localId'] ?? null;   // Firebase UID

        } catch (Throwable $e) {
            error_log('[Firebase Auth createUser error] ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Updates password on an existing Firebase Auth account (by UID).
     * Call this if user changes password on the PHP site.
     */
    public static function updateAuthPassword(string $firebaseUid, string $newPassword): void {
        try {
            $token = self::getAccessToken();
            if (!$token) return;

            $url  = "https://identitytoolkit.googleapis.com/v1/projects/" . self::$project . "/accounts/" . $firebaseUid;
            $body = json_encode(['password' => $newPassword]);

            self::curlPost($url . '?updateMask=password', $body, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'X-HTTP-Method-Override: PATCH',
            ]);
        } catch (Throwable $e) {
            error_log('[Firebase Auth updatePassword error] ' . $e->getMessage());
        }
    }

    // ── Convenience sync helpers ──────────────────────────────────────

    /**
     * Full user sync: writes safe user data to /users/{id} in the database.
     * Optionally also creates a Firebase Auth account so Android can log in.
     * Pass $plainPassword only during registration — never store/log it.
     */
    public static function syncUser(array $row, ?string $plainPassword = null): void {
        $safe = $row;
        unset($safe['password']);   // never send the bcrypt hash to Firebase

        // Add a firebase_uid field if we're creating an Auth account
        if ($plainPassword !== null) {
            $displayName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $uid = self::createAuthUser($row['email'], $plainPassword, $displayName);
            if ($uid) {
                $safe['firebase_uid'] = $uid;
                // Also store the UID back in MySQL so we can reference it later
                try {
                    require_once __DIR__ . '/config.php';
                    // Add firebase_uid column if it doesn't exist yet
                    try { db()->exec("ALTER TABLE users ADD COLUMN firebase_uid VARCHAR(128) DEFAULT NULL"); } catch(Exception $e) {}
                    db()->prepare('UPDATE users SET firebase_uid=? WHERE id=?')->execute([$uid, $row['id']]);
                } catch(Throwable $e) { /* non-fatal */ }
            }
        }

        self::set('users', $row['id'], $safe);
    }

    /** Sync a booking row to /bookings/{id} */
    public static function syncBooking(array $row): void {
        self::set('bookings', $row['id'], $row);
    }

    /** Sync a support ticket to /support_tickets/{id} */
    public static function syncTicket(array $row): void {
        self::set('support_tickets', $row['id'], $row);
    }

    /** Sync a hotel application to /hotel_applications/{id} */
    public static function syncApplication(array $row): void {
        self::set('hotel_applications', $row['id'], $row);
    }

    // ── Internal: Realtime Database HTTP ─────────────────────────────

    private static function dbRequest(string $method, string $path, ?array $body): void {
        try {
            $url  = self::$dbUrl . $path;
            $json = $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

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
                @file_get_contents($url, false, stream_context_create([
                    'http' => [
                        'method'        => $method,
                        'header'        => "Content-Type: application/json\r\n",
                        'content'       => $json,
                        'timeout'       => 5,
                        'ignore_errors' => true,
                    ],
                ]));
            }
        } catch (Throwable $e) {
            error_log('[Firebase DB sync error] ' . $e->getMessage());
        }
    }

    // ── Internal: Google OAuth2 access token (for Admin API calls) ───

    private static ?string $cachedToken    = null;
    private static int     $tokenExpiresAt = 0;

    private static function getAccessToken(): ?string {
        // Return cached token if still valid
        if (self::$cachedToken && time() < self::$tokenExpiresAt - 30) {
            return self::$cachedToken;
        }

        try {
            $saPath = __DIR__ . '/../firebase-service-account.json';
            if (!file_exists($saPath)) return null;
            $sa = json_decode(file_get_contents($saPath), true);

            $now    = time();
            $expiry = $now + 3600;

            // Build JWT header.payload
            $header  = self::b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = self::b64url(json_encode([
                'iss'   => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/firebase',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $expiry,
            ]));

            $sigInput = $header . '.' . $payload;
            $key      = openssl_pkey_get_private($sa['private_key']);
            if (!$key) return null;

            openssl_sign($sigInput, $sig, $key, 'SHA256');
            $jwt = $sigInput . '.' . self::b64url($sig);

            // Exchange JWT for access token
            $response = self::curlPost('https://oauth2.googleapis.com/token',
                http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]),
                ['Content-Type: application/x-www-form-urlencoded']
            );

            $data = json_decode($response, true);
            if (empty($data['access_token'])) return null;

            self::$cachedToken    = $data['access_token'];
            self::$tokenExpiresAt = $now + (int)($data['expires_in'] ?? 3600);
            return self::$cachedToken;

        } catch (Throwable $e) {
            error_log('[Firebase getAccessToken error] ' . $e->getMessage());
            return null;
        }
    }

    private static function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function curlPost(string $url, string $body, array $headers): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result ?: '';
    }
}
