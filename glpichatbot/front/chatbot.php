<?php

/**
 * Tela dedicada do assistente de chamados.
 *
 * Mostra o assistente ocupando a área de conteúdo do GLPI, no autoatendimento
 * (menu "Assistente de chamados") e na interface padrão (Assistência ›
 * Assistente de chamados). O conteúdo é montado pelo public/js/chatbot.js.
 *
 * This file is part of GLPI Chatbot (GPLv3+).
 */

include ('../../../inc/includes.php');

Session::checkLoginUser();

$title = PluginGlpichatbotMenu::getTypeName();

if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpHeader($title);
} else {
    Html::header($title, '', 'helpdesk', 'PluginGlpichatbotMenu');
}

if (!Session::haveRight('ticket', CREATE)) {
    echo '<div class="alert alert-warning m-3">Você não tem permissão para abrir chamados.</div>';
} else {
    echo '<div id="glpichatbot-page" class="glpichatbot-page-wrapper">'
        . '<noscript>Ative o JavaScript para usar o assistente de chamados.</noscript>'
        . '</div>';
}

if (Session::getCurrentInterface() === 'helpdesk') {
    Html::helpFooter();
} else {
    Html::footer();
}
