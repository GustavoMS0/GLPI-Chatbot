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
