<?php

/**
 * Configuração do plugin (Configurar › Plugins › GLPI Chatbot).
 *
 * Opções gravadas na configuração do GLPI (glpi_configs, contexto plugin:glpichatbot).
 * O token CSRF do formulário é validado pelo próprio GLPI 11.
 *
 * This file is part of GLPI Chatbot (GPLv3+).
 */

// GLPI 11 inicializa o ambiente antes de executar os arquivos front/ dos plugins.

require_once __DIR__ . '/../inc/functions.php';

/** @var array $CFG_GLPI */
global $CFG_GLPI;

Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    Config::setConfigurationValues('plugin:glpichatbot', [
        'force_selfservice_redirect' => empty($_POST['force_selfservice_redirect']) ? 0 : 1,
    ]);
    Session::addMessageAfterRedirect('Configuração salva.');
    Html::back();
    return;
}

$config = plugin_glpichatbot_config();
$title  = 'GLPI Chatbot';

Html::header($title, '', 'config', 'plugin');

echo '<div class="card m-3">';
echo '<div class="card-header"><h3 class="card-title">' . htmlescape($title) . '</h3></div>';
echo '<div class="card-body">';
echo '<form method="post" action="' . htmlescape($CFG_GLPI['root_doc'] . '/plugins/glpichatbot/front/config.form.php') . '">';

echo '<div class="form-check form-switch mb-2">';
echo '<input type="hidden" name="force_selfservice_redirect" value="0">';
echo '<input class="form-check-input" type="checkbox" role="switch" id="glpichatbot-force-redirect"'
    . ' name="force_selfservice_redirect" value="1"' . ($config['force_selfservice_redirect'] ? ' checked' : '') . '>';
echo '<label class="form-check-label" for="glpichatbot-force-redirect">'
    . 'Abrir o assistente como página inicial do autoatendimento</label>';
echo '</div>';
echo '<p class="text-muted small mb-3">Quando ligado, a página inicial do perfil de autoatendimento'
    . ' leva direto à tela do assistente. Para ver a página inicial normal mesmo assim, use'
    . ' <code>?no_chatbot=1</code> no endereço.</p>';

echo '<button type="submit" name="update" value="1" class="btn btn-primary">'
    . '<i class="ti ti-device-floppy"></i> <span>Salvar</span></button>';

Html::closeForm();
echo '</div></div>';

Html::footer();
