<?php
session_start();
require_once 'db.php';
require_once 'permissions.php';

define('SIMULATOR_URL', 'http://localhost:4444/TransferSimulator/');
$result = null;
$generatedValue = null;
$type = null;
$isValid = false;
$errorMessage = "";

$testTypes = [
    'fullName'     => ['label' => 'Проверка ФИО',      'param' => 'fullName',      'mock' => 'Тестовый+Запрос+Иванович', 'expected' => 'Только кириллица, пробелы и дефисы'],
    'snils'        => ['label' => 'Проверка СНИЛС',    'param' => 'snils',         'mock' => '112-233-445+95',           'expected' => 'Формат XXX-XXX-XXX XX'],
    'inn'          => ['label' => 'Проверка ИНН',      'param' => 'inn',           'mock' => '770101001',                'expected' => '10 или 12 цифр'],
    'mobilePhone'  => ['label' => 'Проверка Телефона', 'param' => 'mobilePhone',   'mock' => '%2B79991112233',           'normalize' => 'phone',    'expected' => '+7XXXXXXXXXX (10 цифр после 7)'],
    'identityCard' => ['label' => 'Проверка Паспорта', 'param' => 'identityCard',  'mock' => '4508+123456',              'normalize' => 'passport', 'expected' => 'XXXX XXXXXX (4 цифры, пробел, 6 цифр)'],
    'email'        => ['label' => 'Проверка Email',    'param' => 'email',         'mock' => 'test@example.com',         'expected' => 'Корректный email-адрес']
];

