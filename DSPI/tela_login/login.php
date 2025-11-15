<?php
session_start();

# === Variáveis do Banco ===
$servidor = "localhost";
$usuario = "root";
$senha = "";
$database = "appLivroTeste";

# === Conectando ao banco ===
$conexao = mysqli_connect($servidor, $usuario, $senha, $database);

if(!$conexao){
    die("Erro na conexão: ".mysqli_connect_error());
}

# Verificar se o formulário foi enviado
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $email = $_POST['email'];
    $senha_digitada = $_POST['senha'];
    
    # Buscar usuário no banco
    $sql = "SELECT id, nome, email, senha FROM usuarios WHERE email = ?";
    $stmt = mysqli_prepare($conexao, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) > 0){ 
        $usuario = mysqli_fetch_assoc($result);
        
        # Verificar senha
        if(password_verify($senha_digitada, $usuario['senha'])){
            # Login bem-sucedido
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            header("Location: main.php");
            exit();
        } else {
            $erro = "Senha incorreta!";
        }
    } else {
        $erro = "Usuário não encontrado!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Inter", sans-serif;
        }

        body {
            background: #0d0f14;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-container {
            background: #151820;
            width: 380px;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 0 25px rgba(0,0,0,0.35);
        }

        .title {
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .error-message {
            background: #e74c3c;
            color: white;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 0.9rem;
        }

        .input-group {
            margin-bottom: 18px;
            width: 100%;
        }

        .input-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: none;
            outline: none;
            background: #1f232d;
            color: #fff;
            font-size: 1rem;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #6c5ce7;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 10px;
            transition: 0.2s;
        }

        .btn-login:hover {
            background: #5a4bd6;
        }

        .footer-text {
            text-align: center;
            margin-top: 18px;
            font-size: 0.85rem;
            color: #b5b5b5;
        }

        .footer-text a {
            color: #6c5ce7;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h1 class="title">Leitura+</h1>

        <?php if(isset($erro)): ?>
            <div class="error-message"><?php echo $erro; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="seuemail@example.com" required>
            </div>

            <div class="input-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="********" required>
            </div>

            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <p class="footer-text">
            Não possui conta? <a href="cadastro.php">Criar conta</a>
        </p>
    </div>

</body>
</html>