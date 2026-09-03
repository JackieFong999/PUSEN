@php
    $isPdf = $ext === 'pdf';
    $isImage = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Document Preview - {{ config('app.name', 'PolyU SEN Data Bank') }}</title>
<style>
  :root {
    --bg: #10141d;
    --panel: #1a2130;
    --border: #2b3547;
    --text: #e8ecf4;
    --muted: #9aa2b5;
    --accent: #9B2331;
  }
  * { box-sizing: border-box; }
  html, body { height: 100%; }
  body {
    margin: 0;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    user-select: none;
    -webkit-user-select: none;
  }
  header {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .6rem 1rem;
    background: var(--panel);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
  }
  header .brand { font-weight: 700; font-size: .95rem; color: var(--accent); white-space: nowrap; }
  header .sep { color: var(--border); }
  header .docname {
    font-size: .85rem;
    color: var(--muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 1;
  }
  header .note { font-size: .72rem; color: var(--muted); white-space: nowrap; }
  header button, footer button {
    background: #232c3d;
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 8px;
    padding: .35rem .8rem;
    font-size: .8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
  }
  header button:hover, footer button:hover { background: #2d3850; border-color: var(--accent); }
  header button.close { background: var(--accent); border-color: var(--accent); }
  header button.close:hover { background: #b83a4a; }
  main {
    flex: 1;
    overflow: auto;
    position: relative;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 1.25rem;
    background:
      linear-gradient(45deg, rgba(255,255,255,.015) 25%, transparent 25%, transparent 75%, rgba(255,255,255,.015) 75%),
      linear-gradient(45deg, rgba(255,255,255,.015) 25%, transparent 25%, transparent 75%, rgba(255,255,255,.015) 75%);
    background-size: 28px 28px;
    background-position: 0 0, 14px 14px;
  }
  main .canvas-wrap { display: inline-block; }
  main canvas {
    display: block;
    background: #fff;
    box-shadow: 0 6px 24px rgba(0,0,0,.5);
    margin-bottom: .9rem;
    border-radius: 2px;
  }
  #imageViewer { max-width: 100%; box-shadow: 0 6px 24px rgba(0,0,0,.5); border-radius: 2px; }
  #statusBox, #errorBox {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.4rem 2rem;
    text-align: center;
    font-size: .9rem;
    color: var(--muted);
    max-width: 80%;
  }
  #errorBox { border-color: var(--accent); color: #f0b3bc; }
  .spinner {
    width: 28px; height: 28px;
    border: 3px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    margin: 0 auto .8rem;
    animation: spin 1s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    padding: .5rem 1rem;
    background: var(--panel);
    border-top: 1px solid var(--border);
    flex-shrink: 0;
    font-size: .8rem;
    color: var(--muted);
  }
  footer .pageinfo { min-width: 90px; text-align: center; font-variant-numeric: tabular-nums; }
  footer .zoominfo { min-width: 56px; text-align: center; font-variant-numeric: tabular-nums; }
  footer button:disabled { opacity: .35; cursor: not-allowed; }
  /* printing the preview is blocked: hide the whole page on paper */
  @media print {
    body { display: none !important; }
  }
</style>
</head>
<body>
<header>
  <span class="brand">SEN Document Preview</span>
  <span class="sep">|</span>
  <span class="docname" title="{{ $original }}">{{ $original }}</span>
  <span class="note"><i>&#128274;</i> Preview only — downloading &amp; printing are disabled</span>
  <button type="button" class="close" id="closeBtn">&#10005; Close</button>
</header>

<main id="main">
  <div id="statusBox"><div class="spinner"></div>Loading document&hellip;</div>
  <div id="errorBox" style="display:none;"></div>
  <img id="imageViewer" alt="" style="display:none;">
  <div id="pdfPages" style="display:none;"></div>
</main>

<footer id="toolbar" style="display:none;">
  <button type="button" id="prevBtn" title="Previous page">&#9664; Prev</button>
  <span class="pageinfo" id="pageInfo">- / -</span>
  <button type="button" id="nextBtn" title="Next page">Next &#9654;</button>
  <span style="opacity:.4;">|</span>
  <button type="button" id="zoomOutBtn" title="Zoom out">&#8722;</button>
  <span class="zoominfo" id="zoomInfo">100%</span>
  <button type="button" id="zoomInBtn" title="Zoom in">+</button>
  <button type="button" id="fitBtn" title="Fit width">Fit width</button>
</footer>

<script type="module" nonce="{{ $cspNonce }}">
  import * as pdfjsLib from '/vendor/pdfjs/pdf.min.mjs';
  pdfjsLib.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.min.mjs';

  const FILENAME = @js($filename);
  const ORIGINAL = @js($original);
  const EXT      = @js($ext);
  const IS_PDF   = {{ $isPdf ? 'true' : 'false' }};
  const IS_IMAGE = {{ $isImage ? 'true' : 'false' }};

  const mainEl   = document.getElementById('main');
  const statusEl = document.getElementById('statusBox');
  const errorEl  = document.getElementById('errorBox');
  const imgEl    = document.getElementById('imageViewer');
  const pagesEl  = document.getElementById('pdfPages');
  const toolbar  = document.getElementById('toolbar');
  const pageInfo = document.getElementById('pageInfo');
  const zoomInfo = document.getElementById('zoomInfo');

  let pdfDoc = null;
  let currentPage = 1;
  let scale = 1.2;

  function showError(msg) {
    statusEl.style.display = 'none';
    imgEl.style.display = 'none';
    pagesEl.style.display = 'none';
    toolbar.style.display = 'none';
    errorEl.style.display = 'block';
    errorEl.innerHTML = '<div style="font-size:1.6rem;margin-bottom:.5rem;">&#128196;</div>' + msg;
  }

  // ---------- print / download protection ----------
  window.addEventListener('beforeprint', (e) => e.preventDefault());
  window.addEventListener('afterprint', (e) => e.preventDefault());
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) e.preventDefault();
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) e.preventDefault();
  });
  document.addEventListener('contextmenu', (e) => e.preventDefault());

  // ---------- fetch the raw bytes (same-origin, authenticated) ----------
  fetch('/admin/sen-doc/' + encodeURIComponent(FILENAME) + '?raw=1', { credentials: 'same-origin' })
    .then(async (res) => {
      if (!res.ok) {
        if (res.status === 403) throw new Error('Access denied.');
        if (res.status === 404) throw new Error('Document not found.');
        throw new Error('Failed to load the document (HTTP ' + res.status + ').');
      }
      const blob = await res.blob();

      if (IS_IMAGE) {
        const url = URL.createObjectURL(blob);
        imgEl.src = url;
        imgEl.style.display = 'block';
        statusEl.style.display = 'none';
        imgEl.onerror = () => { URL.revokeObjectURL(url); showError('Could not display this image.'); };
        return;
      }

      if (IS_PDF) {
        const data = new Uint8Array(await blob.arrayBuffer());
        pdfDoc = await pdfjsLib.getDocument({
          data,
          cMapUrl: '/vendor/pdfjs/cmaps/',
          cMapPacked: true,
        }).promise;
        statusEl.style.display = 'none';
        pagesEl.style.display = 'block';
        toolbar.style.display = 'flex';
        pageInfo.textContent = '1 / ' + pdfDoc.numPages;
        await renderPage(1);
        return;
      }

      showError('Preview is not available for this file type.<br>Use the <b>Download</b> button in the SEN form instead.');
    })
    .catch((err) => showError(err.message || 'Failed to load the document.'));

  // ---------- PDF rendering ----------
  async function renderPage(n) {
    currentPage = n;
    const page = await pdfDoc.getPage(n);
    const base = page.getViewport({ scale: 1 });
    scale = Math.min(1.6, (mainEl.clientWidth - 40) / base.width);
    await renderCurrent();
  }

  let renderTask = null;
  async function renderCurrent() {
    if (!pdfDoc) return;
    const n = currentPage;
    const page = await pdfDoc.getPage(n);
    const viewport = page.getViewport({ scale });

    // single canvas per page, one page visible at a time
    pagesEl.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.className = 'canvas-wrap';
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    wrap.appendChild(canvas);
    pagesEl.appendChild(wrap);

    if (renderTask) { try { renderTask.cancel(); } catch {} }
    renderTask = page.render({ canvasContext: canvas.getContext('2d'), viewport });
    await renderTask.promise;
    renderTask = null;
    pageInfo.textContent = currentPage + ' / ' + pdfDoc.numPages;
    zoomInfo.textContent = Math.round(scale * 100) + '%';
    mainEl.scrollTop = 0;
    document.getElementById('prevBtn').disabled = currentPage <= 1;
    document.getElementById('nextBtn').disabled = currentPage >= pdfDoc.numPages;
  }

  document.getElementById('prevBtn').addEventListener('click', () => {
    if (currentPage > 1) { currentPage--; renderCurrent(); }
  });
  document.getElementById('nextBtn').addEventListener('click', () => {
    if (currentPage < pdfDoc.numPages) { currentPage++; renderCurrent(); }
  });
  document.getElementById('zoomInBtn').addEventListener('click', () => {
    scale = Math.min(scale + 0.25, 4); renderCurrent();
  });
  document.getElementById('zoomOutBtn').addEventListener('click', () => {
    scale = Math.max(scale - 0.25, 0.25); renderCurrent();
  });
  document.getElementById('fitBtn').addEventListener('click', async () => {
    if (!pdfDoc) return;
    const page = await pdfDoc.getPage(currentPage);
    const base = page.getViewport({ scale: 1 });
    scale = Math.max(0.3, (mainEl.clientWidth - 40) / base.width);
    renderCurrent();
  });

  // ---------- close ----------
  document.getElementById('closeBtn').addEventListener('click', () => {
    window.close();
    setTimeout(() => { window.location.href = '/admin/sen-search'; }, 150);
  });
</script>
</body>
</html>
