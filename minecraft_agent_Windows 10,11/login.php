<?php
// Inicia a sessão no início do arquivo para garantir que esteja disponível
session_start();

// Inclui a conexão com o banco de dados.
require_once 'db_connect.php'; 

// --- VARIÁVEIS DE SEGURANÇA ---
$max_login_attempts = 3;       // Número máximo de tentativas permitidas
$ban_duration_seconds = 300;   // Duração do banimento em segundos (5 minutos)
// -----------------------------

$error = '';

// --- Lógica de Carregamento de Idioma ---
$default_lang = "pt"; // Idioma padrão
$lang_code = $_GET['lang'] ?? $default_lang; 
$lang_file = __DIR__ . "/lang/{$lang_code}.json"; 

// Verifica se o arquivo de idioma existe. Se não, tenta um fallback.
if (!file_exists($lang_file)) {
    $lang_code = $default_lang;
    $lang_file = __DIR__ . "/lang/{$lang_code}.json";
    
    if (!file_exists($lang_file)) {
        $lang_code = "en";
        $lang_file = __DIR__ . "/lang/{$lang_code}.json";
    }
}

// === Lendo e decodificando o arquivo JSON para $lang ===
if (file_exists($lang_file)) {
    $lang = json_decode(file_get_contents($lang_file), true);
} else {
    // Fallback manual para evitar erros fatais
    $lang = [
        'login_error_message' => 'Usuário ou senha incorretos.',
        'login_ip_banned_message' => 'Seu IP foi temporariamente bloqueado por %d minutos devido a muitas tentativas falhas.',
        'title' => 'Login',
        'panel_title' => 'Admin Panel',
        'login_h2' => 'Sign In',
        'username_label' => 'Username',
        'password_label' => 'Password',
        'login_button' => 'Log In',
        'register_link' => 'Register',
        'lang_code' => $lang_code 
    ];
}
// Fim da Lógica de Carregamento de Idioma


// ----------------------------------------------------------------------------------
// --- BLOCO DE VERIFICAÇÃO DE BANIMENTO E INTERRUPÇÃO DO SCRIPT ---
// ----------------------------------------------------------------------------------
$user_ip = $_SERVER['REMOTE_ADDR']; 
$current_time = time();

$stmt_ban = $pdo->prepare("SELECT ban_expires_at FROM ip_bans WHERE ip_address = ?");
$stmt_ban->execute([$user_ip]);
$ban_info = $stmt_ban->fetch();

