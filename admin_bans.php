<?php
session_start();
include 'db.php';
include 'permissions.php';

// Доступ только для администраторов (или тех, у кого есть право bans)
if (!canUseFeature('bans')) {
    header("Location: index.php");
    exit();
}

// Функция для разблокировки пользователя
function unbanUser($login, $conn) {
    $login = $conn->real_escape_string($login);
    $conn->query("DELETE FROM banned_users WHERE login = '$login'");
    $conn->query("DELETE FROM failed_logins WHERE login = '$login'");
    $conn->query("DELETE FROM failed_captcha_attempts WHERE login = '$login'");
    return !$conn->errno;
}

// Разблокировка по GET-параметру
if (isset($_GET['unban'])) {
    $login_to_unban = $_GET['unban'];
    if (unbanUser($login_to_unban, $conn)) {
        $success_message = "Пользователь $login_to_unban разблокирован.";
    } else {
        $error_message = "Ошибка при разблокировке пользователя.";
    }
}

// Получаем ВСЕ активные блокировки (banned_until IS NULL) – бессрочные
$banned_users = [];
$banned_result = $conn->query("SELECT *, (banned_until IS NULL OR banned_until > NOW()) as is_active 
                               FROM banned_users 
                               WHERE banned_until IS NULL 
                               ORDER BY banned_at DESC");
if ($banned_result) {
    $banned_users = $banned_result->fetch_all(MYSQLI_ASSOC);
}

// Статистика неудачных попыток (для информации)
$failed_logins = [];
$failed_logins_result = $conn->query("SELECT login, COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
                                      FROM failed_logins 
                                      WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR) 
                                      GROUP BY login ORDER BY attempts DESC");
if ($failed_logins_result) {
    $failed_logins = $failed_logins_result->fetch_all(MYSQLI_ASSOC);
}

$failed_captcha = [];
$failed_captcha_result = $conn->query("SELECT login, COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
                                       FROM failed_captcha_attempts 
                                       WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR) 
                                       GROUP BY login ORDER BY attempts DESC");
if ($failed_captcha_result) {
    $failed_captcha = $failed_captcha_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление блокировками</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="main-content">
            <header>
                <div class="header-content">
                    <a href="index.php" class="back-link" style="display:inline-block; margin-bottom:10px;">← На главную</a>
                    <h1>Управление блокировками пользователей</h1>
                </div>
            </header>
            <main>
                <?php if (isset($success_message)): ?>
                    <div class="success-message" style="background:#d4edda; color:#155724; padding:10px; border-radius:6px; margin-bottom:15px;">
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($error_message)): ?>
                    <div class="error-message" style="background:#f8d7da; color:#721c24; padding:10px; border-radius:6px; margin-bottom:15px;">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="compact-table-wrapper">
                    <h2>Заблокированные пользователи (бессрочно)</h2>
                    <div class="table-scroll-container">
                        <table class="compact-table">
                            <thead>
                                <tr>
                                    <th>Логин</th>
                                    <th>Дата блокировки</th>
                                    <th>Тип блокировки</th>
                                    <th>Причина</th>
                                    <th>Статус</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($banned_users)): ?>
                                    <?php foreach ($banned_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['login']); ?></td>
                                            <td><?php echo htmlspecialchars($user['banned_at']); ?></td>
                                            <td><?php echo $user['ban_type'] == 'captcha' ? 'Капча' : 'Логин'; ?></td>
                                            <td><?php echo htmlspecialchars($user['reason']); ?></td>
                                            <td><span style="color:#c0392b; font-weight:600;">🔒 Заблокирован</span></td>
                                            <td class="actions">
                                                <a href="admin_bans.php?unban=<?php echo urlencode($user['login']); ?>"
                                                   class="edit-btn"
                                                   onclick="return confirm('Разблокировать пользователя <?php echo htmlspecialchars($user['login']); ?>?');">
                                                   Разблокировать
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6">Нет активных блокировок</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="compact-table-wrapper">
                    <h2>Неудачные попытки входа (последний час)</h2>
                    <div class="table-scroll-container">
                        <table class="compact-table">
                            <thead>
                                <tr><th>Логин</th><th>Количество попыток</th><th>Последняя попытка</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($failed_logins)): ?>
                                    <?php foreach ($failed_logins as $attempt): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($attempt['login']); ?></td>
                                            <td><?php echo $attempt['attempts']; ?></td>
                                            <td><?php echo htmlspecialchars($attempt['last_attempt']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3">Нет неудачных попыток входа</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="compact-table-wrapper">
                    <h2>Неудачные попытки капчи (последний час)</h2>
                    <div class="table-scroll-container">
                        <table class="compact-table">
                            <thead><tr><th>Логин</th><th>Количество попыток</th><th>Последняя попытка</th></tr></thead>
                            <tbody>
                                <?php if (!empty($failed_captcha)): ?>
                                    <?php foreach ($failed_captcha as $attempt): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($attempt['login']); ?></td>
                                            <td><?php echo $attempt['attempts']; ?></td>
                                            <td><?php echo htmlspecialchars($attempt['last_attempt']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3">Нет неудачных попыток капчи</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>