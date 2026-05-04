// ============================================================
//  CortexPOS — Global JS (UI only, no API calls)
// ============================================================

// ── Sidebar Management ────────────────────────────────────
function initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const menu_toggle = document.querySelector('.menu-toggle');
    
    // Create backdrop if not exists
    let backdrop = document.querySelector('.sidebar-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
    }
    
    // Toggle sidebar
    menu_toggle?.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSidebar();
    });
    
    // Close sidebar when clicking on a nav item
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth <= 900) {
                closeSidebar();
            }
        });
    });
    
    // Close sidebar when clicking backdrop
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
            closeSidebar();
        }
    });
    
    // Close sidebar on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && window.innerWidth <= 900) {
            closeSidebar();
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
            closeSidebar();
            backdrop.classList.remove('active');
        }
    });
}

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    
    if (sidebar?.classList.contains('expanded')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

function openSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    
    sidebar?.classList.add('expanded');
    backdrop?.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    
    sidebar?.classList.remove('expanded');
    backdrop?.classList.remove('active');
    document.body.style.overflow = '';
}

// Initialize sidebar on page load
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    
    // ── Flash messages ──
    const flash = document.querySelector('.flash-msg');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transition = '.5s';
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    }
});

// ── Modal ────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('keydown', e => { if(e.key==='Escape') document.querySelectorAll('.overlay.open').forEach(m=>m.classList.remove('open')); });
document.addEventListener('click',   e => { if(e.target.classList.contains('overlay')) e.target.classList.remove('open'); });

// ── Confirm dialog (uses native but styled) ──────────────────
function cortexConfirm(msg, formId) {
    if (window.confirm(msg)) {
        document.getElementById(formId)?.submit();
    }
}

// ── Auto-hide flash messages ─────────────────────────────────
// Moved to DOMContentLoaded above

// ── Preview image before upload ──────────────────────────────
function previewImage(input, imgId) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => { const img = document.getElementById(imgId); if(img) { img.src=e.target.result; img.style.display='block'; } };
    reader.readAsDataURL(input.files[0]);
}

// ── Format number ─────────────────────────────────────────────
function fmtNum(n) { return parseFloat(n||0).toFixed(2); }

// ── Search filter (client-side table search) ─────────────────
function filterTable(inputId, tableId) {
    const q = document.getElementById(inputId)?.value.toLowerCase() || '';
    document.querySelectorAll('#'+tableId+' tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── Sparkline canvas ─────────────────────────────────────────
function drawSparkline(canvasId, data, color) {
    color = color || '#d4922a';
    const canvas = document.getElementById(canvasId);
    if (!canvas || !data || !data.length) return;
    canvas.width = canvas.offsetWidth || 300;
    const ctx = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height, p = 8;
    const max = Math.max(...data) || 1, min = Math.min(...data);
    ctx.clearRect(0,0,W,H);
    // Grid lines
    ctx.strokeStyle = 'rgba(192,122,47,0.08)'; ctx.lineWidth = 1;
    for (let i=0;i<4;i++) { const y=p+(H-2*p)*(i/3); ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(W,y); ctx.stroke(); }
    // Line
    ctx.beginPath(); ctx.strokeStyle=color; ctx.lineWidth=2; ctx.lineJoin='round';
    data.forEach((v,i) => {
        const x=(i/(data.length-1||1))*(W-2*p)+p;
        const y=H-p-((v-min)/(max-min||1))*(H-2*p);
        i===0?ctx.moveTo(x,y):ctx.lineTo(x,y);
    });
    ctx.stroke();
    ctx.lineTo(W-p,H-p); ctx.lineTo(p,H-p); ctx.closePath();
    const g=ctx.createLinearGradient(0,0,0,H);
    g.addColorStop(0,color+'44'); g.addColorStop(1,color+'00');
    ctx.fillStyle=g; ctx.fill();
}

// ── Order quantity controls ───────────────────────────────────
function changeQty(btn, delta) {
    const row = btn.closest('.cart-row');
    if (!row) return;
    const input = row.querySelector('.qty-input');
    if (!input) return;
    let val = parseInt(input.value) + delta;
    if (val < 1) val = 1;
    input.value = val;
    updateCartTotals();
}

function updateCartTotals() {
    let total = 0;
    document.querySelectorAll('.cart-row').forEach(row => {
        const price = parseFloat(row.dataset.price || 0);
        const qty   = parseInt(row.querySelector('.qty-input')?.value || 1);
        const sub   = price * qty;
        const subEl = row.querySelector('.row-subtotal');
        if (subEl) subEl.textContent = sub.toFixed(2) + ' DT';
        total += sub;
    });
    const totalEl = document.getElementById('cart-total');
    if (totalEl) totalEl.textContent = total.toFixed(2) + ' DT';
    const btnEl = document.getElementById('place-btn');
    if (btnEl) btnEl.disabled = total <= 0;
}

document.addEventListener('DOMContentLoaded', updateCartTotals);
