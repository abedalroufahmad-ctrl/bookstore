<?php

namespace App\Support;

/**
 * Translates API user-facing English messages when the app locale is Arabic.
 */
class MessageLocalizer
{
    public static function localize(?string $message): string
    {
        if ($message === null || $message === '') {
            return '';
        }

        if (app()->getLocale() !== 'ar') {
            return $message;
        }

        $exact = trans('messages.exact');
        if (is_array($exact)) {
            $candidates = array_unique([
                $message,
                rtrim($message, '.'),
                rtrim($message, '.').'.',
            ]);
            foreach ($candidates as $key) {
                if (isset($exact[$key])) {
                    return $exact[$key];
                }
            }
        }

        $patterns = trans('messages.patterns');
        if (is_array($patterns)) {
            foreach ($patterns as $pattern => $replacement) {
                $translated = preg_replace($pattern, $replacement, $message);
                if (is_string($translated) && $translated !== $message) {
                    return $translated;
                }
            }
        }

        return $message;
    }
}
