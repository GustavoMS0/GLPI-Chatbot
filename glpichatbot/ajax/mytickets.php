<?php
require_once __DIR__ . '/../inc/functions.php';

Session::checkLoginUser();
global $DB;

$uid = (int) Session::getLoginUserID();
$tickets = [];

if ($uid > 0) {
    try {
        $iterator = $DB->request([
            'SELECT' => [
                'glpi_tickets.id', 
                'glpi_tickets.name', 
                'glpi_tickets.status', 
                'glpi_tickets.date_creation'
            ],
            'FROM'   => 'glpi_tickets',
            'INNER JOIN' => [
                'glpi_tickets_users' => [
                    'ON' => [
                        'glpi_tickets_users' => 'tickets_id',
                        'glpi_tickets'       => 'id'
                    ]
                ]
            ],
            'WHERE'  => [
                'glpi_tickets_users.users_id' => $uid,
                'glpi_tickets_users.type'     => 1,
                'glpi_tickets.is_deleted'     => 0,
                'NOT' => [
                    'glpi_tickets.status' => [Ticket::SOLVED, Ticket::CLOSED]
                ]
            ],
            'ORDER'  => 'glpi_tickets.id DESC',
            'LIMIT'  => 10
        ]);

        foreach ($iterator as $row) {
            $row['status_name'] = Ticket::getStatus($row['status']);
            $dt = $row['date_creation'] ?? date('Y-m-d');
            $row['date_short'] = date('d/m/Y', strtotime($dt));
            $tickets[] = $row;
        }
    } catch (\Throwable $e) {
        plugin_glpichatbot_json(['error' => $e->getMessage()], 400);
        return;
    }
}

plugin_glpichatbot_json($tickets);
