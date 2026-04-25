<!DOCTYPE html>
<html>
<head>
    <title>Registrar Sintomas</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php
// Incluir configuração e gerar token CSRF
require_once __DIR__ . '/../config/config.php';
$csrfToken = generateCsrfToken();
?>

<h1>Registrar Sintomas</h1>

<form id="form">
    <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    
    <label for="bairro_id">Bairro ID:</label>
    <input type="number" name="bairro_id" id="bairro_id" required><br>

    <label for="dias_sintomas">Dias com sintomas:</label>
    <input type="number" name="dias_sintomas" id="dias_sintomas" min="1" max="60" required><br>

    <label>Sintomas:</label><br>
    <input type="checkbox" name="sintomas" value="febre"> Febre<br>
    <input type="checkbox" name="sintomas" value="tosse"> Tosse<br>
    <input type="checkbox" name="sintomas" value="dor_corpo"> Dor no corpo<br>
    <input type="checkbox" name="sintomas" value="dor_cabeca"> Dor de cabeça<br>
    <input type="checkbox" name="sintomas" value="diarreia"> Diarreia<br>
    <input type="checkbox" name="sintomas" value="nausea"> Náusea<br>
    <input type="checkbox" name="sintomas" value="fadiga"> Fadiga<br>
    <input type="checkbox" name="sintomas" value="manchas"> Manchas<br>

    <button type="submit">Enviar</button>
</form>

<h2>Estatísticas Recentes</h2>
<div id="estatisticas">
    <p>Carregando...</p>
</div>

<script>
document.getElementById('form').addEventListener('submit', function(e) {
    e.preventDefault();

    const bairroId = parseInt(document.getElementById('bairro_id').value);
    const diasSintomas = parseInt(document.getElementById('dias_sintomas').value);
    const sintomasChecks = document.querySelectorAll('input[name="sintomas"]:checked');
    const sintomas = Array.from(sintomasChecks).map(cb => cb.value);
    const csrfToken = document.getElementById('csrf_token').value;

    const dados = {
        bairro_id: bairroId,
        sintomas: sintomas,
        dias_sintomas: diasSintomas,
        csrf_token: csrfToken
    };

    fetch('../api/registro.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(dados)
    })
    .then(res => res.json())
    .then(data => {
        alert(data.mensagem || JSON.stringify(data));
        carregarEstatisticas(); // Recarregar estatísticas após registro
    })
    .catch(err => {
        console.error('Erro ao registrar:', err);
        alert('Erro ao registrar sintomas. Tente novamente.');
    });
});

// Função para carregar estatísticas
function carregarEstatisticas() {
    fetch('../api/estatisticas.php?periodo=7')
    .then(res => res.json())
    .then(data => {
        if (data.sucesso) {
            const d = data.dados;
            document.getElementById('estatisticas').innerHTML = `
                <p>Total de registros nos últimos 7 dias: ${d.totais.total_registros}</p>
                <p>Bairros ativos: ${d.totais.bairros_ativos}</p>
                <p>Alertas ativos: ${d.totais.alertas_ativos}</p>
                <h3>Sintomas mais comuns:</h3>
                <ul>
                    ${d.sintomas.map(s => `<li>${s.sintoma}: ${s.quantidade}</li>`).join('')}
                </ul>
            `;
        } else {
            document.getElementById('estatisticas').innerHTML = '<p>Erro ao carregar estatísticas.</p>';
        }
    })
    .catch(err => {
        document.getElementById('estatisticas').innerHTML = '<p>Erro de conexão.</p>';
    });
}

// Carregar estatísticas ao carregar a página
carregarEstatisticas();
</script>

</body>
</html>