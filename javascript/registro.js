/* =====================================================
   EpiMonitor — Página de Registro
   js/registro.js
   ===================================================== */

const API = '../api';

// ─── Toast ─────────────────────────────────────────
function toast(msg, tipo = 'success') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = `show ${tipo}`;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 4000);
}

// ─── Carrega bairros na lista ───────────────────────
async function carregarBairros() {
  const sel = document.getElementById('bairroSelect');
  try {
    const res  = await fetch(`${API}/bairros.php`);
    const json = await res.json();
    if (!json.sucesso) throw new Error();

    json.dados.forEach(b => {
      const opt   = document.createElement('option');
      opt.value   = b.id;
      opt.textContent = b.nome;
      sel.appendChild(opt);
    });
  } catch {
    toast('Erro ao carregar bairros. Recarregue a página.', 'error');
  }
}

// ─── Toggle checkbox ────────────────────────────────
function toggleCheck(el) {
  el.classList.toggle('checked');
  const box = el.querySelector('.check-box');
  box.textContent = el.classList.contains('checked') ? '✓' : '';
}

// ─── Contador de sintomas selecionados ─────────────
function atualizarContador() {
  const total = document.querySelectorAll('.check-item.checked').length;
  const cont  = document.getElementById('contSintomas');
  if (cont) cont.textContent = total > 0 ? `${total} selecionado(s)` : '';
}

document.querySelectorAll('.check-item').forEach(el => {
  el.addEventListener('click', () => {
    toggleCheck(el);
    atualizarContador();
  });
});

// ─── Carrega alertas ativos no topo ─────────────────
async function carregarAlertas() {
  try {
    const res  = await fetch(`${API}/alertas.php`);
    const json = await res.json();
    const box  = document.getElementById('alertasBox');
    if (!box) return;

    if (!json.sucesso || !json.dados.length) {
      box.innerHTML = '';
      return;
    }

    box.innerHTML = json.dados.map(a => `
      <div class="alert-box alert-orange">
        <span class="alert-icon">⚠</span>
        <div>
          <strong>${a.bairro} — Nível ${a.nivel.toUpperCase()}</strong><br>
          ${a.mensagem}
        </div>
      </div>
    `).join('');
  } catch { /* silencioso */ }
}

// ─── Submit do formulário ────────────────────────────
async function registrarSintomas(e) {
  e.preventDefault();

  const bairroId = parseInt(document.getElementById('bairroSelect').value, 10);
  if (!bairroId) { toast('Selecione seu bairro.', 'error'); return; }

  const sintomas = [...document.querySelectorAll('.check-item.checked input')]
    .map(i => i.value);
  if (!sintomas.length) { toast('Marque pelo menos um sintoma.', 'error'); return; }

  const dias = parseInt(document.getElementById('diasInput').value, 10);
  if (!dias || dias < 1) { toast('Informe há quantos dias sente os sintomas.', 'error'); return; }

  const btn = document.getElementById('btnRegistrar');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Enviando…';

  try {
    const res  = await fetch(`${API}/registrar.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ bairro_id: bairroId, sintomas, dias_sintomas: dias }),
    });
    const json = await res.json();

    if (json.sucesso) {
      toast('✓ ' + json.mensagem, 'success');
      mostrarResultado(json.dados, bairroId);
      document.getElementById('formRegistro').reset();
      document.querySelectorAll('.check-item.checked').forEach(el => {
        el.classList.remove('checked');
        el.querySelector('.check-box').textContent = '';
      });
      atualizarContador();
      carregarAlertas();
    } else {
      toast(json.mensagem || 'Erro ao registrar.', 'error');
    }
  } catch {
    toast('Falha na conexão com o servidor.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Registrar Sintomas Anonimamente →';
  }
}

// ─── Exibe resultado pós-registro ────────────────────
async function mostrarResultado(dados, bairroId) {
  const box = document.getElementById('resultadoBox');
  if (!box) return;

  // Busca estatísticas do bairro
  let statsHtml = '';
  try {
    const res  = await fetch(`${API}/estatisticas.php?bairro_id=${bairroId}&periodo=1`);
    const json = await res.json();

    if (json.sucesso && json.dados.sintomas.length) {
      const max = json.dados.sintomas[0].quantidade;
      statsHtml = `
        <div style="margin-bottom:.75rem;">
          <div class="section-label" style="margin-bottom:.5rem;">Sintomas no bairro (últimas 24h)</div>
          ${json.dados.sintomas.slice(0,5).map(s => `
            <div class="mini-bar-row">
              <span class="mini-bar-label">${traduzirSintoma(s.sintoma)}</span>
              <div class="mini-bar-track">
                <div class="mini-bar-fill ${s.quantidade === max ? 'high' : ''}" style="width:${Math.round(s.quantidade/max*100)}%"></div>
              </div>
              <span class="mini-bar-count">${s.quantidade}</span>
            </div>
          `).join('')}
        </div>
      `;
    }
  } catch { /* silencioso */ }

  const alertaHtml = dados.alerta
    ? `<div class="alert-box alert-orange"><span class="alert-icon">⚠</span><div><strong>Alerta gerado para este bairro.</strong> Os dados indicam aumento acima do limiar. Procure a UBS se os sintomas persistirem.</div></div>`
    : `<div class="alert-box alert-teal"><span class="alert-icon">✓</span><div>Registro enviado. Situação estável no bairro. Obrigado por contribuir!</div></div>`;

  box.innerHTML = statsHtml + alertaHtml;
  box.style.display = 'block';
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ─── Tradução de rótulos ─────────────────────────────
function traduzirSintoma(s) {
  const mapa = {
    febre: '🌡 Febre', tosse: '😮‍💨 Tosse', dor_corpo: '🦴 Dor corpo',
    dor_cabeca: '🤕 Dor cabeça', diarreia: '🤢 Diarreia',
    nausea: '😵 Náusea', fadiga: '😴 Fadiga', manchas: '🔴 Manchas',
  };
  return mapa[s] || s;
}

// ─── Init ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  carregarBairros();
  carregarAlertas();

  const form = document.getElementById('formRegistro');
  if (form) form.addEventListener('submit', registrarSintomas);

  // Reveal on scroll
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
  }, { threshold: .12 });
  document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
});
