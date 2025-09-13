<?php
// index.php - Painel de controle para gerenciar servidor Minecraft

// --- Configurações ---
$agent_url = "http://localhost:5000";
$version = "v1.0.1";

// --- Lógica de Carregamento de Idioma ---
$default_lang = "pt"; // Idioma padrão
$lang_code = $_GET['lang'] ?? $default_lang;

$lang_file = "lang/{$lang_code}.json";

// Verifica se o arquivo de idioma existe, caso contrário, usa o padrão.
if (!file_exists($lang_file)) {
    $lang_code = $default_lang;
    $lang_file = "lang/{$lang_code}.json";
}

// Carrega o conteúdo do arquivo de idioma.
$translations = json_decode(file_get_contents($lang_file), true);

// --- Funções de comunicação com o agente Python ---
function callPythonAgent($endpoint, $method = 'GET', $data = []) {
    global $agent_url;
    global $translations;

    $url = $agent_url . $endpoint;
    $options = [
        'http' => [
            'method'        => $method,
            'header'        => 'Content-type: application/json',
            'content'       => json_encode($data),
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        $error = error_get_last();
        return ['success' => false, 'error' => $translations['error_agent_connect'] . ($error ? $error['message'] : 'Erro desconhecido')];
    }

    $response_data = json_decode($result, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Resposta inválida do agente Python: ' . $result];
    }
    
    $status_code = 500;
    if (isset($http_response_header) && is_array($http_response_header)) {
        preg_match('/HTTP\/[\d\.]+\s*(\d+)/', $http_response_header[0], $matches);
        $status_code = isset($matches[1]) ? (int)$matches[1] : 500;
    }

    if ($status_code >= 400) {
        return ['success' => false, 'error' => $response_data['error'] ?? 'Erro HTTP: ' . $status_code, 'status_code' => $status_code];
    }

    return $response_data;
}

// --- Lógica de processamento dos formulários ---
$minecraft_output = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["minecraft_command_action"])) {
        $action = $_POST["minecraft_command_action"];
        
        $actions = [
            "start" => ["endpoint" => "/start_server", "method" => "POST", "success_msg" => $translations['output_success_start']],
            "stop" => ["endpoint" => "/stop_server", "method" => "POST", "success_msg" => $translations['output_success_stop']],
            "status" => ["endpoint" => "/server_status", "method" => "GET"],
            "rcon" => ["endpoint" => "/rcon_command", "method" => "POST"],
        ];

        if (array_key_exists($action, $actions)) {
            $config = $actions[$action];
            $data = ($action === 'rcon') ? ['command' => $_POST["rcon_command_input"] ?? ''] : [];
            
            $response = callPythonAgent($config['endpoint'], $config['method'], $data);
            
            if (isset($response['success']) && $response['success']) {
                if ($action === 'status') {
                    $minecraft_output = $translations['output_status_running_prefix'] . ($response['status'] ?? 'N/A') . "<hr><center>" . $translations['output_status_running_status'] .
                        (isset($response['is_running']) && $response['is_running']
                            ? "<span style='color: #00ff00;'>{$translations['output_status_yes']}</span><br>{$translations['output_status_ram_usage']}<strong>" . ($response['ram_usage'] ?? 'N/A') . "</strong><hr>"
                            : "<span style='color: red;'>{$translations['output_status_no']}</span><hr>") . "</center>";
                } elseif ($action === 'rcon') {
                    if (empty($data['command'])) {
                        $minecraft_output = $translations['error_rcon_empty'];
                    } else {
                        $minecraft_output = $translations['output_rcon_sent'] . "'{$data['command']}'<br>" . $translations['output_rcon_response'] . htmlspecialchars($response['response'] ?? 'N/A');
                    }
                } else {
                    $minecraft_output = $config['success_msg'];
                }
            } else {
                $minecraft_output = $translations['error_action'] . $action . "': " . ($response['error'] ?? 'Erro desconhecido');
            }
        } else {
            $minecraft_output = $translations['error_unknown_action'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="./favicon.png" type="image/png">
    <title><?= htmlspecialchars($translations['page_title']) ?></title>
    <link rel="stylesheet" href="style.css" />
</head>
<body>
    <div class="lang-selector">
        <a href="?lang=pt">PT</a> |
        <a href="?lang=en">EN</a>
    </div>

    <div class="imagem-container">
        <img src="Minecraft.png" alt="Logo do Minecraft">
    </div>

    <div class="container">
        <h2><?= htmlspecialchars($translations['main_heading']) ?></h2>
        <span class="version-info"><?= htmlspecialchars($translations['version']) ?>: <?= htmlspecialchars($version) ?></span>
        
        <form method="POST">
            <div>
                <button type="submit" name="minecraft_command_action" value="start"><?= htmlspecialchars($translations['start_button']) ?></button>
            </div>
            <div>
                <button type="submit" name="minecraft_command_action" value="stop"><?= htmlspecialchars($translations['stop_button']) ?></button>
            </div>
            <div>
                <button type="submit" name="minecraft_command_action" value="status"><?= htmlspecialchars($translations['status_button']) ?></button>
            </div>
        </form>

        <hr style="border-color:#555; margin: 20px 0;">

        <h3><?= htmlspecialchars($translations['rcon_heading']) ?></h3>
        <form method="POST" id="rconForm">
            <input type="text" name="rcon_command_input" placeholder="<?= htmlspecialchars($translations['rcon_placeholder']) ?>" style="width: 100%;">
            <button type="submit" name="minecraft_command_action" value="rcon"><?= htmlspecialchars($translations['rcon_button']) ?></button>
        </form>

        <div class="output" style="margin-top: 20px;">
            <?php
            if (!empty($minecraft_output)) {
                echo $minecraft_output;
            } else {
                echo htmlspecialchars($translations['output_default']);
            }
            ?>
        </div>
        
    </div>
</body>
</html>