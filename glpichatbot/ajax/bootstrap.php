<?php

/**
 * GET ajax/bootstrap.php
 *
 * Dados iniciais do assistente: nome do usuário, se ele pode abrir chamados
 * e as entidades (matriz/filiais) disponíveis para ele.
 *
 * This file is part of GLPI Chatbot (GPLv3+).
 */

require_once __DIR__ . '/../inc/functions.php';

Session::checkLoginUser();

/** @var array $CFG_GLPI */
global $CFG_GLPI;

$entities = [];
foreach (array_slice($_SESSION['glpiactiveentities'] ?? [], 0, 300) as $id) {
    $entities[] = [
        'id'   => (int) $id,
        'name' => (string) Dropdown::getDropdownName('glpi_entities', (int) $id),
    ];
}
usort($entities, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));

plugin_glpichatbot_json([
    'user'           => (string) ($_SESSION['glpifirstname'] ?? '') !== '' ? $_SESSION['glpifirstname'] : $_SESSION['glpiname'],
    'can_create'     => Session::haveRight('ticket', CREATE),
    'entities'       => $entities,
    'default_entity' => (int) $_SESSION['glpiactive_entity'],
    'ticket_url'     => $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=',
]);
