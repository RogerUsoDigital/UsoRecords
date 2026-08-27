<?php

namespace App\Validators;

final class SourceUrlValidator
{
    /** @return array{valid: bool, message: ?string} */
    public function validate(?string $url): array
    {
        if ($url === null || trim($url) === '') {
            return ['valid' => false, 'message' => 'A URL do áudio é obrigatória.'];
        }

        $parts = parse_url(trim($url));
        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host']) || isset($parts['user'], $parts['pass'])
            || isset($parts['fragment'])) {
            return ['valid' => false, 'message' => 'A URL informada é inválida.'];
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return ['valid' => false, 'message' => 'O destino informado não é permitido.'];
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : $this->resolveAddresses($host);
        if ($addresses === []) {
            return ['valid' => false, 'message' => 'Não foi possível validar o destino informado.'];
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return ['valid' => false, 'message' => 'O destino informado não é permitido.'];
            }
        }

        return ['valid' => true, 'message' => null];
    }

    /** @return list<string> */
    private function resolveAddresses(string $host): array
    {
        $ips = [];

        // Tenta resolver nativamente pelo sistema operacional 
        $resolved = gethostbynamel($host);
        
        if (is_array($resolved)) {
            $ips = array_merge($ips, $resolved);
        }

        // Como fallback, tenta usar o DNS do PHP para registros A (IPv4) e AAAA (IPv6)
        if (empty($ips)) {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip'])) {
                        $ips[] = $record['ip'];
                    } elseif (isset($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
