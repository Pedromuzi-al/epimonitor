<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas Ativos - EpiMonitor</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Alertas Ativos</h1>
    <div id="alertas">
        <p>Carregando alertas...</p>
    </div>
    <script>
    function carregarAlertas() {
        fetch('../api/alertas.php')
        .then(res => res.json())
        .then(data => {
            if (data.sucesso && data.dados.length) {
                document.getElementById('alertas').innerHTML = data.dados.map(a => `
                    <div class="alerta-box alerta-${a.nivel}">
                        <strong>Bairro:</strong> ${a.bairro}<br>
                        <strong>Nível:</strong> ${a.nivel.toUpperCase()}<br>
                        <strong>Mensagem:</strong> ${a.mensagem}<br>
                        <strong>Atualizado:</strong> ${new Date(a.atualizado).toLocaleString('pt-BR')}
                    </div>
                `).join('');
            } else {
                document.getElementById('alertas').innerHTML = '<p>Nenhum alerta ativo no momento.</p>';
            }
        })
        .catch(() => {
            document.getElementById('alertas').innerHTML = '<p>Erro ao carregar alertas.</p>';
        });
    }
    carregarAlertas();
    </script>
    <style>
    .alerta-box { margin: 1em 0; padding: 1em; border-radius: 8px; background: var(--card-bg); border-left: 6px solid var(--orange); }
    .alerta-alto { border-color: var(--orange); }
    .alerta-critico { border-color: var(--red); }
    .alerta-medio { border-color: var(--yellow); }
    .alerta-baixo { border-color: var(--teal); }
    </style>
</body>
</html>