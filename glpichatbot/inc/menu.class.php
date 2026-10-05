<?php

/**
 * ------------------------------------------------------------------------
 * GLPI Chatbot - Item de menu no GLPI (Assistência > Assistente de chamados)
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Acesso direto não permitido.");
}

class PluginGlpichatbotMenu extends CommonGLPI
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

    public static function getMenuContent()
    {
        if (!static::canView()) {
            return false;
        }
        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/glpichatbot/front/chatbot.php',
            'icon'  => self::getIcon(),
        ];
    }
}

