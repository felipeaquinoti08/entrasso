<?php

namespace GlpiPlugin\Entrasso;

use CronTask;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * License check-in against the company's licensing panel (Laravel app,
 * see /api/licenses/validate there). One "installation" (this GLPI
 * instance) counts against the license's contracted quantity for as long
 * as it keeps checking in - instance_id is what identifies it across
 * restarts/redeploys, so it's generated once and persisted forever.
 *
 * Called from two places, both going through checkIn():
 *  - the CronTask (cronCheckIn), every 10 minutes in the background;
 *  - front/redirect.php, synchronously on every login attempt, so a
 *    license change (cancelled, limit lowered...) takes effect on the
 *    very next login instead of waiting for the next cron cycle.
 *
 * Reachability failures (network blip, panel down) never disable the
 * plugin - only a definitive rejection from the panel does (invalid key,
 * expired, suspended, cancelled, or the installation quantity exceeded).
 * A later successful check re-enables it automatically. See also
 * Config::isLicenseFresh(), which independently disables the plugin if
 * no check-in (cron or login-triggered) succeeds for 30 minutes - so a
 * broken cron can't leave a cancelled license active forever.
 */
class LicenseCheck
{
    private const DEFINITIVE_REASONS = ['invalid_key', 'expired', 'suspended', 'cancelled', 'limit_exceeded'];

    public static function cronCheckIn(CronTask $task): int
    {
        $valid = self::checkIn(15, [$task, 'log']);
        $task->setVolume($valid ? 1 : 0);
        return $valid ? 1 : 0;
    }

    /**
     * @param int $timeoutSeconds Kept short for the login-time call site
     *                            so an unreachable panel doesn't stall
     *                            the login page.
     * @param ?callable $logger   fn(string $message): void
     */
    public static function checkIn(int $timeoutSeconds = 15, ?callable $logger = null): bool
    {
        global $CFG_GLPI;

        $log = $logger ?? function (string $message): void {
        };

        $config = Config::get();

        if ($config['license_api_url'] === '' || $config['license_key'] === '') {
            $log('Licenciamento não configurado, pulando verificação.');
            return false;
        }

        $instanceId = $config['license_instance_id'];
        if ($instanceId === '') {
            $instanceId = bin2hex(random_bytes(16));
            Config::set(['license_instance_id' => $instanceId]);
        }

        $baseUrl = rtrim($config['license_api_url'], '/');
        // http_errors=false: the API legitimately responds 403/404 with a
        // meaningful {valid:false, reason:...} body for a rejected license
        // (see LicenseValidationController) - Guzzle's default of throwing
        // on any non-2xx would otherwise swallow that body and report a
        // generic 'error' instead of the real reason.
        $client = new Client(['timeout' => $timeoutSeconds, 'http_errors' => false]);

        try {
            $response = $client->post($baseUrl . '/api/licenses/validate', [
                'json' => [
                    'license_key' => $config['license_key'],
                    'instance_identifier' => $instanceId,
                    'instance_url' => rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/'),
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true) ?? [];
            $valid = (bool) ($body['valid'] ?? false);

            self::recordResult($valid ? 'ok' : ($body['reason'] ?? 'unknown'));

            if ($valid) {
                Config::set(['license_last_success_at' => date('Y-m-d H:i:s')]);
                if (!$config['is_active']) {
                    Config::set(['is_active' => 1]);
                    $log('Licença válida novamente - login Microsoft reativado.');
                }
                return true;
            }

            $reason = $body['reason'] ?? 'unknown';
            if (in_array($reason, self::DEFINITIVE_REASONS, true)) {
                Config::set(['is_active' => 0]);
                $log("Licença recusada pelo painel ({$reason}) - login Microsoft desativado.");
            } else {
                $log("Resposta inesperada do painel de licenciamento: {$reason}");
            }
            return false;
        } catch (ConnectException $e) {
            // Panel unreachable - fail open, don't touch is_active.
            self::recordResult('unreachable');
            $log('Não foi possível contatar o painel de licenciamento: ' . $e->getMessage());
            return false;
        } catch (RequestException $e) {
            // Got an HTTP response but something else went wrong (5xx,
            // malformed body...) - also fail open.
            self::recordResult('error');
            $log('Erro ao validar a licença: ' . $e->getMessage());
            return false;
        } catch (GuzzleException $e) {
            self::recordResult('error');
            $log('Erro ao validar a licença: ' . $e->getMessage());
            return false;
        }
    }

    private static function recordResult(string $status): void
    {
        Config::set([
            'license_last_status' => $status,
            'license_last_checked_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
