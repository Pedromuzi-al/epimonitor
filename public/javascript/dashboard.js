/* =====================================================
   EpiMonitor — Dashboard de Monitoramento
   js/dashboard.js
   ===================================================== */

const API = '../api';

let chartSintomas   = null;
let chartEvolucao   = null;
let periodoAtual    = 7;

// ─── Paleta de cores ────────────────────────────────
const CORES = {
  teal:   'rgba(0, 212, 168, .85)',
  orange: 'rgba(255, 107, 53, .85)',
  yellow: 'rgba(245, 200, 66, .85)',
  gray:   'rgba(139, 165, 194, .55)',
  red:    'rgba(232, 64, 64, .85)',
};
const CORES_SINTOMAS = [
  CORES.orange, CORES.teal, CORES.yellow,
  CORES.red, CORES.gray, 'rgba(99,179,237,.8)',
  'rgba(144,202,249,.8)', 'rgba(206,147,216,.8)',
];

// ─── Rótulos ─────────────────────────────────────────
const SINTOMA_LABEL = {
  febre:'Febre', tosse:'Tosse', dor_corpo:'Dor no corpo',
  dor_cabeca:'Dor de cabeça', diarreia:'Diarreia',
  nausea:'Náusea', fadiga:'Fadiga', manchas:'Manchas',
};

// ─── Toast ─────────────────────────────────────────
function toast(msg, tipo = 'success') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = `show ${tipo}`;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 4000);
}

// ─── Formata data pt-BR ──────────────────────────────
function fmtData(str) {
  if (!str) return '-';
  const d = new Date(str);
  return d.toLocaleDateString('pt-BR', { day:'2-digit', month:'2-digit' });
}

// ─── Carrega e renderiza tudo ────────────────────────
async function carregarDashboard(bairroId = '', periodo = 7) {
  periodoAtual = periodo;
  const params = new URLSearchParams({ periodo });
  if (bairroId) params.set('bairro_id', bairroId);

  try {
    const [resEstat, resAlertas] = await Promise.all([
      fetch(`${API}/estatisticas.php?${params}`),
      fetch(`${API}/alertas.php`),
    ]);
    const [estat, alertas] = await Promise.all([resEstat.json(), resAlertas.json()]);

    if (!estat.sucesso) throw new Error('Falha nas estatísticas.');

    const d = estat.dados;

    // Cards de totais
    document.getElementById('totalRegistros').textContent =
      Number(d.totais.total_registros).toLocaleString('pt-BR');
    document.getElementById('bairrosAtivos').textContent  = d.totais.bairros_ativos;
    document.getElementById('alertasAtivos').textContent  = d.totais.alertas_ativos;
    document.getElementById('sintomaDominante').textContent =
      d.sintomas[0] ? SINTOMA_LABEL[d.sintomas[0].sintoma] || d.sintomas[0].sintoma : '-';

    // Alertas
    renderAlertas(alertas.dados || []);

    // Gráfico de sintomas
    renderGraficoSintomas(d.sintomas);

    // Gráfico de evolução
    renderGraficoEvolucao(d.evolucao_diaria);

    // Tabela de bairros
    renderTabelaBairros(d.resumo_bairros);

  } catch (err) {
    toast('Erro ao carregar dados. Verifique a conexão.', 'error');
    console.error(err);
  }
}

// ─── Alertas ─────────────────────────────────────────
function renderAlertas(lista) {
  const box = document.getElementById('alertasLista');
  if (!lista.length) {
    box.innerHTML = '<p style="font-size:.82rem;color:var(--gray)">Nenhum alerta ativo no momento.</p>';
    return;
  }
  const corNivel = { baixo:'teal', medio:'orange', alto:'orange', critico:'red' };
  box.innerHTML = lista.map(a => `
    <div class="alert-box alert-${corNivel[a.nivel] || 'orange'}" style="margin-bottom:.6rem;">
      <span class="alert-icon">⚠</span>
      <div>
        <strong>${a.bairro} — ${a.nivel.toUpperCase()}</strong><br>
        ${a.mensagem}
        <div style="font-size:.7rem;margin-top:.3rem;opacity:.6">${new Date(a.atualizado).toLocaleString('pt-BR')}</div>
      </div>
    </div>
  `).join('');
}

