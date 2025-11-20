<?php
session_start();
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

$servidor = "localhost";
$usuario = "root";
$senha = "";
$database = "appLivroTeste";
$conexao = mysqli_connect($servidor, $usuario, $senha, $database);

if(isset($_POST['livro_id']) && isset($_POST['status'])) {
    $livro_id = intval($_POST['livro_id']);
    $status = $_POST['status'];
    $usuario_id = $_SESSION['usuario_id'];
    
    // Verificar se já existe na estante
    $sql_check = "SELECT id FROM historico_leitura WHERE usuario_id = ? AND livro_id = ?";
    $stmt_check = mysqli_prepare($conexao, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "ii", $usuario_id, $livro_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    if(mysqli_num_rows($result_check) > 0) {
        // Atualizar status existente
        $sql = "UPDATE historico_leitura SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE usuario_id = ? AND livro_id = ?";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $status, $usuario_id, $livro_id);
    } else {
        // Inserir novo
        $sql = "INSERT INTO historico_leitura (usuario_id, livro_id, status, data_inicio) VALUES (?, ?, ?, CURDATE())";
        $stmt = mysqli_prepare($conexao, $sql);
        mysqli_stmt_bind_param($stmt, "iis", $usuario_id, $livro_id, $status);
    }
    
    if(mysqli_stmt_execute($stmt)) {
        // Sucesso - redirecionar de volta
        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit();
    } else {
        // Erro
        die("Erro ao adicionar livro à estante");
    }
} else {
    header("Location: index.php");
    exit();
}
?>