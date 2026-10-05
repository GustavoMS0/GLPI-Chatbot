<?php

/**
 * ------------------------------------------------------------------------
 * GLPI Chatbot - item de menu "Assistente de chamados"
 * ------------------------------------------------------------------------
 * This file is part of GLPI Chatbot (GPLv3+).
 * ------------------------------------------------------------------------
 */

namespace GlpiPlugin\Glpichatbot;

use CommonGLPI;
use Session;

class Chatbot extends CommonGLPI
{
    public static $rightname = 'ticket';

    public static function getTypeName($nb = 0): string
    {
        return 'Assistente de chamados';
    }

    public static function getMenuName(): string
    {
        return self::getTypeName();
    }

    public static function getIcon(): string
    {
        return 'ti ti-message-chatbot';
    }

    public static function canView(): bool
    {
        return (bool) Session::haveRight('ticket', CREATE);
    }

    /** Endereço da tela dedicada (relativo à raiz do GLPI). */
    public static function getPageUrl(): string
    {
        return '/plugins/glpichatbot/front/chatbot.php';
    }

    public static function getMenuContent()
    {
        if (!static::canView()) {
            return false;
        }
        return [
            'title' => self::getMenuName(),
            'page'  => self::getPageUrl(),
            'icon'  => self::getIcon(),
        ];
    }
}

