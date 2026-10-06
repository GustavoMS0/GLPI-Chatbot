<?php

/**
 * ------------------------------------------------------------------------
 * GLPI Chatbot - funções compartilhadas pelos endpoints AJAX
 * ------------------------------------------------------------------------
 * This file is part of GLPI Chatbot (GPLv3+).
 * ------------------------------------------------------------------------
 */

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
 * Entidade solicitada, se o usuário tiver acesso a ela; senão, a entidade ativa.
 */
function plugin_glpichatbot_entity($requested): int
{
    if (is_numeric($requested) && (int) $requested >= 0 && Session::haveAccessToEntity((int) $requested)) {
        return (int) $requested;
    }
    return (int) $_SESSION['glpiactive_entity'];
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
        foreach ($DB->request(['SELECT' => ['id', 'name', 'itilcategories_id'], 'FROM' => $table, 'WHERE' => ['id' => array_values($missing)]]) as $row) {
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

    $doc    = new Document();
    $doc_id = (int) $doc->add([
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
        $motivos = [];
        foreach ($_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [] as $type_messages) {
            foreach ((array) $type_messages as $message) {
                $motivos[] = trim(html_entity_decode(strip_tags((string) $message), ENT_QUOTES, 'UTF-8'));
            }
        }
        $_SESSION['MESSAGE_AFTER_REDIRECT'] = [];
        return $motivos !== [] ? implode(' ', array_unique($motivos)) : 'o GLPI não aceitou o arquivo';
    }

    // Vínculo separado: criando o documento já vinculado, o GLPI troca o nome pelo do chamado
    (new Document_Item())->add([
        'documents_id' => $doc_id,
        'itemtype'     => 'Ticket',
        'items_id'     => $tickets_id,
        'entities_id'  => $entity,
    ]);
    return true;
}