// ─── Gráfico: Sintomas (Doughnut) ────────────────────
function renderGraficoSintomas(dados) {
  const ctx = document.getElementById('chartSintomas').getContext('2d');
  const labels = dados.map(s => SINTOMA_LABEL[s.sintoma] || s.sintoma);
  const values = dados.map(s => parseInt(s.quantidade, 10));

  if (chartSintomas) chartSintomas.destroy();

  chartSintomas = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: CORES_SINTOMAS.slice(0, dados.length),
        borderColor: '#050F1F',
        borderWidth: 3,
        hoverOffset: 8,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ` ${ctx.label}: ${ctx.parsed} registros`,
          },
        },
      },
    },
  });

  // Legenda manual
  const legEl = document.getElementById('legendaSintomas');
  legEl.innerHTML = labels.map((l, i) => `
    <span style="display:flex;align-items:center;gap:5px;font-size:.75rem;color:var(--gray)">
      <span style="width:10px;height:10px;border-radius:2px;background:${CORES_SINTOMAS[i]};flex-shrink:0"></span>
      ${l} <span style="font-family:'JetBrains Mono',monospace;color:var(--white)">(${values[i]})</span>
    </span>
  `).join('');
}

// ─── Gráfico: Evolução diária (Line) ─────────────────
function renderGraficoEvolucao(dados) {
  const ctx = document.getElementById('chartEvolucao').getContext('2d');
  const labels = dados.map(d => fmtData(d.dia));
  const values = dados.map(d => parseInt(d.registros, 10));

  if (chartEvolucao) chartEvolucao.destroy();

  const grad = ctx.createLinearGradient(0, 0, 0, 220);
  grad.addColorStop(0,   'rgba(0,212,168,.25)');
  grad.addColorStop(1,   'rgba(0,212,168,0)');

  chartEvolucao = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Registros',
        data: values,
        borderColor: '#00D4A8',
        backgroundColor: grad,
        borderWidth: 2.5,
        pointBackgroundColor: '#00D4A8',
        pointRadius: 4,
        pointHoverRadius: 6,
        tension: 0.38,
        fill: true,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y} registros` } },
      },
      scales: {
        x: {
          ticks: { color: '#8BA5C2', font: { size: 11, family: "'JetBrains Mono'" }, maxRotation: 0 },
          grid:  { color: 'rgba(255,255,255,.04)' },
        },
        y: {
          ticks: { color: '#8BA5C2', font: { size: 11 }, stepSize: 1 },
          grid:  { color: 'rgba(255,255,255,.06)' },
          min: 0,
        },
      },
    },
  });
}

// ─── Tabela de bairros ────────────────────────────────
function renderTabelaBairros(dados) {
  const tbody = document.getElementById('tabelaBairros');
  if (!dados.length) { tbody.innerHTML = '<tr><td colspan="4">Sem dados no período.</td></tr>'; return; }

  const max = Math.max(...dados.map(d => d.total_registros));

  tbody.innerHTML = dados.map(d => {
    const pct    = max > 0 ? Math.round(d.total_registros / max * 100) : 0;
    const nivel  = pct >= 80 ? 'badge-red' : pct >= 50 ? 'badge-orange' : 'badge-teal';
    const txt    = pct >= 80 ? 'Alto' : pct >= 50 ? 'Médio' : 'Normal';
    return `
      <tr>
        <td><strong>${d.bairro}</strong></td>
        <td>${d.total_registros || 0}</td>
        <td>${d.media_dias ? Number(d.media_dias).toFixed(1) : '-'} dias</td>
        <td><span class="badge ${nivel}">${txt}</span></td>
      </tr>
    `;
  }).join('');
}

// ─── Filtros ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  carregarDashboard();

  // Carrega bairros no filtro
  fetch(`${API}/bairros.php`).then(r => r.json()).then(json => {
    const sel = document.getElementById('filtroBairro');
    if (!sel || !json.sucesso) return;
    json.dados.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b.id;
      opt.textContent = b.nome;
      sel.appendChild(opt);
    });
  }).catch(() => {});

  document.getElementById('filtroBairro')?.addEventListener('change', function() {
    carregarDashboard(this.value, periodoAtual);
  });

  document.querySelectorAll('.btn-periodo')?.forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.btn-periodo').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      carregarDashboard(document.getElementById('filtroBairro')?.value || '', parseInt(this.dataset.dias, 10));
    });
  });

  // Auto-refresh a cada 60s
  setInterval(() => carregarDashboard(
    document.getElementById('filtroBairro')?.value || '', periodoAtual
  ), 60000);
});