if ($ban_info && $ban_info['ban_expires_at'] > $current_time) {
    // Se o IP estiver banido
    $time_remaining = $ban_info['ban_expires_at'] - $current_time;
    $minutes_remaining = ceil($time_remaining / 60);
    
    // Define a mensagem de erro de banimento
    $ban_message = sprintf($lang['login_ip_banned_message'], $minutes_remaining);
    
    // HTML Simples: Exibe a mensagem de banimento e ENCERRA o script
    echo '<!DOCTYPE html><html><head><title>Bloqueado</title><style>
            body { background-color: #1a1a1a; color: white; display: flex; 
                   justify-content: center; align-items: center; height: 100vh; 
                   font-family: sans-serif; text-align: center; }
            h1 { color: #ff4d4d; }
          </style></head><body>';
    echo "<h1>❌ Você está banido</h1>";
    echo "<p>{$ban_message}</p>";
    echo '</body></html>';
    
    // Encerra a execução para que o formulário de login não seja exibido
    exit; 
} 
// Se o banimento expirou, remove o registro (limpeza)
else if ($ban_info) {
    $pdo->prepare("DELETE FROM ip_bans WHERE ip_address = ?")->execute([$user_ip]);
}
// FIM DO BLOCO DE INTERRUPÇÃO


// Verifica se a requisição é do tipo POST (se o formulário foi enviado)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // LOGIN BEM-SUCEDIDO
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $user['username'];
        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$user_ip]);
        header("Location: index.php");
        exit;
    } else {
        // LOGIN FALHOU
        
        // 2a. Registra a tentativa falha (ou incrementa)
        $stmt_attempt = $pdo->prepare("
            INSERT INTO login_attempts (ip_address, last_attempt_time, attempts_count)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                last_attempt_time = VALUES(last_attempt_time),
                attempts_count = attempts_count + 1
        ");
        $stmt_attempt->execute([$user_ip, $current_time]);

        // 2b. Recupera o novo número de tentativas
        $stmt_count = $pdo->prepare("SELECT attempts_count FROM login_attempts WHERE ip_address = ?");
        $stmt_count->execute([$user_ip]);
        $attempts_row = $stmt_count->fetch();
        $attempts_count = $attempts_row['attempts_count'] ?? 1;

        // 2c. Checa se atingiu o limite para banir
        if ($attempts_count >= $max_login_attempts) {
            $ban_expires_at = $current_time + $ban_duration_seconds;
            
            // BANIMENTO: Insere/Atualiza o IP na tabela de banidos
            $stmt_insert_ban = $pdo->prepare("
                INSERT INTO ip_bans (ip_address, ban_expires_at)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE ban_expires_at = VALUES(ban_expires_at)
            ");
            $stmt_insert_ban->execute([$user_ip, $ban_expires_at]);
            
            // Define a mensagem de erro de banimento (5 minutos)
            $minutes_remaining = ceil($ban_duration_seconds / 60);
            $error = sprintf($lang['login_ip_banned_message'], $minutes_remaining);
            
            // Limpa o registro de tentativas após o banimento
            $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$user_ip]);
            
        } else {
            // Define a mensagem de erro normal e informa quantas tentativas restam
            $remaining = $max_login_attempts - $attempts_count;
            $error = ($lang['login_error_message']) . " Você tem mais $remaining tentativas.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang['lang_code'] ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="./img/favicon.png" type="image/png" />
    <title><?= $lang['title'] ?></title>
    <link rel="stylesheet" href="style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS para o layout menor, como solicitado */
        .container {
            max-width: 400px;
            margin-top: 5rem;
            padding: 1.5rem;
        }
        .header {
            padding-bottom: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="justify-content: center;">
            <h1>
                <div class="imagem-container">
                    <img src="./img/Minecraft.png" alt="Logo do Minecraft">
                </div>
                <?= $lang['panel_title'] ?>
            </h1>
        </div>

        <div class="content-section active">
            <h2 style="text-align: center; margin-bottom: 2rem;"><?= $lang['login_h2'] ?></h2>
            <?php if ($error): ?>
                <p style="color: var(--ban-red); text-align: center;"><?= $error ?></p>
            <?php endif; ?>

            <form action="login.php" method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                <label for="username" style="font-weight: 600;"><?= $lang['username_label'] ?>:</label>
                <input type="text" id="username" name="username" required 
                       style="padding: 0.75rem; border-radius: 8px; border: none; background-color: #2a2a2a; color: #f0f0f0;">
                
                <label for="password" style="font-weight: 600;"><?= $lang['password_label'] ?>:</label>
                <input type="password" id="password" name="password" required
                       style="padding: 0.75rem; border-radius: 8px; border: none; background-color: #2a2a2a; color: #f0f0f0;">
                
                <button type="submit" name="minecraft_command_action" value="status"
                        style="margin-top: 1rem; padding: 0.75rem 1.5rem; background: linear-gradient(45deg, var(--accent-purple), var(--accent-pink)); border: none; border-radius: 8px; color: #fff; font-weight: 600; cursor: pointer;">
                    <?= $lang['login_button'] ?>
                </button>
            </form>

            <?php 
            if (isset($allow_registration) && $allow_registration): 
            ?>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="register.php" style="color: var(--accent-purple); text-decoration: none; font-weight: 600;">
                        <?= $lang['register_link'] ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>