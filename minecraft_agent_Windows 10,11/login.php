<?php
// Inicia a sessão no início do arquivo para garantir que esteja disponível
session_start();

// Inclui a conexão com o banco de dados.
// NOTA: 'db_connect.php' deve definir $pdo e $allow_registration.
require_once 'db_connect.php'; 

// Variável para armazenar mensagens de erro
$error = '';

// --- Lógica de Carregamento de Idioma ---
$default_lang = "pt"; // Idioma padrão
// Usa o idioma da URL (?lang=en) ou o padrão 'pt'
$lang_code = $_GET['lang'] ?? $default_lang; 
$lang_file = __DIR__ . "/lang/{$lang_code}.json"; // Assume que os arquivos .json estão em 'lang/'

// Verifica se o arquivo de idioma existe. Se não, tenta um fallback.
if (!file_exists($lang_file)) {
    // Tenta carregar o idioma padrão (pt). Se falhar, tenta 'en'.
    $lang_code = $default_lang;
    $lang_file = __DIR__ . "/lang/{$lang_code}.json";
    
    if (!file_exists($lang_file)) {
        // Último recurso: usa 'en'
        $lang_code = "en";
        $lang_file = __DIR__ . "/lang/{$lang_code}.json";
    }
}

// === LINHA CRÍTICA CORRIGIDA: Lendo e decodificando o arquivo JSON para $lang ===
if (file_exists($lang_file)) {
    // Carrega o conteúdo do arquivo JSON para a variável $lang
    $lang = json_decode(file_get_contents($lang_file), true);
} else {
    // Fallback manual caso todos os arquivos falhem (para evitar erros fatais no HTML)
    $lang = [
        'login_error_message' => 'Login failed.',
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

// Verifica se a requisição é do tipo POST (se o formulário foi enviado)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // O restante da sua lógica de login permanece correta
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Prepara a consulta para evitar injeção SQL
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verifica se o usuário existe e se a senha está correta
    if ($user && password_verify($password, $user['password'])) {
        // Se as credenciais estiverem corretas, define as variáveis de sessão
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $user['username'];

        // Redireciona o usuário para a página protegida
        header("Location: index.php");
        exit;
    } else {
        // Define uma mensagem de erro em caso de falha no login, usando $lang
        $error = $lang['login_error_message'];
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
                

				
				<button type="submit" name="minecraft_command_action" value="status"> <?= $lang['login_button'] ?></button>
            </form>

            <?php 
            // A variável $allow_registration deve vir do 'db_connect.php'
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