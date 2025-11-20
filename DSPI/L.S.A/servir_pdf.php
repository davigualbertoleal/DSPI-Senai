<?php
session_start();
if(!isset($_SESSION['usuario_id'])){
    header("Location: login.php");
    exit();
}

$livro_id = $_GET['id'] ?? null;
if(!$livro_id) {
    die("Livro não especificado");
}

// Conectar ao banco
$servidor = "localhost";
$usuario = "root";
$senha = "";
$database = "appLivroTeste";
$conexao = mysqli_connect($servidor, $usuario, $senha, $database);

// Buscar livro
$sql = "SELECT arquivo_pdf, titulo FROM livros WHERE id = ?";
$stmt = mysqli_prepare($conexao, $sql);
mysqli_stmt_bind_param($stmt, "i", $livro_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$livro = mysqli_fetch_assoc($resultado);

if($livro && !empty($livro['arquivo_pdf']) && file_exists($livro['arquivo_pdf'])) {
    $caminho_pdf = $livro['arquivo_pdf'];
    
    // Configurar headers para PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $livro['titulo'] . '.pdf"');
    header('Content-Length: ' . filesize($caminho_pdf));
    
    // Ler e enviar o arquivo
    readfile($caminho_pdf);
    exit();
} else {
    // Tentar encontrar PDF na pasta
    $titulo_arquivo = preg_replace('/[^a-zA-Z0-9]/', '_', $livro['titulo']);
    $possiveis_caminhos = [
        "pdfs/{$titulo_arquivo}.pdf",
        "pdfs/{$livro_id}.pdf",
        "pdfs/livro_{$livro_id}.pdf"
    ];
    
    foreach($possiveis_caminhos as $caminho) {
        if(file_exists($caminho)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $livro['titulo'] . '.pdf"');
            header('Content-Length: ' . filesize($caminho));
            readfile($caminho);
            exit();
        }
    }
    
    // Se não encontrou nenhum PDF
    http_response_code(404);
    echo "PDF não encontrado para este livro";
}
?>