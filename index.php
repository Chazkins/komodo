<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

ob_start();
session_start();
include 'db.php';
include 'permissions.php';

// --- ВЫХОД ---
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

$error_message = '';
$export_error  = '';
$import_error  = '';
$import_success = '';
$max_attempts         = 3;
$max_captcha_attempts = 3;

// ============================================================
//  ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ АВТОРИЗАЦИИ
// ============================================================
function isUserBanned($login, $conn) {
    $login = $conn->real_escape_string($login);
    $result = $conn->query("SELECT 1 FROM banned_users WHERE login = '$login' AND (banned_until IS NULL OR banned_until > NOW())");
    return $result && $result->num_rows > 0;
}

function getFailedAttempts($login, $conn) {
    $login  = $conn->real_escape_string($login);
    $result = $conn->query("SELECT COUNT(*) as attempts FROM failed_logins
                            WHERE login = '$login'
                            AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    if (!$result) return 0;
    return $result->fetch_assoc()['attempts'];
}

function getFailedCaptchaAttempts($login, $conn) {
    $login  = $conn->real_escape_string($login);
    $result = $conn->query("SELECT COUNT(*) as attempts FROM failed_captcha_attempts
                            WHERE login = '$login'
                            AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    if (!$result) return 0;
    return $result->fetch_assoc()['attempts'];
}

function addFailedAttempt($login, $conn) {
    $ip    = $conn->real_escape_string($_SERVER['REMOTE_ADDR']);
    $login = $conn->real_escape_string($login);
    $conn->query("INSERT INTO failed_logins (login, ip_address) VALUES ('$login', '$ip')");
}

function addFailedCaptchaAttempt($login, $conn) {
    $ip    = $conn->real_escape_string($_SERVER['REMOTE_ADDR']);
    $login = $conn->real_escape_string($login);
    $conn->query("INSERT INTO failed_captcha_attempts (login, ip_address) VALUES ('$login', '$ip')");
}

function banUser($login, $conn, $ban_type = 'login') {
    error_log("banUser called for login: $login, type: $ban_type");
    $login = $conn->real_escape_string($login);
    // Удаляем старую блокировку, если была
    $conn->query("DELETE FROM banned_users WHERE login = '$login'");
    $reason = $ban_type === 'captcha'
        ? 'Превышено количество неудачных попыток ввода капчи'
        : 'Превышено количество неудачных попыток входа';
    $reason = $conn->real_escape_string($reason);
    $sql = "INSERT INTO banned_users (login, banned_until, reason, ban_type)
            VALUES ('$login', NULL, '$reason', '$ban_type')";
    $result = $conn->query($sql);
    if ($result) {
        error_log("banUser succeeded for $login");
    } else {
        error_log("banUser failed for $login: " . $conn->error);
    }
    return $result;
}

function hashPassword($password)         { return password_hash($password, PASSWORD_DEFAULT); }
function verifyPassword($password, $hash) { return password_verify($password, $hash); }

function getTableList($conn) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    if ($result) while ($row = $result->fetch_array()) $tables[] = $row[0];
    return $tables;
}

function generatePuzzleCaptcha() {
    $order = [1, 2, 3, 4];
    do { shuffle($order); } while ($order === [1, 2, 3, 4]);
    $_SESSION['puzzle_correct_order'] = [1, 2, 3, 4];
    $_SESSION['puzzle_current_order'] = $order;
    return ['current_order' => $order, 'correct_order' => [1, 2, 3, 4]];
}

// ============================================================
//  ЭКСПОРТ / ИМПОРТ (без изменений)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'export_table'
    && isset($_SESSION['role_id']))
{
    if (!canDo('export')) {
        $export_error = "У вас нет прав для экспорта данных.";
    } else {
        try {
            $table_name  = trim($_POST['export_table']);
            $tables_list = getTableList($conn);
            if (!in_array($table_name, $tables_list)) {
                $export_error = "Таблица '$table_name' не существует.";
            } elseif (!canAccessTable($table_name)) {
                $export_error = "У вас нет доступа к таблице '$table_name'.";
            } else {
                $tn     = $conn->real_escape_string($table_name);
                $result = $conn->query("SELECT * FROM `$tn`");
                if (!$result)                  { $export_error = "Ошибка запроса: " . $conn->error; }
                elseif ($result->num_rows === 0) { $export_error = "Таблица '$table_name' пуста."; }
                else {
                    $data = [];
                    while ($row = $result->fetch_assoc()) $data[] = $row;
                    ob_end_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="'
                           . $table_name . '_export_' . date('Y-m-d_H-i') . '.json"');
                    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    exit();
                }
            }
        } catch (Exception $e) { $export_error = "Ошибка экспорта: " . $e->getMessage(); }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'import_json'
    && isset($_SESSION['role_id']))
{
    if (!canUseFeature('import')) {
        $import_error = "У вас нет прав для импорта данных.";
    } else {
        $target_table = trim($_POST['import_table']);
        $tables_list  = getTableList($conn);

        if (!in_array($target_table, $tables_list)) {
            $import_error = "Выбрана несуществующая таблица.";
        } elseif (!canAccessTable($target_table)) {
            $import_error = "У вас нет доступа к таблице '$target_table'.";
        } elseif (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $import_error = "Ошибка загрузки файла.";
        } else {
            $json_content = file_get_contents($_FILES['import_file']['tmp_name']);
            $data = json_decode($json_content, true);

            if (!is_array($data) || empty($data)) {
                $import_error = "Файл не содержит валидных данных JSON или пуст.";
            } else {
                $tn         = $conn->real_escape_string($target_table);
                $col_result = $conn->query("SHOW COLUMNS FROM `$tn`");
                $valid_cols = [];
                while ($row = $col_result->fetch_assoc()) $valid_cols[] = $row['Field'];

                $imported = 0; $skipped = 0;
                $conn->query("START TRANSACTION");

                foreach ($data as $row_data) {
                    if (!is_array($row_data)) { $skipped++; continue; }
                    $filtered = array_intersect_key($row_data, array_flip($valid_cols));
                    if (empty($filtered)) { $skipped++; continue; }

                    $cols = array_keys($filtered);
                    $vals = array_map(function($v) use ($conn) {
                        return is_null($v) ? 'NULL' : "'" . $conn->real_escape_string((string)$v) . "'";
                    }, $filtered);

                    $sql = "INSERT IGNORE INTO `$tn` (`" . implode('`,`', $cols) . "`) VALUES ("
                           . implode(',', $vals) . ")";
                    if ($conn->query($sql)) $imported++; else $skipped++;
                }

                if ($imported > 0 || $skipped === 0) {
                    $conn->query("COMMIT");
                    $import_success = "Импорт завершён. Добавлено: $imported, Пропущено: $skipped.";
                } else {
                    $conn->query("ROLLBACK");
                    $import_error = "Не удалось импортировать данные. Проверьте структуру JSON.";
                }
            }
        }
    }
}

