<?php

/**
 * ------------------------------------------------------------------------
 * GLPI Chatbot
 *
 * Assistente guiado (sem IA) para abertura de chamados no GLPI.
 *
 * Copyright (C) 2026 by G. Martins
 * ------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI Chatbot.
 *
 * GLPI Chatbot is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * GLPI Chatbot is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI Chatbot. If not, see <http://www.gnu.org/licenses/>.
 * ------------------------------------------------------------------------
 */

define('PLUGIN_GLPICHATBOT_VERSION', '1.1.0');
define('PLUGIN_GLPICHATBOT_MIN_GLPI', '11.0.0');
define('PLUGIN_GLPICHATBOT_MAX_GLPI', '11.0.99');

function plugin_init_glpichatbot()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpichatbot'] = true;

    // Redireciona a tela inicial do solicitante (autoatendimento) para o chatbot
    $PLUGIN_HOOKS['post_init']['glpichatbot'] = 'plugin_glpichatbot_post_init';

    // Carregado em todas as telas; o próprio script só mostra o assistente
    // para usuários logados que podem abrir chamados.
    $PLUGIN_HOOKS['add_javascript']['glpichatbot'] = ['js/chatbot.js'];
    $PLUGIN_HOOKS['add_css']['glpichatbot']        = ['css/chatbot.css'];

    // Tela dedicada: Assistência › Assistente de chamados (interface padrão)
    $PLUGIN_HOOKS['menu_toadd']['glpichatbot'] = ['helpdesk' => 'PluginGlpichatbotMenu'];

    // Tela dedicada no menu do autoatendimento
    $PLUGIN_HOOKS['helpdesk_menu_entry']['glpichatbot']      = '/front/chatbot.php';
    $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['glpichatbot'] = 'ti ti-message-chatbot';

    // Se a sessão já existia sem a entrada de menu do chatbot, força o GLPI a recarregar o menu
    if (isset($_SESSION['glpimenu']) && !isset($_SESSION['glpimenu']['helpdesk']['content']['pluginglpichatbotmenu'])) {
        unset($_SESSION['glpimenu']);
    }
}

function plugin_version_glpichatbot()
{
    return [
        'name'         => 'GLPI Chatbot',
        'version'      => PLUGIN_GLPICHATBOT_VERSION,
        'author'       => 'G. Martins',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/GustavoMS0/GLPI-Chatbot',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_GLPICHATBOT_MIN_GLPI,
                'max' => PLUGIN_GLPICHATBOT_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_glpichatbot_check_prerequisites()
{
    return true;
}

function plugin_glpichatbot_check_config($verbose = false)
{
    return true;
}

// O plugin não cria tabelas nem configurações.
function plugin_glpichatbot_install()
{
    return true;
}

function plugin_glpichatbot_uninstall()
{
    return true;
}

/**
 * Hook disparado após a inicialização do GLPI.
 * Redireciona usuários da interface de autoatendimento (solicitantes)
 * que acessam a página inicial para a tela do chatbot.
 */
function plugin_glpichatbot_post_init()
{
    // Apenas para a interface simplificada (solicitante) e se puder criar chamados
    if (Session::getCurrentInterface() === 'helpdesk' && Session::haveRight('ticket', CREATE)) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?: '';
        
        $base = defined('GLPI_ROOT') ? rtrim(parse_url(Toolbox::getSiteUrl(), PHP_URL_PATH), '/') : '';
        if ($base !== '') {
            $path = preg_replace('#^' . preg_quote($base, '#') . '#', '', $path);
        }

        // Se acessar a raiz ou as páginas centrais sem parâmetros (como ?id=)
        if (
            preg_match('#^(/|/index\.php|/front/central\.php|/front/helpdesk\.php|/Helpdesk|/front/helpdesk\.public\.php)$#i', $path)
            && empty($_GET)
        ) {
            Html::redirect($base . '/plugins/glpichatbot/front/chatbot.php');
        }
    }
}
