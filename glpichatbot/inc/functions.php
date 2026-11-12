<?php

/**
 * ------------------------------------------------------------------------
 * GLPI Chatbot - funções compartilhadas pelos endpoints AJAX
 * ------------------------------------------------------------------------
 * This file is part of GLPI Chatbot (GPLv3+).
 * ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    include __DIR__ . '/../../../inc/includes.php';
}

/**
 * Escreve a resposta JSON. O chamador deve dar "return" logo depois
 * (o GLPI 11 monta a resposta HTTP após o script terminar; não use exit).
 */
function plugin_glpichatbot_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

/**
 * Opções do plugin (Configurar › Plugins › GLPI Chatbot). Opções ainda não
 * gravadas (ex.: atualização sem reinstalar) assumem o valor padrão.
 *
 * @return array{force_selfservice_redirect:bool}
 */
function plugin_glpichatbot_config(): array
{
    $values = Config::getConfigurationValues('plugin:glpichatbot');
    return [
        'force_selfservice_redirect' => (bool) ($values['force_selfservice_redirect'] ?? 0),
    ];
}

/**
 * Validação de mesma origem para requisições POST.
 * Compatível com GLPI 10, 11 e 12 (Sec-Fetch-Site ou Origin/Host).
 */
function plugin_glpichatbot_require_same_origin_post(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        return false;
    }

    $fetch_site = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetch_site !== '') {
        $valid = ($fetch_site === 'same-origin');
    } else {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if ($origin === '' && isset($_SERVER['HTTP_REFERER'])) {
            $origin = (string) $_SERVER['HTTP_REFERER'];
        }

        $origin_host = parse_url($origin, PHP_URL_HOST);
        $origin_port = parse_url($origin, PHP_URL_PORT);

        $expected_host = is_string($origin_host)
            ? $origin_host . ($origin_port !== null ? ':' . $origin_port : '')
            : '';

        $valid = $origin !== ''
            && $host !== ''
            && hash_equals(strtolower($host), strtolower($expected_host));
    }

    if (!$valid) {
        http_response_code(403);
        return false;
    }

    return true;
}

/**
 * Dicionário de internacionalização para a interface do assistente.
 *
 * @return array<string, string>
 */