// --- Экспорт .doc ---
if (isset($_GET['export_doc']) && isset($_SESSION['last_validation'])) {
    $data     = $_SESSION['last_validation'];
    $typeKey  = $data['type'];
    $config   = $testTypes[$typeKey];
    $resultText = $data['isValid'] ? 'Успешно' : 'Не успешно: ' . $data['errorMessage'];

    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="validation_report.doc"');
    echo '<html><head><meta charset="UTF-8"><title>Отчёт о валидации</title>
    <style>body{font-family:Arial,sans-serif;margin:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #000;padding:8px;}</style>
    </head><body>
        <h2>Результат валидации данных</h2>
        <p><strong>Дата проверки:</strong> ' . date('d.m.Y H:i:s') . '</p>
        <table>
        <thead><tr><th>Действие</th><th>Ожидаемый результат</th><th>Результат</th></tr></thead>
        <tbody>
        <tr><td>' . htmlspecialchars($config['label']) . '</td><td>' . htmlspecialchars($config['expected']) . '</td><td>' . htmlspecialchars($resultText) . '</td></tr>
        <tr><td colspan="2"><strong>Полученное значение:</strong></td><td>' . htmlspecialchars($data['generatedValue']) . '</td></tr>
        </tbody>
        </table>
    </body></html>';
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$save_result_message = '';
$save_result_success = false;

// --- Обработка: Отправить результат теста в test_results ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_test_result'])) {
    $csrf = $_POST['csrf'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $save_result_message = '❌ Ошибка безопасности: неверный CSRF токен.';
    } elseif (!isset($_SESSION['last_validation'])) {
        $save_result_message = '❌ Нет данных для сохранения. Сначала выполните проверку.';
    } else {
        $v          = $_SESSION['last_validation'];
        $typeKey    = $v['type'];
        $config     = $testTypes[$typeKey];
        $typeLabel  = $config['label'];
        $expected   = $config['expected'];
        $valValue   = $v['generatedValue'];
        $valResult  = $v['isValid'] ? 'Успешно' : 'Не успешно';
        $valError   = $v['isValid'] ? null : $v['errorMessage'];

        // Убедимся что таблица test_results существует
        $conn->query("CREATE TABLE IF NOT EXISTS `test_results` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `type`       VARCHAR(50)  NOT NULL,
            `type_label` VARCHAR(100) NOT NULL,
            `value`      VARCHAR(255) NOT NULL,
            `expected`   VARCHAR(255) NOT NULL,
            `result`     VARCHAR(20)  NOT NULL,
            `error_msg`  VARCHAR(255) DEFAULT NULL,
            `checked_at` DATETIME     DEFAULT NOW()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $conn->prepare(
            "INSERT INTO `test_results` (`type`, `type_label`, `value`, `expected`, `result`, `error_msg`)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if ($stmt) {
            $stmt->bind_param('ssssss', $typeKey, $typeLabel, $valValue, $expected, $valResult, $valError);
            if ($stmt->execute()) {
                $save_result_success = true;
                $save_result_message = '✅ Результат теста успешно сохранён в таблицу test_results.';
            } else {
                $save_result_message = '❌ Ошибка сохранения: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $save_result_message = '❌ Ошибка подготовки запроса: ' . $conn->error;
        }
    }
}

// --- Восстановить состояние валидации из сессии после POST сохранения ---
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_test_result'])
    && isset($_SESSION['last_validation']))
{
    $saved          = $_SESSION['last_validation'];
    $type           = $saved['type'];
    $generatedValue = $saved['generatedValue'];
    $isValid        = $saved['isValid'];
    $errorMessage   = $saved['errorMessage'];
    $result         = true;
}

// --- Основная обработка: Сгенерировать и проверить ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'])) {
    $type = $_POST['type'];
    if (array_key_exists($type, $testTypes)) {
        $config   = $testTypes[$type];
        $url      = SIMULATOR_URL . $type . "?" . $config['param'] . "=" . $config['mock'];
        $response = @file_get_contents($url);

        if ($response !== false) {
            $data = json_decode($response, true);
            $raw  = trim($data['value'] ?? '');

            if ($raw === '') {
                $isValid        = false;
                $errorMessage   = "Симулятор вернул пустое значение.";
                $generatedValue = '';
                $result         = true;
            } else {
                // Убираем дублирование (баг симулятора)
                $halfLen = mb_strlen($raw) / 2;
                if ($halfLen == floor($halfLen) && mb_substr($raw, 0, $halfLen) === mb_substr($raw, $halfLen)) {
                    $raw = mb_substr($raw, 0, $halfLen);
                }

                if ($type === 'mobilePhone') {
                    $raw = urldecode($raw);
                    $raw = preg_replace('/[^\d+]/', '', $raw);
                    if (preg_match('/^7\d{10}$/', $raw)) $raw = '+' . $raw;
                }
                if ($type === 'identityCard') {
                    $raw    = urldecode($raw);
                    $raw    = str_replace('+', ' ', $raw);
                    $digits = preg_replace('/\D/', '', $raw);
                    if (strlen($digits) === 10) $raw = substr($digits, 0, 4) . ' ' . substr($digits, 4, 6);
                }

                $generatedValue = $raw;

                switch ($type) {
                    case 'fullName':
                        $isValid      = (bool)preg_match('/^[А-Яа-яЁё\s-]+$/u', $generatedValue);
                        $errorMessage = "ФИО содержит запрещенные символы.";
                        break;
                    case 'snils':
                        $isValid      = (bool)preg_match('/^\d{3}-\d{3}-\d{3}\s\d{2}$/', $generatedValue);
                        $errorMessage = "Формат СНИЛС нарушен.";
                        break;
                    case 'inn':
                        $isValid      = (bool)preg_match('/^(\d{10}|\d{12})$/', $generatedValue);
                        $errorMessage = "ИНН должен содержать 10 или 12 цифр.";
                        break;
                    case 'mobilePhone':
                        $isValid      = (bool)preg_match('/^(\+7|8)\d{10}$/', $generatedValue);
                        $errorMessage = "Неверный формат телефона (+7XXXXXXXXXX).";
                        break;
                    case 'identityCard':
                        $isValid      = (bool)preg_match('/^\d{4}\s\d{6}$/', $generatedValue);
                        $errorMessage = "Формат паспорта нарушен (XXXX XXXXXX).";
                        break;
                    case 'email':
                        $isValid      = filter_var($generatedValue, FILTER_VALIDATE_EMAIL) !== false;
                        $errorMessage = "Невалидный email-адрес.";
                        break;
                }
                $result = true;
            }

            $_SESSION['last_validation'] = [
                'type'           => $type,
                'generatedValue' => $generatedValue,
                'isValid'        => $isValid,
                'errorMessage'   => $isValid ? '' : $errorMessage,
            ];
        } else {
            $result = false;
        }
    }
}

// --- История результатов тестов ---
$all_tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_array()) $all_tables[] = $row[0];
$test_history = [];
if (in_array('test_results', $all_tables)) {
    $hRes = $conn->query("SELECT * FROM `test_results` ORDER BY checked_at DESC LIMIT 20");
    if ($hRes) while ($row = $hRes->fetch_assoc()) $test_history[] = $row;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Валидатор Данных</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        .validator-container { max-width: 860px; margin: 40px auto; padding: 0 20px; }
        .validator-card { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #eaeaea; margin-bottom: 24px; }
        .validator-header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .validator-header h1 { font-size: 1.8rem; color: #2c3e50; margin: 0; }
        .form-group select, .form-group input { padding: 12px; font-size: 1rem; border-radius: 8px; width: 100%; }
        .result-area { margin-top: 30px; animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .generated-value-box { background: #f8f9fa; border: 1px dashed #ccc; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px; }
        .generated-value-box span { font-family: monospace; font-size: 1.2rem; color: #e67e22; font-weight: bold; word-break: break-all; }
        .status-alert { padding: 15px 20px; border-radius: 8px; display: flex; align-items: center; gap: 15px; }
        .status-alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-alert.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-alert.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .status-icon { font-size: 1.5rem; }
        .nav-back { display: inline-block; margin-bottom: 20px; color: #3a6ea5; text-decoration: none; font-weight: 500; }
        .export-btn  { margin-top: 15px; background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; display: block; text-align: center; text-decoration: none; }
        .insert-btn  { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 15px; }
        .insert-btn:hover { background: #0056b3; }
        .save-test-btn { background: #6f42c1; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: bold; width: 100%; margin-top: 12px; font-size: 1rem; transition: background 0.2s; }
        .save-test-btn:hover { background: #5a32a3; }
        .form-inline { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px; }
        .form-inline .form-group { flex: 1; }
        .message { padding: 12px; border-radius: 8px; margin-top: 15px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .nav-link { color: #007bff; text-decoration: none; font-weight: 500; }
        .nav-link:hover { text-decoration: underline; }

        /* ========= ГРОМКОЕ ПРЕДУПРЕЖДЕНИЕ ========= */
        .loud-warning {
            background: #fff3cd;
            border-left: 8px solid #dc3545;
            border-radius: 12px;
            padding: 18px 22px;
            margin: 20px 0;
            box-shadow: 0 4px 14px rgba(220, 53, 69, 0.25);
            animation: pulseWarning 0.8s ease-in-out 2;
        }
        .loud-warning .warning-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #a71d2a;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .loud-warning .warning-title span {
            font-size: 2rem;
        }
        .loud-warning .warning-text {
            font-size: 1rem;
            color: #856404;
            margin-left: 32px;
        }
        @keyframes pulseWarning {
            0% { background-color: #fff3cd; border-left-color: #dc3545; transform: scale(1); }
            50% { background-color: #ffe6b3; border-left-color: #ff0000; transform: scale(1.01); }
            100% { background-color: #fff3cd; border-left-color: #dc3545; transform: scale(1); }
        }

        /* Кнопка сохранения в опасном режиме */
        .save-test-btn.danger-mode {
            background: #dc3545;
            box-shadow: 0 0 8px rgba(220,53,69,0.6);
            transition: all 0.2s;
        }
        .save-test-btn.danger-mode:hover {
            background: #bb2d3b;
            transform: scale(1.02);
        }

        /* Усиленный блок ошибки валидации */
        .status-alert.error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            font-weight: bold;
            animation: shakeError 0.4s ease-in-out;
        }
        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        .save-success-banner {
            display: flex; align-items: flex-start; gap: 14px;
            background: linear-gradient(135deg, #ede7f6, #d1c4e9);
            border: 1px solid #6f42c1; border-left: 5px solid #6f42c1;
            border-radius: 10px; padding: 16px 20px; margin-top: 12px;
            animation: slideIn 0.4s ease;
        }
        .save-success-banner .s-icon { font-size: 2rem; line-height: 1; }
        .save-success-banner .s-body { display: flex; flex-direction: column; gap: 6px; }
        .save-success-banner .s-body strong { color: #4a235a; font-size: 1rem; }
        .save-success-banner .s-body span   { color: #4a235a; font-size: 0.9rem; }
        .goto-history-link {
            display: inline-block; margin-top: 6px; background: #6f42c1; color: #fff;
            text-decoration: none; padding: 7px 16px; border-radius: 6px;
            font-weight: 600; font-size: 0.9rem; transition: background 0.2s; width: fit-content;
        }
        .goto-history-link:hover { background: #5a32a3; }

        .history-card h2 { font-size: 1.3rem; color: #2c3e50; margin: 0 0 16px; }
        .history-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .history-table th { background: #3a6ea5; color: #fff; padding: 10px 12px; text-align: left; }
        .history-table td { padding: 9px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .history-table tr:hover td { background: #f8f9fa; }
        .badge-ok  { display:inline-block; background:#28a745; color:#fff; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:600; }
        .badge-err { display:inline-block; background:#dc3545; color:#fff; padding:3px 10px; border-radius:20px; font-size:.8rem; font-weight:600; }
        .history-empty { text-align:center; color:#999; padding:24px; font-style:italic; }
        .section-divider { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="main-content" style="width:100%; max-width:100%;">
        <div class="validator-container">
            <a href="index.php" class="nav-back">← Вернуться в панель управления</a>

            <!-- КАРТОЧКА ВАЛИДАТОРА -->
            <div class="validator-card">
                <div class="validator-header">
                    <h1>Панель Валидации Данных</h1>
                    <p>Генерация и проверка через TransferSimulator + сохранение результатов</p>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="type">Выберите тип данных для проверки:</label>
                        <select name="type" id="type" required>
                            <?php foreach ($testTypes as $key => $values): ?>
                                <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>>
                                    <?= $values['label'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="add-btn" style="width:100%; padding:12px; font-size:1rem; margin-top:10px;">
                        🔍 Сгенерировать и Проверить
                    </button>
                </form>

                <?php if ($result === true): ?>
                <div class="result-area">
                    <div class="generated-value-box">
                        <div style="font-size:0.85rem; color:#777; margin-bottom:5px;">Получено от симулятора:</div>
                        <span><?= htmlspecialchars($generatedValue ?: '(пусто)') ?></span>
                    </div>

                    <?php if ($isValid): ?>
                        <div class="status-alert success">
                            <div class="status-icon">✔</div>
                            <div>
                                <strong>Данные корректны!</strong><br>
                                <span style="font-size:0.9rem;">Строка успешно прошла проверку.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Усиленный блок ошибки -->
                        <div class="status-alert error">
                            <div class="status-icon">❌</div>
                            <div>
                                <strong>Ошибка валидации!</strong><br>
                                <span><?= htmlspecialchars($errorMessage) ?></span>
                            </div>
                        </div>
                        <!-- ГРОМКОЕ ПРЕДУПРЕЖДЕНИЕ -->
                        <div class="loud-warning">
                            <div class="warning-title">
                                <span>⚠️⚠️⚠️</span> ВНИМАНИЕ!
                            </div>
                            <div class="warning-text">
                                <strong>Вы пытаетесь сохранить НЕВАЛИДНЫЕ данные!</strong><br>
                                Запись в таблицу <code>test_results</code> может быть некорректной или повредить целостность данных.<br>
                                Пожалуйста, убедитесь, что вы осознаёте риск, прежде чем нажимать кнопку сохранения.
                            </div>
                        </div>
                    <?php endif; ?>

                    <hr class="section-divider">

                    <form method="POST" id="saveTestForm">
                        <input type="hidden" name="save_test_result" value="1">
                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="save-test-btn <?= !$isValid ? 'danger-mode' : '' ?>" data-invalid="<?= $isValid ? '0' : '1' ?>">
                            <?= !$isValid ? '⚠️ ' : '📋 ' ?>Отправить результат теста
                        </button>
                    </form>

                    <?php if ($save_result_message): ?>
                        <?php if ($save_result_success): ?>
                        <div class="save-success-banner">
                            <div class="s-icon">📋</div>
                            <div class="s-body">
                                <strong>Результат сохранён!</strong>
                                <span>Запись добавлена в таблицу <strong>test_results</strong>.</span>
                                <a href="table_view.php?table=test_results" class="goto-history-link">
                                    🔗 Перейти к таблице test_results
                                </a>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="message error"><?= htmlspecialchars($save_result_message) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <hr class="section-divider">
                    <a href="?export_doc=1" class="export-btn">📄 Скачать отчёт в .doc</a>
                </div>

                <?php elseif ($result === false): ?>
                <div class="result-area">
                    <div class="status-alert warning">
                        <div class="status-icon">⚠️</div>
                        <div>
                            <strong>Симулятор недоступен</strong><br>
                            <span>Не удалось подключиться к TransferSimulator на порту 4444. Проверьте, запущен ли сервис.</span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- КАРТОЧКА ИСТОРИИ ТЕСТОВ -->
            <div class="validator-card history-card">
                <h2>📊 История результатов тестов
                    <?php if (!empty($test_history)): ?>
                    <a href="table_view.php?table=test_results" style="font-size:0.85rem; font-weight:400; margin-left:10px; color:#3a6ea5; text-decoration:none;">
                        Открыть полную таблицу →
                    </a>
                    <?php endif; ?>
                </h2>

                <?php if (empty($test_history)): ?>
                    <div class="history-empty">Результатов пока нет. Выполните проверку и нажмите «Отправить результат теста».</div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="history-table">
                        <thead>
                            <tr><th>#</th><th>Тип проверки</th><th>Значение</th><th>Ожидаемый формат</th><th>Результат</th><th>Причина ошибки</th><th>Дата</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($test_history as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['type_label']) ?></td>
                                <td><code><?= htmlspecialchars($row['value']) ?></code></td>
                                <td style="color:#555; font-size:0.85rem;"><?= htmlspecialchars($row['expected']) ?></td>
                                <td>
                                    <?php if ($row['result'] === 'Успешно'): ?>
                                        <span class="badge-ok">✔ Успешно</span>
                                    <?php else: ?>
                                        <span class="badge-err">✗ Не успешно</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:#888; font-size:0.85rem;"><?= htmlspecialchars($row['error_msg'] ?? '—') ?></td>
                                <td style="white-space:nowrap; color:#888; font-size:0.85rem;"><?= date('d.m.Y H:i', strtotime($row['checked_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
    // Предупреждение при попытке сохранить невалидные данные (дополнительная защита)
    const saveBtn = document.querySelector('.save-test-btn[data-invalid="1"]');
    if (saveBtn) {
        saveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const confirmed = confirm(
                '⚠️ ВНИМАНИЕ!\n\n' +
                'Полученные данные НЕ ПРОШЛИ проверку формата.\n' +
                'Добавление таких данных в базу может привести к ошибкам или нарушению целостности.\n\n' +
                'Всё равно продолжить?'
            );
            if (confirmed) {
                document.getElementById('saveTestForm').submit();
            }
        });
    }
</script>
</body>
</html>