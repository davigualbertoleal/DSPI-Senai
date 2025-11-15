<?php
session_start();

# Verificar se usuário está logado
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

# === Variáveis do Banco ===
$servidor = "localhost";
$usuario = "root";
$senha = "root";
$database = "appLivroTeste";

# === Conectando ao banco ===
$conexao = mysqli_connect($servidor, $usuario, $senha, $database);

if(!$conexao){
    die("Erro na conexão: ".mysqli_connect_error());
}

# Buscar informações do usuário
$usuario_id = $_SESSION['usuario_id'];
$sql_usuario = "SELECT nome, email, ra FROM usuarios WHERE id = '$usuario_id'";
$result_usuario = mysqli_query($conexao, $sql_usuario);
$usuario_info = mysqli_fetch_assoc($result_usuario);

# Buscar estatísticas do usuário
$sql_estatisticas = "SELECT 
    COUNT(*) as total_livros,
    SUM(CASE WHEN status = 'LENDO' THEN 1 ELSE 0 END) as lendo,
    SUM(CASE WHEN status = 'LIDO' THEN 1 ELSE 0 END) as lido,
    SUM(CASE WHEN status = 'QUERO_LER' THEN 1 ELSE 0 END) as quer_ler
    FROM historico_leitura WHERE usuario_id = '$usuario_id'";
$result_estatisticas = mysqli_query($conexao, $sql_estatisticas);
$estatisticas = mysqli_fetch_assoc($result_estatisticas);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal - Leitura+</title>
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
        }

        .header {
            background: #151820;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-logout {
            background: #6c5ce7;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .welcome {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #151820;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #6c5ce7;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #b5b5b5;
            font-size: 0.9rem;
        }

        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .nav-card {
            background: #151820;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: white;
            transition: transform 0.2s, background 0.2s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .nav-card:hover {
            transform: translateY(-5px);
            background: #1a1d26;
        }

        .nav-icon {
            font-size: 2rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Leitura+</h1>
        <div class="user-info">
            <span>Olá, <strong><?php echo $usuario_info['nome']; ?></strong></span>
            <a href="logout.php" class="btn-logout">Sair</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h2>Bem-vindo ao Leitura+</h2>
            <p>Gerencie sua biblioteca pessoal e descubra novos livros!</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $estatisticas['total_livros'] ?: '0'; ?></div>
                <div class="stat-label">Total de Livros</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $estatisticas['lendo'] ?: '0'; ?></div>
                <div class="stat-label">Lendo Agora</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $estatisticas['lido'] ?: '0'; ?></div>
                <div class="stat-label">Já Lidos</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $estatisticas['quer_ler'] ?: '0'; ?></div>
                <div class="stat-label">Quero Ler</div>
            </div>
        </div>

        <div class="nav-grid">
            <a href="livros.php" class="nav-card">
                <div class="nav-icon">📚</div>
                <h3>Biblioteca</h3>
                <p>Explore todos os livros</p>
            </a>
            <a href="meus_livros.php" class="nav-card">
                <div class="nav-icon">📖</div>
                <h3>Meus Livros</h3>
                <p>Gerencie sua coleção</p>
            </a>
            <a href="avaliacoes.php" class="nav-card">
                <div class="nav-icon">⭐</div>
                <h3>Avaliações</h3>
                <p>Veja suas avaliações</p>
            </a>
            <a href="perfil.php" class="nav-card">
                <div class="nav-icon">👤</div>
                <h3>Meu Perfil</h3>
                <p>Edite suas informações</p>
            </a>
        </div>
    </div>

</body>
</html>