function plugin_glpichatbot_i18n_strings(string $lang): array
{
    $is_en = str_starts_with(strtolower($lang), 'en');

    if ($is_en) {
        return [
            'welcome'           => 'Hello, %s! How can I help you today?',
            'welcome_anon'      => 'Hello! How can I help you today?',
            'btn_open_ticket'   => '🎫 Open ticket',
            'btn_my_tickets'    => '🔍 My tickets',
            'choose_type'       => 'What type of ticket do you need to open?',
            'choose_entity'     => 'For which entity/company is this request?',
            'choose_category'   => 'Choose the category for your ticket:',
            'no_categories'     => 'No categories available for this ticket type. Please contact IT or try another type.',
            'ask_title'         => 'Briefly summarize what happened or what you need:',
            'ph_title'          => 'Example: Unable to access the system...',
            'ask_desc'          => 'Now provide more details to assist the support team:',
            'ph_desc'           => 'Describe what you were doing, error messages, and any relevant details...',
            'ask_urgency'       => 'What is the urgency of this ticket?',
            'review_title'      => 'Please review the details before submitting:',
            'btn_confirm'       => 'Confirm & open ticket',
            'btn_cancel'        => 'Cancel',
            'ticket_created'    => 'Ticket #%d created successfully!',
            'btn_view_ticket'   => 'View ticket',
            'btn_restart'       => '↺ Restart',
            'btn_back'          => '← Back',
            'filter_ph'         => 'Type to filter...',
            'no_filter_results' => 'No options match your filter.',
            'attach_file'       => '📎 Attach file (optional)',
            'remove_file'       => 'Remove',
            'file_too_large'    => 'File exceeds the maximum allowed size.',
            'my_tickets_title'  => 'Here are your recent open tickets:',
            'no_my_tickets'     => 'You currently have no open tickets.',
            'error_generic'     => 'An error occurred. Please try again.',
            'error_load_cat'    => 'Could not load categories.',
            'error_submitting'  => 'Submitting ticket...',
            'ticket_summary'    => 'Summary',
            'ticket_details'    => 'Details',
            'ticket_type'       => 'Type',
            'ticket_category'   => 'Category',
            'ticket_entity'     => 'Entity',
            'ticket_urgency'    => 'Urgency',
            'ticket_attachment' => 'Attachment',
            'badge_assistant'   => 'Virtual Assistant',
            'fullscreen'        => 'Toggle fullscreen',
            'close'             => 'Close assistant',
            'launcher_label'    => 'Open ticket',
        ];
    }

    return [
        'welcome'           => 'Olá, %s! Como posso te ajudar hoje?',
        'welcome_anon'      => 'Olá! Como posso te ajudar hoje?',
        'btn_open_ticket'   => '🎫 Abrir chamado',
        'btn_my_tickets'    => '🔍 Consultar meus chamados',
        'choose_type'       => 'Qual tipo de chamado você precisa abrir?',
        'choose_entity'     => 'Em qual unidade/empresa é a solicitação?',
        'choose_category'   => 'Escolha o assunto do chamado:',
        'no_categories'     => 'Não há categorias disponíveis para este tipo de chamado. Fale com a TI ou tente o outro tipo.',
        'ask_title'         => 'Descreva resumidamente o que aconteceu ou o que você precisa:',
        'ph_title'          => 'Exemplo: Não consigo acessar o sistema...',
        'ask_desc'          => 'Agora dê mais detalhes para ajudar o atendimento:',
        'ph_desc'           => 'Conte o que você estava fazendo, se apareceu mensagem de erro...',
        'ask_urgency'       => 'Qual é a urgência deste chamado?',
        'review_title'      => 'Confira os dados antes de abrir o chamado:',
        'btn_confirm'       => 'Confirmar abertura',
        'btn_cancel'        => 'Cancelar',
        'ticket_created'    => 'Chamado #%d aberto com sucesso!',
        'btn_view_ticket'   => 'Ver chamado',
        'btn_restart'       => '↺ Recomeçar',
        'btn_back'          => '← Voltar',
        'filter_ph'         => 'Digite para filtrar...',
        'no_filter_results' => 'Nenhum resultado para o filtro.',
        'attach_file'       => '📎 Anexar arquivo (opcional)',
        'remove_file'       => 'Remover',
        'file_too_large'    => 'O arquivo ultrapassa o tamanho máximo permitido.',
        'my_tickets_title'  => 'Aqui estão seus chamados em andamento:',
        'no_my_tickets'     => 'Você não possui chamados em andamento no momento.',
        'error_generic'     => 'Ocorreu um erro. Por favor, tente novamente.',
        'error_load_cat'    => 'Não foi possível carregar as categorias.',
        'error_submitting'  => 'Abrindo chamado...',
        'ticket_summary'    => 'Resumo',
        'ticket_details'    => 'Detalhes',
        'ticket_type'       => 'Tipo',
        'ticket_category'   => 'Categoria',
        'ticket_entity'     => 'Unidade',
        'ticket_urgency'    => 'Urgência',
        'ticket_attachment' => 'Anexo',
        'badge_assistant'   => 'Assistente Virtual',
        'fullscreen'        => 'Alternar tela cheia',
        'close'             => 'Fechar assistente',
        'launcher_label'    => 'Abrir chamado',
    ];
}

/**
 * Urgências (1 a 5) que podem ser escolhidas, conforme Configurar › Geral ›
 * Assistência › "Urgências permitidas" ($CFG_GLPI['urgency_mask']).
 *
 * @return int[]
 */
function plugin_glpichatbot_urgencies(): array
{
    /** @var array $CFG_GLPI */
    global $CFG_GLPI;

    $mask    = (int) ($CFG_GLPI['urgency_mask'] ?? 0);
    $allowed = [];
    for ($urgency = 1; $urgency <= 5; $urgency++) {
        // A urgência média (3) é sempre permitida pelo GLPI
        if ($urgency === 3 || ($mask & (1 << $urgency))) {
            $allowed[] = $urgency;
        }
    }
    return $allowed;
}

