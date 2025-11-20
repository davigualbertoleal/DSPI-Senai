<?php
session_start();

# === Variáveis do Banco ===
$servidor = "localhost";
$usuario = "root";
$senha = "";  // Lembra de colocar a senha certa que você descobriu
$database = "appLivroTeste";

# === Conectando ao banco ===
$conexao = mysqli_connect($servidor, $usuario, $senha, $database);

if(!$conexao){
    die("Erro na conexão: ".mysqli_connect_error());
}

$erro = "";
$sucesso = "";

function gerarCpfAleatorio() {
    // Gera 9 números aleatórios
    $noveDigitos = '';
    for ($i = 0; $i < 9; $i++) {
        $noveDigitos .= rand(0, 9);
    }
    
    // Calcula primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $noveDigitos[$i] * (10 - $i);
    }
    $resto = $soma % 11;
    $digito1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Calcula segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $noveDigitos[$i] * (11 - $i);
    }
    $soma += $digito1 * 2;
    $resto = $soma % 11;
    $digito2 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Formata CPF (XXX.XXX.XXX-XX)
    $cpf = substr($noveDigitos, 0, 3) . '.' . 
           substr($noveDigitos, 3, 3) . '.' . 
           substr($noveDigitos, 6, 3) . '-' . 
           $digito1 . $digito2;
    
    return $cpf;
}

# Verificar se o formulário foi enviado
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha_digitada = $_POST['senha'];
    $confsenha = $_POST['confsenha'];
    $ra = rand(100000, 999999); // gera RA aleatório
    $cpf = gerarCpfAleatorio(); // gera CPF válido aleatório
    
    # Validações
    if(empty($nome) || empty($email) || empty($senha_digitada)){
        $erro = "Todos os campos são obrigatórios!";
    }
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $erro = "E-mail inválido!";
    }
    elseif(strlen($senha_digitada) < 6 || !preg_match('/[a-z]/', $senha_digitada) || 
           !preg_match('/[A-Z]/', $senha_digitada) || !preg_match('/[0-9]/', $senha_digitada)){
        $erro = "A senha não atende aos requisitos de segurança!";
    }
    elseif($senha_digitada !== $confsenha){
        $erro = "As senhas não coincidem!";
    }
    else {
        # Verificar se email já existe
        $sql_verifica = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = mysqli_prepare($conexao, $sql_verifica);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result_verifica = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result_verifica) > 0){
            $erro = "Este e-mail já está cadastrado!";
        } else {
            # Inserir novo usuário
            $senha_hash = password_hash($senha_digitada, PASSWORD_DEFAULT);
            
            $sql_inserir = "INSERT INTO usuarios (nome, email, senha, ra, cpf) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexao, $sql_inserir);
            mysqli_stmt_bind_param($stmt, "sssis", $nome, $email, $senha_hash, $ra, $cpf);
            
            if(mysqli_stmt_execute($stmt)){
                $sucesso = "Cadastro realizado com sucesso!";
                # Redirecionar para login após 2 segundos
                header("refresh:2;url=login.php");
            } else {
                $erro = "Erro ao cadastrar usuário!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>
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

        .success-message {
            background: #27ae60;
            color: white;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 0.9rem;
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

        .rule-ok {
            color: #4cd137; /* verde */
        }

        .rule-bad {
            color: #e84118; /* vermelho */
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h1 class="title">L.S.A</h1>

        <?php if($sucesso): ?>
            <div class="success-message"><?php echo $sucesso; ?></div>
        <?php endif; ?>

        <?php if($erro): ?>
            <div class="error-message"><?php echo $erro; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <label for="nome">Nome de usuário</label>
                <input type="text" id="nome" name="nome" placeholder="Seu nome de usuário" required>
            </div>

            <div class="input-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="seuemail@example.com" required>
            </div>

            <div class="input-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="********" required>
            </div>

            <div id="password-rules" style="margin-top: 10px; font-size: 0.85rem; color: #ccc; display: none;">
                <p id="rule-length">✖ Mínimo de 6 caracteres</p>
                <p id="rule-lower">✖ Pelo menos uma letra minúscula</p>
                <p id="rule-upper">✖ Pelo menos uma letra maiúscula</p>
                <p id="rule-number">✖ Pelo menos um número</p>
            </div>

            <div class="input-group">
                <label for="confsenha">Confirmar senha</label>
                <input type="password" id="confsenha" name="confsenha" placeholder="********" required>
            </div>

            <button type="submit" class="btn-login">Cadastrar</button>
        </form>

        <p class="footer-text">
            Já possui conta? <a href="login.php">Login</a>
        </p>
    </div>

    <script>
        document.getElementById("senha").addEventListener("input", validateWordVisual);

        function validateWordVisual() {
            const senha = document.getElementById("senha").value;
            const rules = document.getElementById("password-rules");

            const hasMinLength = senha.length >= 6;
            const hasLower = /[a-z]/.test(senha);
            const hasUpper = /[A-Z]/.test(senha);
            const hasNumber = /[0-9]/.test(senha);

            // Se o campo estiver vazio → esconder regras
            if (senha.length === 0) {
                rules.style.display = "none";
                return;
            }

            // Se *todas* as regras estiverem ok → esconder novamente
            if (hasMinLength && hasLower && hasUpper && hasNumber) {
                rules.style.display = "none";
            } else {
                rules.style.display = "block";
            }

            // Atualizar cores dos itens
            updateRule("rule-length", hasMinLength);
            updateRule("rule-lower", hasLower);
            updateRule("rule-upper", hasUpper);
            updateRule("rule-number", hasNumber);
        }

        function updateRule(id, isValid) {
            const rule = document.getElementById(id);

            if (isValid) {
                rule.classList.remove("rule-bad");
                rule.classList.add("rule-ok");
                rule.textContent = "✔ " + rule.textContent.substring(2);
            } else {
                rule.classList.remove("rule-ok");
                rule.classList.add("rule-bad");
                rule.textContent = "✖ " + rule.textContent.substring(2);
            }
        }
    </script>

</body>
</html>