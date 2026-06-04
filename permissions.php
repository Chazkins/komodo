<?php

$roles = [
    1 => 'Администратор',
    2 => 'Менеджер',
    3 => 'Пользователь',
    
];

$permissions = [

    // АДМИНИСТРАТОР: полный доступ ---
    1 => [
        'tables'   => '*',           // все таблицы
        'actions'  => ['view', 'add', 'edit', 'delete', 'export'],
        'features' => ['admin_panel', 'bans', 'backup', 'import', 'validator', 'catalog'],
    ],

    // МЕНЕДЖЕР: ограниченный доступ ---
    2 => [
        'tables'   => [              // только эти таблицы
            'products',
            'categories',
            'manufacturers',
            'orders',
            'order_items',
            
        ],
        'actions'  => ['view', 'add', 'edit', 'export'],   // без удаления
        'features' => ['admin_panel', 'import', 'validator', 'catalog'],
    ],

    
    3 => [
        'tables'   => [],            // нет доступа к таблицам
        'actions'  => [],
        'features' => ['catalog'],
    ],

];

function getCurrentRoleId() {
    return $_SESSION['role_id'] ?? null;
}


function canAccessTable($table) {
    global $permissions;
    $role_id = getCurrentRoleId();
    if (!$role_id || !isset($permissions[$role_id])) return false;

    $tables = $permissions[$role_id]['tables'];
    if ($tables === '*') return true;
    return in_array($table, $tables);
}


  //Проверить: разрешено ли действие для текущей роли?
function canDo($action) {
    global $permissions;
    $role_id = getCurrentRoleId();
    if (!$role_id || !isset($permissions[$role_id])) return false;

    return in_array($action, $permissions[$role_id]['actions']);
}


function canUseFeature($feature) {
    global $permissions;
    $role_id = getCurrentRoleId();
    if (!$role_id || !isset($permissions[$role_id])) return false;

    return in_array($feature, $permissions[$role_id]['features']);
}


function getAllowedTables($all_tables) {
    global $permissions;
    $role_id = getCurrentRoleId();
    if (!$role_id || !isset($permissions[$role_id])) return [];

    $tables = $permissions[$role_id]['tables'];
    if ($tables === '*') return $all_tables;   // администратор видит всё
    return array_values(array_intersect($all_tables, $tables));
}


function requireFeature($feature, $redirect = 'index.php') {
    if (!canUseFeature($feature)) {
        header("Location: $redirect");
        exit();
    }
}

function requireAuth($redirect = 'index.php') {
    if (!getCurrentRoleId()) {
        header("Location: $redirect");
        exit();
    }
}