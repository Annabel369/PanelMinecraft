<?php
// PHP para interagir com o agente Python e a classe Rcon.php

// Define a exibição de erros para fins de depuração.
// NÃO use isso em um ambiente de produção.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclui a classe Rcon.
// Certifique-se de que o arquivo 'rcon.php' está no mesmo diretório.
require_once 'rcon.php';

// Configurações do seu servidor Minecraft RCON (para uso direto com Rcon.php)
// ATENÇÃO: Essas configurações são usadas APENAS para os comandos RCON diretos,
// não para o agente Python, que tem suas próprias configurações de RCON.
$minecraft_rcon_host = '127.0.0.1';
$minecraft_rcon_port = 25575;
$minecraft_rcon_password = '12312sdafa134';
$rcon_timeout = 3;

// URL base do seu agente Python (para Start/Stop/Status)
$agent_url = "http://localhost:5000";

// --- Funções para interagir com o agente Python ---
function callPythonAgent($endpoint, $method = 'GET', $data = []) {
    global $agent_url;
    $url = $agent_url . $endpoint;
    $options = [
        'http' => [
            'method'        => $method,
            'header'        => 'Content-type: application/json',
            'content'       => json_encode($data),
            'ignore_errors' => true // Captura erros HTTP (4xx, 5xx)
        ]
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        $error = error_get_last();
        return ['success' => false, 'error' => 'Falha na conexão com o agente Python: ' . ($error ? $error['message'] : 'Erro desconhecido')];
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

// --- Lógica de processamento no PHP ---
$minecraft_output = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["minecraft_command_action"])) {
        $action = $_POST["minecraft_command_action"];
        
        switch ($action) {
            case "start":
                $response = callPythonAgent('/start_server', 'POST');
                if (isset($response['success']) && $response['success']) {
                    $minecraft_output = "Servidor Minecraft:<br><hr><center> <font color=lime>iniciado com sucesso! </center></br></font><hr>" . ($response['stdout'] ?? '') . ($response['stderr'] ?? '');
                } else {
                    $minecraft_output = "Erro ao iniciar servidor: " . ($response['error'] ?? 'Erro desconhecido');
                }
                break;
            case "stop":
                $response = callPythonAgent('/stop_server', 'POST');
                if (isset($response['success']) && $response['success']) {
                    $minecraft_output = "Servidor Minecraft:<br><hr><center> <font color=red>parado com sucesso! </center></br></font><hr>" . ($response['stdout'] ?? '') . ($response['stderr'] ?? '');
                } else {
                    $minecraft_output = "Erro ao parar servidor: " . ($response['error'] ?? 'Erro desconhecido');
                }
                break;
            case "status":
                $response = callPythonAgent('/server_status', 'GET');
                if (isset($response['success']) && $response['success']) {
                    $is_running = $response['is_running'] ?? false;
                    $status_text = $response['status'] ?? 'N/A';
                    $ram_usage = $response['ram_usage'] ?? null;
                    $is_ready = $response['is_ready'] ?? null;

                    $minecraft_output = "Status do Servidor: " . $status_text . "<hr>";
                    $minecraft_output .= "Rodando: " . ($is_running ? "<span style='color: #00ff00;'>✔ Sim</span>" : "<span style='color: red;'>✘ Não</span>") . "<hr>";
                    
                    if ($ram_usage !== null) {
                        $minecraft_output .= "Uso de RAM: <span style='color:yellow;'>{$ram_usage}</span><hr>";
                    }

                    if ($is_ready !== null) {
                        $minecraft_output .= "Pronto (RCON): <span style='color:" . ($is_ready ? "lime" : "red") . ";'>" . ($is_ready ? "✔ Sim" : "✘ Não") . "</span><hr>";
                    }

                } else {
                    $minecraft_output = "Erro ao obter status: " . ($response['error'] ?? 'Erro desconhecido');
                }
                break;
            case "rcon":
                $command_to_send = $_GET["rcon_command_input"] ?? '';

                if (!empty($command_to_send)) {
                    $rcon = new Rcon($minecraft_rcon_host, $minecraft_rcon_port, $minecraft_rcon_password, $rcon_timeout);

                    if ($rcon->connect()) {
                        $response_rcon = $rcon->send_command($command_to_send); // Use um nome diferente para a variável de resposta
                        if ($response_rcon !== false) {
                            $minecraft_output = "Comando RCON enviado: '{$command_to_send}'<br>Resposta: " . htmlspecialchars($response_rcon);
                        } else {
                            $minecraft_output = "Erro ao enviar comando RCON: " . htmlspecialchars($rcon->get_response());
                        }
                        $rcon->disconnect();
                    } else {
                        $minecraft_output = "Falha ao conectar ao RCON: " . htmlspecialchars($rcon->get_response());
                    }
                } else {
                    $minecraft_output = "Por favor, digite um comando RCON para enviar.";
                }
                break;
            default:
                $minecraft_output = "Ação desconhecida.";
                break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Minecraft via Agente Python e RCON PHP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #222;
            color: #fff;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            width: 90%;
            max-width: 700px;
            margin-bottom: 20px;
            background: #333;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.4);
            box-sizing: border-box;
            position: relative;
            z-index: 1;
        }
        h2 {
            color: #0f0;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .output {
            background: #111;
            padding: 15px;
            border-radius: 5px;
            min-height: 100px;
            max-height: 250px;
            overflow-y: auto;
            text-align: left;
            margin-bottom: 15px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        input[type="text"], select, button {
            font-size: 1em;
            padding: 12px 15px;
            margin-top: 10px;
            border: 1px solid #555;
            border-radius: 5px;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }
        input[type="text"] {
            width: calc(100% - 20px);
        }
        select {
            width: 100%;
            background: #2a2a2a;
            color: #eee;
        }
        button {
            background: #0f0;
            color: #222;
            border: none;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
            text-transform: uppercase;
        }
        button:hover {
            background: #0c0;
            transform: translateY(-2px);
        }

        /* Estilos da Imagem Centralizada e Reduzida */
        .imagem-container {
            width: 90%;
            max-width: 400px;
            margin-top: 20px;
            text-align: center;
            box-sizing: border-box;
        }

        .imagem-container img {
            width: 80%;
            max-width: 250px;
            height: auto;
            display: block;
            margin: 0 auto;
            border-radius: 8px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.5);
        }

    </style>
</head>
<body>
    <div class="imagem-container">
        <img src="Minecraft.png" alt="Logo do Minecraft">
    </div>
    <div class="container">
        <h2>Gerenciar Minecraft</h2>
        <form method="POST">
            <div>
                <button type="submit" name="minecraft_command_action" value="start">Iniciar Servidor</button>
            </div>
            <div>
                <button type="submit" name="minecraft_command_action" value="stop">Parar Servidor</button>
            </div>
            <div>
                <button type="submit" name="minecraft_command_action" value="status">Ver Status</button>
            </div>
        </form>

        <hr style="border-color:#555; margin: 20px 0;">

        <h3>Comando RCON</h3>
        <form method="POST" id="rconForm">
            <input type="text" name="rcon_command_input" placeholder="Ex: say Hello World" style="width: 100%;">
            <button type="submit" name="minecraft_command_action" value="rcon">Executar Comando RCON</button>
        </form>

        <div class="output" style="margin-top: 20px;">
            <?php
            if (!empty($minecraft_output)) {
                echo $minecraft_output;
            } else {
                echo "Nenhum comando executado ainda.";
            }
            ?>
        </div>
        
    </div>
</body>
</html>