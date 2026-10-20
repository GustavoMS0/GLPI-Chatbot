<?php

/**
 * GET ajax/categories.php?entity=<id>&type=<1|2>
 *
 * Árvore de categorias que o usuário pode escolher para a entidade e o tipo
 * de chamado informados (mesmas regras do campo nativo do GLPI).
 *
 * This file is part of GLPI Chatbot (GPLv3+).
 */

require_once __DIR__ . '/../inc/functions.php';

Session::checkLoginUser();

if (!Session::haveRight('ticket', CREATE) && !Session::haveRight('ticket', READ)) {
    plugin_glpichatbot_json(['error' => 'Acesso negado.'], 403);
    return;
}

$entity = plugin_glpichatbot_entity($_GET['entity'] ?? '');
$type   = (int) ($_GET['type'] ?? 0);

plugin_glpichatbot_json(array_values(plugin_glpichatbot_categories($entity, $type)));
