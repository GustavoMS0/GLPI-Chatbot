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

define('PLUGIN_GLPICHATBOT_VERSION', '1.0.0');
define('PLUGIN_GLPICHATBOT_MIN_GLPI', '11.0.0');
define('PLUGIN_GLPICHATBOT_MAX_GLPI', '11.0.99');

function plugin_init_glpichatbot()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['glpichatbot'] = true;

    // Carregado em todas as telas; o próprio script só mostra o assistente
    // para usuários logados que podem abrir chamados.
    $PLUGIN_HOOKS['add_javascript']['glpichatbot'] = ['js/chatbot.js'];
    $PLUGIN_HOOKS['add_css']['glpichatbot']        = ['css/chatbot.css'];
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
