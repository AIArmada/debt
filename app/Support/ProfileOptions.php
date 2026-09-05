<?php

namespace App\Support;

use DateTimeZone;

final class ProfileOptions
{
    /**
     * @return array<string, string>
     */
    public static function timezones(): array
    {
        $preferred = [
            'Asia/Kuala_Lumpur' => 'Kuala Lumpur (UTC+08:00)',
            'Asia/Singapore' => 'Singapore (UTC+08:00)',
            'Asia/Jakarta' => 'Jakarta (UTC+07:00)',
            'Asia/Bangkok' => 'Bangkok (UTC+07:00)',
            'Asia/Manila' => 'Manila (UTC+08:00)',
            'Asia/Hong_Kong' => 'Hong Kong (UTC+08:00)',
            'Asia/Taipei' => 'Taipei (UTC+08:00)',
            'Asia/Shanghai' => 'Shanghai (UTC+08:00)',
            'Asia/Tokyo' => 'Tokyo (UTC+09:00)',
            'Asia/Seoul' => 'Seoul (UTC+09:00)',
            'Asia/Kolkata' => 'Kolkata (UTC+05:30)',
            'Asia/Dubai' => 'Dubai (UTC+04:00)',
            'Australia/Sydney' => 'Sydney (UTC+10:00)',
            'Pacific/Auckland' => 'Auckland (UTC+12:00)',
            'Europe/London' => 'London (UTC+00:00)',
            'Europe/Paris' => 'Paris (UTC+01:00)',
            'Europe/Berlin' => 'Berlin (UTC+01:00)',
            'Africa/Johannesburg' => 'Johannesburg (UTC+02:00)',
            'America/New_York' => 'New York (UTC−05:00)',
            'America/Chicago' => 'Chicago (UTC−06:00)',
            'America/Denver' => 'Denver (UTC−07:00)',
            'America/Los_Angeles' => 'Los Angeles (UTC−08:00)',
            'America/Toronto' => 'Toronto (UTC−05:00)',
            'UTC' => 'UTC (Coordinated Universal Time)',
        ];

        $options = $preferred;

        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            $options[$timezone] ??= str_replace(['_', '/'], [' ', ' / '], $timezone);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function locales(): array
    {
        return [
            'en-MY' => 'English (Malaysia)',
            'ms-MY' => 'Bahasa Melayu (Malaysia)',
            'en-SG' => 'English (Singapore)',
            'en-US' => 'English (United States)',
            'en-GB' => 'English (United Kingdom)',
            'id-ID' => 'Bahasa Indonesia (Indonesia)',
            'zh-CN' => '简体中文 (China)',
            'zh-TW' => '繁體中文 (Taiwan)',
            'th-TH' => 'ไทย (Thailand)',
            'vi-VN' => 'Tiếng Việt (Vietnam)',
            'ja-JP' => '日本語 (Japan)',
            'ko-KR' => '한국어 (South Korea)',
        ];
    }
}
