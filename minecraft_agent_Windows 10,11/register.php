<?php
// Inclui o arquivo de conexão com o banco de dados.
// NOTA: 'db_connect.php' deve definir $pdo e $allow_registration.
require_once 'db_connect.php';

// Inicializa uma variável para armazenar mensagens (sucesso ou erro).
$message = '';

// --- Lógica de Carregamento de Idioma (IDÊNTICA ao login.php) ---
$default_lang = "pt"; // Idioma padrão
// Usa o idioma da URL (?lang=en) ou o padrão 'pt'
$lang_code = $_GET['lang'] ?? $default_lang; 
$lang_file = __DIR__ . "/lang/{$lang_code}.json"; 

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

// Carrega o JSON para a variável $lang
if (file_exists($lang_file)) {
    $lang = json_decode(file_get_contents($lang_file), true);
} else {
    // Fallback manual para evitar erros fatais se o JSON não for encontrado
    $lang = [
        'registration_disabled' => 'Registration is currently disabled.',
        'user_exists_error' => 'This username already exists.',
        'empty_fields_error' => 'Username and password cannot be empty.',
        'registration_success' => 'User "%s" created successfully!',
        'database_error' => 'Database Error',
        'contact_admin' => 'Registration is disabled. Please contact an administrator.',
        'register_title' => 'User Registration',
        'register_h1' => 'Registration',
        'register_h2' => 'Create New User',
        'username_label' => 'Username',
        'password_label' => 'Password',
        'register_button' => 'Register',
        'lang_code' => 'en'
    ];
}
// Fim da Lógica de Carregamento de Idioma

// Verifica se o registro é permitido antes de processar o formulário.
if (!$allow_registration) {
    // Se o registro estiver desativado, define uma mensagem para informar o usuário
    $message = $lang['registration_disabled'];
} else if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get the username and password from the form input.
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate that the fields are not empty.
    if (!empty($username) && !empty($password)) {
        try {
            // Check if the username already exists in the database.
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $count = $stmt->fetchColumn();

            // If count is greater than 0, the username already exists.
            if ($count > 0) {
                $message = $lang['user_exists_error'];
            } else {
                // Hash the password for secure storage.
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert the new user into the 'users' table.
                $insert_stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $insert_stmt->execute([$username, $hashed_password]);

                // Set a success message.
                $message = sprintf($lang['registration_success'], htmlspecialchars($username));
            }
        } catch (\PDOException $e) {
            // Handle any database-related errors.
            $message = $lang['database_error'] . ": " . $e->getMessage();
        }
    } else {
        $message = $lang['empty_fields_error'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang['lang_code'] ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="./img/favicon.png" type="image/png" />
    <title><?= $lang['register_title'] ?></title>
    <link rel="stylesheet" href="style.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Additional styling for this specific page to match the theme */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 400px; /* Reduces the maximum width */
            margin-top: 5rem;
            padding: 1.5rem; /* Reduces padding */
        }
        .header {
            padding-bottom: 0.75rem; /* Reduces padding */
        }
        .contact-message {
            margin-top: 2rem;
            font-size: 0.9em;
            color: #ccc;
        }
        .contact-message a {
            color: var(--accent-purple);
            text-decoration: none;
            font-weight: 600;
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
                <?= $lang['register_h1'] ?>
            </h1>
        </div>

        <div class="content-section active">
            <h2 style="text-align: center; margin-bottom: 2rem;"><?= $lang['register_h2'] ?></h2>
            
            <?php 
            // Define a cor da mensagem com base no sucesso/erro
            $msg_color = (strpos($message, 'sucesso') !== false || strpos($message, 'successfully') !== false) ? 'var(--accent-purple)' : 'var(--ban-red)';
            if (!empty($message)): ?>
                <p style="text-align: center; color: <?= $msg_color ?>;"><?= htmlspecialchars($message) ?></p>
            <?php endif; ?>

            <?php if (isset($allow_registration) && $allow_registration): ?>
                <form action="register.php" method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                    <label for="username" style="font-weight: 600;"><?= $lang['username_label'] ?>:</label>
                    <input type="text" id="username" name="username" required 
                           style="padding: 0.75rem; border-radius: 8px; border: none; background-color: #2a2a2a; color: #f0f0f0;">
                    
                    <label for="password" style="font-weight: 600;"><?= $lang['password_label'] ?>:</label>
                    <input type="password" id="password" name="password" required
                           style="padding: 0.75rem; border-radius: 8px; border: none; background-color: #2a2a2a; color: #f0f0f0;">
                    
					
					<button type="submit" name="minecraft_command_action" value="status"> <?= $lang['register_button'] ?></button>
                </form>
            <?php else: ?>
                <div class="contact-message">
                    <p style="text-align: center;">
                        <?= $lang['contact_admin'] ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>