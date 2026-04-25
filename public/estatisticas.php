<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatísticas - EpiMonitor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Estatísticas de Monitoramento</h1>

    <div id="filtros">
        <label for="periodo">Período (dias):</label>
        <input type="number" id="periodo" value="7" min="1" max="90">
        <button id="carregar">Carregar</button>
    </div>

    <div id="conteudo">
        <p>Carregando estatísticas...</p>
    </div>

    <script>
    document.getElementById('carregar').addEventListener('click', carregarEstatisticas);

    function carregarEstatisticas() {
        const periodo = document.getElementById('periodo').value;
        fetch(`../api/estatisticas.php?periodo=${periodo}`)
        .then(res => res.json())
        .then(data => {
            if (data.sucesso) {
                const d = data.dados;
                document.getElementById('conteudo').innerHTML = `
                    <h2>Totais</h2>
                    <p>Registros: ${d.totais.total_registros}</p>
                    <p>Bairros ativos: ${d.totais.bairros_ativos}</p>
                    <p>Alertas: ${d.totais.alertas_ativos}</p>

                    <h2>Sintomas</h2>
                    <ul>
                        ${d.sintomas.map(s => `<li>${s.sintoma}: ${s.quantidade}</li>`).join('')}
                    </ul>

                    <h2>Resumo por Bairro</h2>
                    <table border="1">
                        <tr><th>Bairro</th><th>Registros</th><th>Média Dias</th><th>Último</th></tr>
                        ${d.resumo_bairros.map(b => `<tr><td>${b.bairro}</td><td>${b.total_registros}</td><td>${b.media_dias}</td><td>${b.ultimo_registro}</td></tr>`).join('')}
                    </table>

                    <h2>Evolução Diária</h2>
                    <ul>
                        ${d.evolucao_diaria.map(e => `<li>${e.dia}: ${e.registros}</li>`).join('')}
                    </ul>
                `;
            } else {
                document.getElementById('conteudo').innerHTML = '<p>Erro ao carregar.</p>';
            }
        })
        .catch(err => {
            document.getElementById('conteudo').innerHTML = '<p>Erro de conexão.</p>';
        });
    }

    // Carregar ao iniciar
    carregarEstatisticas();
    </script>
</body>
</html></content>
<parameter name="filePath">c:\wamp\www\epimontor\public\estatisticas.php