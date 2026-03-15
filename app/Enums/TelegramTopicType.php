<?php

namespace App\Enums;

enum TelegramTopicType: string
{
    case GENERAL = 'general';
    case CLIENT = 'client';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match($this) {
            self::GENERAL => 'General',
            self::CLIENT => 'Client',
            self::CUSTOM => 'Custom',
        };
    }
}
