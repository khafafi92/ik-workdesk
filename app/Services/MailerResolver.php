<?php

namespace App\Services;

use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * Menentukan mailer dan FROM address yang tepat berdasarkan domain email penerima.
 *
 * Mapping domain → mailer (dikonfigurasi di config/mail.php dan .env):
 *   @kpmog.com  → mailer 'kpmog'  → from noreply@kpmog.com
 *   @apca.com   → mailer 'apca'   → from noreply@apca.com
 *   lainnya     → mailer default  → from MAIL_FROM_ADDRESS
 */
class MailerResolver
{
    /**
     * Mapping domain email ke nama mailer di config/mail.php.
     */
    private const DOMAIN_MAP = [
        'kpmog.com' => 'kpmog',
        'apca.com'  => 'apca',
    ];

    /**
     * FROM address per mailer.
     * Diambil dari .env agar mudah diganti tanpa ubah kode.
     */
    private const FROM_MAP = [
        'kpmog' => [
            'address' => 'MAIL_KPMOG_FROM_ADDRESS',
            'name'    => 'MAIL_KPMOG_FROM_NAME',
        ],
        'apca'  => [
            'address' => 'MAIL_APCA_FROM_ADDRESS',
            'name'    => 'MAIL_APCA_FROM_NAME',
        ],
    ];

    /**
     * Deteksi domain email penerima, kembalikan mailer yang sesuai.
     * Jika domain tidak dikenal, gunakan mailer default Laravel.
     *
     * @param  string|null  $recipientEmail  Email penerima, misal user@kpmog.com
     */
    public static function forEmail(?string $recipientEmail): Mailer
    {
        $mailerName = self::resolveMailerName($recipientEmail);

        return Mail::mailer($mailerName);
    }

    /**
     * Resolusi nama mailer berdasarkan domain email.
     */
    public static function resolveMailerName(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return config('mail.default', 'log');
        }

        $domain = strtolower(substr($email, strpos($email, '@') + 1));

        return self::DOMAIN_MAP[$domain] ?? config('mail.default', 'log');
    }

    /**
     * Ambil FROM address yang sesuai untuk mailer tertentu.
     */
    public static function fromAddress(string $mailerName): array
    {
        if (! isset(self::FROM_MAP[$mailerName])) {
            return [
                'address' => config('mail.from.address'),
                'name'    => config('mail.from.name'),
            ];
        }

        $map = self::FROM_MAP[$mailerName];

        return [
            'address' => env($map['address'], config('mail.from.address')),
            'name'    => env($map['name'], config('mail.from.name')),
        ];
    }
}