/**
 * Mensagens que o GLPI guarda para mostrar após o redirecionamento.
 * Usado para separar as mensagens geradas por uma operação do assistente
 * das que já estavam na sessão (que devem continuar lá).
 */
function plugin_glpichatbot_messages_snapshot(): array
{
    return $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [];
}

/**
 * Tira da sessão as mensagens geradas desde $snapshot (restaurando as que já
 * existiam) e as devolve como texto simples.
 *
 * @return string[]
 */
function plugin_glpichatbot_messages_since(array $snapshot): array
{
    $new = [];
    foreach ($_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [] as $level => $type_messages) {
        $before = array_values((array) ($snapshot[$level] ?? []));
        foreach (array_values((array) $type_messages) as $i => $message) {
            if (isset($before[$i]) && $before[$i] === $message) {
                continue; // já estava na sessão antes da operação
            }
            $new[] = trim(html_entity_decode(strip_tags((string) $message), ENT_QUOTES, 'UTF-8'));
        }
    }
    $_SESSION['MESSAGE_AFTER_REDIRECT'] = $snapshot;
    return array_values(array_unique(array_filter($new, static fn($m) => $m !== '')));
}

/**
 * Entidade solicitada, se o usuário tiver acesso a ela; senão, -1 (inválida).
 */
function plugin_glpichatbot_entity($requested): int
{
    if (is_numeric($requested) && (int) $requested >= 0 && Session::haveAccessToEntity((int) $requested)) {
        return (int) $requested;
    }
    return -1;
}

/**
 * Categorias que o usuário pode escolher, com as mesmas regras do campo nativo:
 * entidade (com recursividade), "visível na interface simplificada" e tipo do chamado.
 * Pais que não atendem aos filtros, mas têm filhos válidos, voltam com selectable=false.
 *
 * @return array<int, array{id:int, parent:int, name:string, selectable:bool}>
 */
function plugin_glpichatbot_categories(int $entity, int $type): array
{
    /** @var DBmysql $DB */
    global $DB;

    $table = ITILCategory::getTable();
    $where = [getEntitiesRestrictCriteria($table, '', $entity, true)];

    if (Session::getCurrentInterface() === 'helpdesk') {
        $where[] = ['is_helpdeskvisible' => 1];
    }
    if ($type === Ticket::INCIDENT_TYPE) {
        $where[] = ['is_incident' => 1];
    } elseif ($type === Ticket::DEMAND_TYPE) {
        $where[] = ['is_request' => 1];
    } else {
        $where[] = ['OR' => ['is_incident' => 1, 'is_request' => 1]];
    }

    $nodes = [];
    foreach ($DB->request(['SELECT' => ['id', 'name', 'itilcategories_id'], 'FROM' => $table, 'WHERE' => $where]) as $row) {
        $nodes[(int) $row['id']] = [
            'id'         => (int) $row['id'],
            'parent'     => (int) $row['itilcategories_id'],
            'name'       => (string) $row['name'],
            'selectable' => true,
        ];
    }

    // Ancestrais ausentes (só para navegação), nível a nível
    for ($depth = 0; $depth < 30; $depth++) {
        $missing = [];
        foreach ($nodes as $node) {
            if ($node['parent'] > 0 && !isset($nodes[$node['parent']])) {
                $missing[$node['parent']] = $node['parent'];
            }
        }
        if ($missing === []) {
            break;
        }
        $found = 0;
        $missing_where = ['id' => array_values($missing), getEntitiesRestrictCriteria($table, '', $entity, true)];
        foreach ($DB->request(['SELECT' => ['id', 'name', 'itilcategories_id'], 'FROM' => $table, 'WHERE' => $missing_where]) as $row) {
            $nodes[(int) $row['id']] = [
                'id'         => (int) $row['id'],
                'parent'     => (int) $row['itilcategories_id'],
                'name'       => (string) $row['name'],
                'selectable' => false,
            ];
            $found++;
        }
        if ($found === 0) {
            foreach ($nodes as &$node) {
                if (isset($missing[$node['parent']])) {
                    $node['parent'] = 0;
                }
            }
            unset($node);
        }
    }

    if ($nodes !== [] && Session::haveTranslations('ITILCategory', 'name')) {
        $ids = array_keys($nodes);
        $lang = $_SESSION['glpilanguage'] ?? 'pt_BR';
        $translations = [];
        $trans_iter = $DB->request([
            'SELECT' => ['items_id', 'value'],
            'FROM'   => 'glpi_dropdowntranslations',
            'WHERE'  => [
                'itemtype' => 'ITILCategory',
                'field'    => 'name',
                'language' => $lang,
                'items_id' => $ids,
            ],
        ]);
        foreach ($trans_iter as $trow) {
            if (!empty($trow['value'])) {
                $translations[(int) $trow['items_id']] = (string) $trow['value'];
            }
        }
        foreach ($nodes as &$node) {
            if (isset($translations[$node['id']])) {
                $node['name'] = $translations[$node['id']];
            }
        }
        unset($node);
    }

    return $nodes;
}

