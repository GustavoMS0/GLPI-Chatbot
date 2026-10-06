<?php

/**
 * POST ajax/create.php  (application/x-www-form-urlencoded + cabeçalho X-Glpi-Csrf-Token)
 *
 * Campos: type, entity, category, title, description, urgency
 *
 * Abre o chamado em nome do usuário logado. Por usar a criação padrão do GLPI,
 * as regras de negócio, a atribuição pela categoria, os modelos de chamado e as
 * notificações funcionam normalmente. O token CSRF é validado pelo próprio GLPI.
 *
 * This file is part of GLPI Chatbot (GPLv3+).
 */

require_once __DIR__ . '/../inc/functions.php';

Session::checkLoginUser();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    plugin_glpichatbot_json(['error' => 'Método não permitido.'], 405);
    return;
}
if (!Session::haveRight('ticket', CREATE)) {
    plugin_glpichatbot_json(['error' => 'Você não tem permissão para abrir chamados.'], 403);
    return;
}

$type        = (int) ($_POST['type'] ?? 0);
$entity      = plugin_glpichatbot_entity($_POST['entity'] ?? '');
$category    = (int) ($_POST['category'] ?? 0);
$title       = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$urgency     = (int) ($_POST['urgency'] ?? 3);

$errors = [];
if (!in_array($type, [Ticket::INCIDENT_TYPE, Ticket::DEMAND_TYPE], true)) {
    $errors[] = 'Tipo de chamado inválido.';
}
$allowed = plugin_glpichatbot_categories($entity, $type);
if (!isset($allowed[$category]) || !$allowed[$category]['selectable']) {
    $errors[] = 'Categoria inválida para este tipo de chamado.';
}
if (mb_strlen($title) < 3 || mb_strlen($title) > 250) {
    $errors[] = 'O resumo precisa ter entre 3 e 250 caracteres.';
}
if (mb_strlen($description) < 3 || mb_strlen($description) > 20000) {
    $errors[] = 'A descrição precisa ter entre 3 e 20.000 caracteres.';
}
if ($urgency < 1 || $urgency > 5) {
    $errors[] = 'Urgência inválida.';
}
if ($errors !== []) {
    plugin_glpichatbot_json(['error' => implode(' ', $errors)], 422);
    return;
}

// Texto simples -> HTML seguro (o conteúdo do chamado é rich text no GLPI)
$content = '<p>' . nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>'
    . '<p><em>Aberto pelo assistente de chamados.</em></p>';

$ticket = new Ticket();
$id = $ticket->add([
    'name'                => $title,
    'content'             => $content,
    'type'                => $type,
    'itilcategories_id'   => $category,
    'urgency'             => $urgency,
    'entities_id'         => $entity,
    '_users_id_requester' => Session::getLoginUserID(),
]);

// Mensagens que o GLPI gerou (ex.: campo obrigatório do modelo de chamado)
$messages = [];
foreach ($_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [] as $type_messages) {
    foreach ((array) $type_messages as $message) {
        $messages[] = trim(strip_tags((string) $message));
    }
}
$_SESSION['MESSAGE_AFTER_REDIRECT'] = [];

if (!$id) {
    plugin_glpichatbot_json([
        'error' => $messages !== [] ? implode(' ', $messages) : 'Não foi possível abrir o chamado.',
    ], 422);
    return;
}

// Anexo enviado pelo assistente (opcional): se falhar, o chamado continua aberto e o usuário é avisado
$response = ['id' => (int) $id];
if (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $anexo = plugin_glpichatbot_attach((int) $id, $entity, $_FILES['file']);
    if ($anexo !== true) {
        $response['warning'] = "O chamado foi aberto, mas o anexo não foi salvo: {$anexo}. Você pode anexar o arquivo direto no chamado.";
    }
}

plugin_glpichatbot_json($response, 201);
