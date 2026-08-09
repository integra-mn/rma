<?php
defined('RMS') or die('Direct access not permitted');

/**
 * WhatsApp delivery via Meta's WhatsApp Cloud API.
 *
 * Required settings:
 *   - whatsapp_provider       : "meta" (only one supported today)
 *   - whatsapp_meta_phone_id  : numeric phone-number ID from Meta Business
 *   - whatsapp_meta_token     : long-lived system user access token
 *   - whatsapp_meta_template  : approved template name (e.g. "otp_verification")
 *   - whatsapp_meta_lang      : template language code (default "en")
 *   - whatsapp_meta_version   : optional Graph API version (default "v20.0")
 *
 * The template must be pre-approved by Meta and must contain one body
 * placeholder that receives the OTP code. If the template is an
 * "authentication" category template with a copy-code button, Meta also
 * requires the code to be passed as a button URL parameter — this helper
 * sends both.
 */
function whatsapp_send_otp(string $to, string $code): bool {
    if (!(bool) setting('whatsapp_enabled', 0)) return false;

    $provider = setting('whatsapp_provider', 'meta');
    if ($provider !== 'meta') return false;

    $phone_id = setting('whatsapp_meta_phone_id', '');
    $token    = setting('whatsapp_meta_token', '');
    $template = setting('whatsapp_meta_template', 'otp_verification');
    $lang     = setting('whatsapp_meta_lang', 'en');
    $version  = setting('whatsapp_meta_version', 'v20.0');

    if ($phone_id === '' || $token === '' || $template === '') return false;

    // E.164 without leading "+"
    $to = preg_replace('/[^\d]/', '', $to);
    if ($to === '') return false;

    $body = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'     => $template,
            'language' => ['code' => $lang],
            'components' => [
                [
                    'type'       => 'body',
                    'parameters' => [['type' => 'text', 'text' => $code]],
                ],
                [
                    'type'     => 'button',
                    'sub_type' => 'url',
                    'index'    => '0',
                    'parameters' => [['type' => 'text', 'text' => $code]],
                ],
            ],
        ],
    ];

    $url = "https://graph.facebook.com/{$version}/{$phone_id}/messages";
    [$ok, $resp] = http_post($url, json_encode($body), [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    if (!$ok) {
        error_log('WhatsApp Meta API failed: ' . $resp);
        return false;
    }
    return true;
}