/**
 * Anexa ao chamado o arquivo enviado pelo assistente ($_FILES['file']).
 *
 * O arquivo vai para a pasta temporária do GLPI e o próprio GLPI cria o documento:
 * confere o tipo (Configurar › Listas suspensas › Tipos de documento), calcula o
 * checksum e guarda em files/. Retorna true ou a mensagem de erro para o usuário.
 *
 * @return true|string
 */
function plugin_glpichatbot_attach(int $tickets_id, int $entity, array $file)
{
    global $CFG_GLPI;

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return 'o arquivo não chegou ao servidor (pode ser maior que o limite de upload do PHP)';
    }
    $max_mb = (int) ($CFG_GLPI['document_max_size'] ?? 0);
    if ($max_mb > 0 && (int) $file['size'] > $max_mb * 1024 * 1024) {
        return "o arquivo passa do tamanho máximo de {$max_mb} MB";
    }

    // Nome sem caminho nem caracteres que o sistema de arquivos não aceita
    $name = trim((string) preg_replace('#[\\\\/:*?"<>|\x00-\x1F]+#u', '_', basename((string) $file['name'])));
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'anexo';
    }
    $prefix = bin2hex(random_bytes(6)) . '_';
    $tmp    = GLPI_TMP_DIR . '/' . $prefix . $name;
    if (!move_uploaded_file($file['tmp_name'], $tmp)) {
        return 'não foi possível gravar o arquivo no servidor';
    }

    $snapshot = plugin_glpichatbot_messages_snapshot();
    $doc      = new Document();
    $doc_id   = (int) $doc->add([
        'name'             => $name,
        'entities_id'      => $entity,
        '_filename'        => [$prefix . $name],
        '_prefix_filename' => [$prefix],
    ]);
    if (is_file($tmp)) {
        @unlink($tmp);
    }
    if ($doc_id <= 0 || empty($doc->fields['filepath'])) {
        if ($doc_id > 0) {
            $doc->delete(['id' => $doc_id], true);
        }
        // Repassa o motivo que o GLPI deu (ex.: tipo de arquivo não permitido)
        $motivos = plugin_glpichatbot_messages_since($snapshot);
        return $motivos !== [] ? implode(' ', $motivos) : 'o GLPI não aceitou o arquivo';
    }

    // Vínculo separado: criando o documento já vinculado, o GLPI troca o nome pelo do chamado
    $link_id = (new Document_Item())->add([
        'documents_id' => $doc_id,
        'itemtype'     => 'Ticket',
        'items_id'     => $tickets_id,
        'entities_id'  => $entity,
    ]);
    $motivos = plugin_glpichatbot_messages_since($snapshot);
    if (!$link_id) {
        // Documento sem vínculo ficaria órfão: remove
        $doc->delete(['id' => $doc_id], true);
        return $motivos !== []
            ? 'não foi possível vincular o arquivo ao chamado (' . implode(' ', $motivos) . ')'
            : 'não foi possível vincular o arquivo ao chamado';
    }
    return true;
}
