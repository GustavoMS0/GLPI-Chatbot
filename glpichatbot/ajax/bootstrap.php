<?php

/**
 * GET ajax/bootstrap.php
 *
 * Dados iniciais do assistente: nome do usuário, se ele pode abrir chamados
 * e as entidades (matriz/filiais) disponíveis para ele, além das
 * urgências permitidas e das opções do plugin.
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

$lang   = (string) ($_SESSION['glpilanguage'] ?? 'pt_BR');
$locale = str_replace('_', '-', $lang);
$is_en  = str_starts_with(strtolower($lang), 'en');

$urgencies = plugin_glpichatbot_urgencies();
$urgency_names = [];
foreach ($urgencies as $u) {
    $urgency_names[$u] = Ticket::getUrgencyName($u);
}

plugin_glpichatbot_json([
    'user'           => (string) ($_SESSION['glpifirstname'] ?? '') !== '' ? $_SESSION['glpifirstname'] : $_SESSION['glpiname'],
    'can_create'     => Session::haveRight('ticket', CREATE),
    'interface'      => Session::getCurrentInterface(),
    'entities'       => $entities,
    'default_entity' => (int) $_SESSION['glpiactive_entity'],
    'ticket_url'     => $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=',
    'urgencies'      => $urgencies,
    'urgency_names'  => $urgency_names,
    'type_labels'    => [
        Ticket::INCIDENT_TYPE => Ticket::getTicketTypeName(Ticket::INCIDENT_TYPE),
        Ticket::DEMAND_TYPE   => Ticket::getTicketTypeName(Ticket::DEMAND_TYPE),
    ],
    'locale'         => $locale,
    'is_en'          => $is_en,
    'i18n'           => plugin_glpichatbot_i18n_strings($lang),
    'force_redirect' => plugin_glpichatbot_config()['force_selfservice_redirect'],
]);