// ============================================================
//  ОБРАБОТКА ЛОГИНА (ИСПРАВЛЕНА)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    try {
        $login    = trim($_POST['login']);
        $password = $_POST['password'];

        if (isUserBanned($login, $conn)) {
            $error_message = "Аккаунт временно заблокирован. Обратитесь к администратору.";
        } elseif (empty($_POST['puzzle_order'])) {
            $error_message = "Пожалуйста, соберите пазл для входа.";
        } else {
            $submitted_order = json_decode($_POST['puzzle_order'], true);
            $correct_order   = $_SESSION['puzzle_correct_order'] ?? null;

            if ($correct_order === null) {
                $error_message  = "Сессия капчи устарела. Попробуйте ещё раз.";
                $captcha_data   = generatePuzzleCaptcha();
            } elseif (json_encode($submitted_order) !== json_encode($correct_order)) {
                // НЕВЕРНАЯ КАПЧА
                $check = $conn->prepare("SELECT 1 FROM users WHERE login = ?");
                $check->bind_param("s", $login);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    addFailedCaptchaAttempt($login, $conn);
                    $new_fc = getFailedCaptchaAttempts($login, $conn);
                    if ($new_fc >= $max_captcha_attempts) {
                        banUser($login, $conn, 'captcha');
                        $error_message = "Превышено число попыток капчи. Аккаунт заблокирован до разблокировки администратором.";
                    } else {
                        $rem = $max_captcha_attempts - $new_fc;
                        $error_message = "Неверно собран пазл. Осталось попыток: $rem";
                    }
                } else {
                    $error_message = "Неверный логин, пароль или капча.";
                }
                unset($_SESSION['puzzle_correct_order'], $_SESSION['puzzle_current_order']);
                $captcha_data = generatePuzzleCaptcha();
            } else {
                // КАПЧА ВЕРНА, ПРОВЕРЯЕМ ПАРОЛЬ
                $fa = getFailedAttempts($login, $conn);
                if ($fa >= $max_attempts) {
                    // Уже есть 3 неудачных попытки – блокируем без проверки пароля
                    banUser($login, $conn, 'login');
                    $error_message = "Превышено число попыток входа. Аккаунт заблокирован до разблокировки администратором.";
                } else {
                    $le  = $conn->real_escape_string($login);
                    $res = $conn->query("SELECT id, login, password_hash, role_id FROM users WHERE login = '$le'");
                    if ($res && $res->num_rows === 1) {
                        $user = $res->fetch_assoc();
                        if (verifyPassword($password, $user['password_hash'])) {
                            // УСПЕШНЫЙ ВХОД – очищаем счётчики
                            $conn->query("DELETE FROM failed_logins WHERE login = '$le'");
                            $conn->query("DELETE FROM failed_captcha_attempts WHERE login = '$le'");
                            $_SESSION['user_id']    = $user['id'];
                            $_SESSION['user_login'] = $user['login'];
                            $_SESSION['role_id']    = $user['role_id'];
                            if (canUseFeature('catalog') && !canUseFeature('admin_panel')) {
                                header("Location: catalog.php"); exit();
                            } else {
                                $_SESSION['admin_login'] = $user['login'];
                                header("Location: index.php"); exit();
                            }
                        } else {
                            // НЕВЕРНЫЙ ПАРОЛЬ
                            addFailedAttempt($login, $conn);
                            $new_fa = getFailedAttempts($login, $conn);
                            if ($new_fa >= $max_attempts) {
                                banUser($login, $conn, 'login');
                                $error_message = "Превышено число попыток входа. Аккаунт заблокирован до разблокировки администратором.";
                            } else {
                                
                                $error_message = "Неверный логин или пароль.";
                            }
                            unset($_SESSION['puzzle_correct_order'], $_SESSION['puzzle_current_order']);
                            $captcha_data = generatePuzzleCaptcha();
                        }
                    } else {
                        addFailedAttempt($login, $conn);
                        $error_message = "Неверный логин или пароль.";
                        unset($_SESSION['puzzle_correct_order'], $_SESSION['puzzle_current_order']);
                        $captcha_data = generatePuzzleCaptcha();
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "Ошибка входа: " . $e->getMessage();
    }
}

$captcha_data = generatePuzzleCaptcha();

$all_tables    = getTableList($conn);
$tables_list   = isset($_SESSION['role_id']) ? getAllowedTables($all_tables) : [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Панель управления БД</title>
<link rel="stylesheet" href="style.css">
<style>
body {
    background-image: url('images/back.png');
    background-size: cover;
    background-position: center center;
    background-attachment: fixed;
    background-repeat: no-repeat;
    position: relative;
}
body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.55);
    z-index: -1;
}
.site-logo {
    text-align: center;
    margin-bottom: 20px;
}
.site-logo img {
    max-width: 200px;
    height: auto;
}
.login-panel, .dash-card, .sql-query-panel, .compact-table-wrapper {
    background-color: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(2px);
}
.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:20px;margin-top:20px}
.dash-card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 4px 12px rgba(0,0,0,.05);border:1px solid #eaeaea}
.dash-header{margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #eee}
.form-grid{display:flex;flex-direction:column;gap:12px}
.file-input-wrapper input[type="file"]{display:block;width:100%;padding:10px;border:1px dashed #bbb;border-radius:8px;cursor:pointer;background:#f8f9fa;font-size:.9rem}
.status-box{padding:10px 14px;border-radius:8px;margin-top:12px;font-size:.85rem;font-weight:500}
.status-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.status-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
.login-wrapper{display:flex;justify-content:center;align-items:center;min-height:70vh}
.login-panel{background:#fff;border-radius:14px;padding:32px;width:100%;max-width:420px;box-shadow:0 8px 24px rgba(0,0,0,.08);border:1px solid #eee}
.login-header{text-align:center;margin-bottom:24px}
.captcha-section{background:#f8f9fa;padding:16px;border-radius:10px;margin:16px 0;border:1px solid #eaeaea}
.role-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:600;background:#3a6ea5;color:#fff;margin-left:8px}
</style>
</head>
<body>
<div class="container">
<?php if (isset($_SESSION['admin_login'])): ?>
<!-- ===================== ПАНЕЛЬ УПРАВЛЕНИЯ ===================== -->
<div class="sidebar">
    <div class="table-list">
        <?php foreach ($tables_list as $t): ?>
        <a href="table_view.php?table=<?= urlencode($t) ?>" class="table-item">
            <?= htmlspecialchars($t) ?>
        </a>
        <?php endforeach; ?>
        <?php if (canUseFeature('bans')): ?>
        <a href="admin_bans.php" class="table-item">Блокировки</a>
        <?php endif; ?>
        <?php if (canUseFeature('validator')): ?>
        <a href="emulator_results.php" class="table-item">Валидация данных</a>
        <?php endif; ?>
        <a href="index.php?logout=1" class="table-item"
           style="background:#e67e22;color:#fff;margin-top:10px;">Выйти</a>
    </div>
</div>

<div class="main-content">
    <header>
        <div class="header-content">
            <h1>Панель управления
                <span class="role-badge">
                    <?= htmlspecialchars($roles[$_SESSION['role_id']] ?? 'Роль ' . $_SESSION['role_id']) ?>
                </span>
            </h1>
            <p>Добро пожаловать, <strong><?= htmlspecialchars($_SESSION['admin_login']) ?></strong>!</p>
        </div>
    </header>
    <main>
        <div class="dashboard-grid">
            <?php if (canDo('export') || canUseFeature('import')): ?>
            <div class="dash-card">
                <div class="dash-header"><h3>📦 Управление данными</h3></div>
                <div class="form-grid">
                    <?php if (canDo('export')): ?>
                    <form method="post" action="index.php">
                        <input type="hidden" name="action" value="export_table">
                        <div class="form-group">
                            <label>Экспорт таблицы в JSON</label>
                            <select name="export_table" required>
                                <option value="">-- Выберите таблицу --</option>
                                <?php foreach ($tables_list as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($export_error): ?>
                        <div class="status-box status-error"><?= htmlspecialchars($export_error) ?></div>
                        <?php endif; ?>
                        <button type="submit" class="export-btn" style="margin-top:8px;">Скачать JSON</button>
                    </form>
                    <?php endif; ?>
                    <?php if (canDo('export') && canUseFeature('import')): ?>
                    <hr style="border:0;border-top:1px solid #eee;margin:8px 0">
                    <?php endif; ?>
                    <?php if (canUseFeature('import')): ?>
                    <form method="post" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import_json">
                        <div class="form-group">
                            <label>Импорт JSON в таблицу</label>
                            <select name="import_table" required>
                                <option value="">-- Выберите таблицу --</option>
                                <?php foreach ($tables_list as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="file-input-wrapper" style="margin-top:8px;">
                            <label>Файл JSON:</label>
                            <input type="file" name="import_file" accept=".json" required>
                        </div>
                        <?php if ($import_error): ?>
                        <div class="status-box status-error"><?= htmlspecialchars($import_error) ?></div>
                        <?php endif; ?>
                        <?php if ($import_success): ?>
                        <div class="status-box status-success"><?= htmlspecialchars($import_success) ?></div>
                        <?php endif; ?>
                        <button type="submit" class="modern-btn" style="background:#2ecc71;margin-top:8px;">Импортировать</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (canUseFeature('backup')): ?>
            <div class="dash-card">
                <div class="dash-header"><h3>💾 Резервное копирование</h3></div>
                <p style="color:#555;font-size:.9rem;margin-bottom:16px;">
                    Создайте полную резервную копию базы данных в формате SQL.
                </p>
                <form method="post" action="create_backup.php">
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="backup-btn" style="width:100%;padding:10px;">
                        Создать бэкап БД
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php else: ?>
<!-- ===================== ФОРМА ВХОДА ===================== -->
<div class="main-content login-wrapper">
    <div class="login-panel">
        <div class="login-header">
            <h2>Вход в систему</h2>
            <div class="site-logo">
                <img src="images/rib.jpg" alt="Лого">
            </div>
            <p>Введите ваши учётные данные</p>
        </div>
        <form method="post" action="index.php">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="login" required placeholder="Ваш логин">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <div style="position:relative;">
                    <input type="password" id="password" name="password" required
                           placeholder="••••••••" style="padding-right:42px;">
                    <button type="button" id="toggle-password"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                   background:none;border:none;cursor:pointer;color:#888;">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="captcha-section">
                <label>Проверка безопасности</label>
                <p style="font-size:.85rem;color:#555;margin:6px 0 10px;">
                    Перетащите фрагменты пазла в порядке 1 → 2 → 3 → 4
                </p>
                <div class="puzzle-container">
                    <?php foreach ($captcha_data['current_order'] as $pos): ?>
                    <div class="puzzle-piece" data-piece="<?= $pos ?>">
                        <img src="captcha-images/<?= $pos ?>.png" alt="Пазл <?= $pos ?>"
                             style="max-width:100%;height:auto;">
                        <div class="puzzle-hint" style="font-size:.75rem;text-align:center;margin-top:4px;">
                            Перетащи меня
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="puzzle_order" id="puzzle_order"
                       value="<?= json_encode($captcha_data['current_order']) ?>">
                <div style="display:flex;gap:10px;margin-top:10px;">
                    <button type="button" id="reset-puzzle"   class="puzzle-btn">Сбросить</button>
                    <button type="button" id="shuffle-puzzle" class="puzzle-btn">Перемешать</button>
                </div>
            </div>

            <?php if ($error_message): ?>
            <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <button type="submit" class="add-btn"
                    style="width:100%;padding:12px;font-size:1rem;margin-top:10px;">
                Войти в систему
            </button>
            <div style="text-align:center;color:#777;margin:12px 0;font-size:.9rem;">или</div>
            <a href="catalog.php"
               style="display:block;text-align:center;text-decoration:none;padding:10px;
                      background:#f1f3f5;border-radius:8px;color:#555;font-weight:500;">
                Продолжить как гость
            </a>
        </form>
    </div>
</div>
<?php endif; ?>

</div><!-- /.container -->

<script>
(function () {
    function initPuzzle() {
        const container = document.querySelector('.puzzle-container');
        if (!container) return;

        function updateOrder() {
            const pieces = container.querySelectorAll('.puzzle-piece');
            const order  = Array.from(pieces).map(p => parseInt(p.dataset.piece));
            document.getElementById('puzzle_order').value = JSON.stringify(order);
        }

        function swap(a, b) {
            const ph = document.createComment('ph');
            container.insertBefore(ph, a);
            container.insertBefore(a, b);
            container.insertBefore(b, ph);
            ph.remove();
            updateOrder();
        }

        container.querySelectorAll('.puzzle-piece').forEach(piece => {
            piece.setAttribute('draggable', 'true');
            piece.addEventListener('dragstart', e => {
                e.dataTransfer.setData('text/plain', piece.dataset.piece);
                piece.style.opacity = '.5';
            });
            piece.addEventListener('dragend',  () => piece.style.opacity = '1');
            piece.addEventListener('dragover', e => e.preventDefault());
            piece.addEventListener('drop', e => {
                e.preventDefault();
                const src = container.querySelector('[data-piece="' + e.dataTransfer.getData('text/plain') + '"]');
                const tgt = e.target.closest('.puzzle-piece');
                if (src && tgt && src !== tgt) swap(src, tgt);
            });
        });

        document.getElementById('reset-puzzle')?.addEventListener('click', () => {
            const pieces = Array.from(container.querySelectorAll('.puzzle-piece'));
            pieces.sort((a, b) => +a.dataset.piece - +b.dataset.piece);
            pieces.forEach(p => container.appendChild(p));
            updateOrder();
        });

        document.getElementById('shuffle-puzzle')?.addEventListener('click', () => {
            const pieces = Array.from(container.querySelectorAll('.puzzle-piece'));
            for (let i = pieces.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [pieces[i], pieces[j]] = [pieces[j], pieces[i]];
            }
            pieces.forEach(p => container.appendChild(p));
            updateOrder();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initPuzzle();
        const btn = document.getElementById('toggle-password');
        if (btn) {
            const inp  = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            const open   = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            const closed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>'
                         + '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>'
                         + '<line x1="1" y1="1" x2="23" y2="23"/>';
            btn.addEventListener('click', () => {
                const show = inp.type === 'password';
                inp.type   = show ? 'text' : 'password';
                icon.innerHTML = show ? closed : open;
            });
        }
    });
})();
</script>
</body>
</html>
<?php ob_end_flush(); ?>