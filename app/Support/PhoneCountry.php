<?php

namespace App\Support;

/**
 * Códigos de país (DDI) suportados nos campos de celular do cadastro e do
 * perfil. O telefone é sempre persistido em E.164: "+<ddi><dígitos>".
 */
class PhoneCountry
{
    /**
     * DDI => rótulo exibido no select.
     *
     * @var array<string, string>
     */
    public const COUNTRIES = [
        '55' => '🇧🇷 Brasil (+55)',
        '351' => '🇵🇹 Portugal (+351)',
        '1' => '🇺🇸 EUA / Canadá (+1)',
        '54' => '🇦🇷 Argentina (+54)',
        '591' => '🇧🇴 Bolívia (+591)',
        '56' => '🇨🇱 Chile (+56)',
        '57' => '🇨🇴 Colômbia (+57)',
        '593' => '🇪🇨 Equador (+593)',
        '595' => '🇵🇾 Paraguai (+595)',
        '51' => '🇵🇪 Peru (+51)',
        '598' => '🇺🇾 Uruguai (+598)',
        '58' => '🇻🇪 Venezuela (+58)',
        '52' => '🇲🇽 México (+52)',
        '34' => '🇪🇸 Espanha (+34)',
        '33' => '🇫🇷 França (+33)',
        '49' => '🇩🇪 Alemanha (+49)',
        '39' => '🇮🇹 Itália (+39)',
        '44' => '🇬🇧 Reino Unido (+44)',
        '41' => '🇨🇭 Suíça (+41)',
        '81' => '🇯🇵 Japão (+81)',
        '86' => '🇨🇳 China (+86)',
        '61' => '🇦🇺 Austrália (+61)',
        '244' => '🇦🇴 Angola (+244)',
        '258' => '🇲🇿 Moçambique (+258)',
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return self::COUNTRIES;
    }

    /** Monta o E.164 a partir do DDI e do número digitado (com ou sem máscara). */
    public static function e164(string $countryCode, string $number): string
    {
        return '+'.$countryCode.preg_replace('/\D/', '', $number);
    }

    /**
     * Divide um E.164 armazenado em [ddi, número nacional]. DDIs mais longos
     * têm prioridade (ex.: +591 antes de +55... não colide, mas +1/+1x sim).
     *
     * @return array{0: string, 1: string}
     */
    public static function split(?string $phone): array
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return ['55', ''];
        }

        $codes = array_keys(self::COUNTRIES);
        usort($codes, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($digits, $code)) {
                return [$code, substr($digits, strlen($code))];
            }
        }

        return ['55', $digits];
    }
}
