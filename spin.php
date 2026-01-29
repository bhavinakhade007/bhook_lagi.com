<?php
// New polished spin wheel
// Read recipes.csv and only include recipes that have a recipe-<id>.php file
// Read recipe source from SQLite DB when available; fall back to CSV for compatibility
$recipes = [];
$dbPath = __DIR__ . DIRECTORY_SEPARATOR . 'recipes.db';
if (file_exists($dbPath)) {
  try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query('SELECT id, name, image FROM recipes ORDER BY id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $id = isset($row['id']) ? $row['id'] : '';
      $name = isset($row['name']) ? $row['name'] : '';
      $image = isset($row['image']) ? $row['image'] : '';
      $page = __DIR__ . '/recipes/recipe-' . $id . '.php';
      if ($id !== '' && file_exists($page)) {
        $recipes[] = ['id' => $id, 'name' => $name, 'image' => $image];
      }
    }
  } catch (Exception $e) {
    // DB read failed — fall back to CSV below
    $recipes = [];
  }
}
// Fallback: if no recipes found (DB missing or empty), use CSV (graceful)
if (count($recipes) === 0) {
  if (($handle = fopen(__DIR__ . '/recipes.csv', 'r')) !== FALSE) {
    $headers = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== FALSE) {
      $id = isset($row[0]) ? $row[0] : '';
      $name = isset($row[1]) ? $row[1] : '';
      $image = isset($row[4]) ? $row[4] : '';
      $page = __DIR__ . '/recipes/recipe-' . $id . '.php';
      if ($id !== '' && file_exists($page)) {
        $recipes[] = ['id'=>$id,'name'=>$name,'image'=>$image];
      }
    }
    fclose($handle);
  }
}
// Fallback: if no recipes found (shouldn't happen), include all rows (graceful)
if (count($recipes) === 0) {
    if (($handle = fopen(__DIR__ . '/recipes.csv', 'r')) !== FALSE) {
        $headers = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== FALSE) {
      $recipes[] = ['id'=>$row[0],'name'=>$row[1],'image'=>$row[4]];
        }
        fclose($handle);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Spin the Wheel — Decide a Recipe</title>
  <style>
    :root{--bg:#f6f8ff;--card:#fff;--muted:#eef2ff;--accent:#6c63ff;--accent2:#7b8cff;--text:#222}
  html,body{height:100%;margin:0;font-family:Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial}
  body{display:flex;align-items:center;justify-content:center;color:var(--text);background: linear-gradient(120deg, #f6d365cc 0%, #fda085cc 100%),
        url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat fixed; margin:0; min-height:100vh}
  .wrap{width:920px;max-width:96%;background: linear-gradient(120deg, #fcf5f5 80%, #f9f9f9 100%);border-radius:18px;padding:22px;box-shadow:0 4px 24px #fda08533;border:2.5px solid;border-image: linear-gradient(90deg, #f6d365 0%, #fda085 100%);border-image-slice:1;text-align:center}
    h1{margin:0 0 12px;font-size:20px;color:var(--accent)}
    .sub{color:#6b7280;margin-bottom:18px}
    .canvas-wrap{position:relative;display:flex;justify-content:center;align-items:center}
    canvas{border-radius:999px;background:linear-gradient(180deg,#fff,#fbfdff);box-shadow:0 12px 40px rgba(16,24,64,0.06)}
    .pointer{position:absolute;top:8px;left:50%;transform:translateX(-50%);z-index:30}
  .pointer .triangle{width:0;height:0;border-left:20px solid transparent;border-right:20px solid transparent;border-bottom:28px solid var(--accent);filter:drop-shadow(0 8px 18px rgba(32,40,80,0.08));border-radius:2px;transition:transform .25s}
  .pointer.spin .triangle{transform:translateY(-4px) rotate(-6deg);animation:pointerWobble .8s infinite}
  @keyframes pointerWobble{0%{transform:translateY(-2px) rotate(-4deg)}50%{transform:translateY(2px) rotate(4deg)}100%{transform:translateY(-2px) rotate(-4deg)}}
    .controls{margin-top:16px;display:flex;gap:12px;justify-content:center}
    .btn{padding:10px 16px;border-radius:10px;border:0;cursor:pointer;font-weight:700}
    .btn.primary{background:linear-gradient(90deg,var(--accent),var(--accent2));color:#fff}
    .btn.ghost{background:transparent;border:1px solid #c09f9f;color:#374151}
    /* floating selected preview */
  .preview{margin-top:14px;display:flex;align-items:center;justify-content:center;gap:12px}
  .preview .img{width:100px;height:100px;border-radius:12px;overflow:hidden;box-shadow:0 12px 30px rgba(16,24,64,0.06);transition:transform .28s}
  .preview.pulse .img{transform:scale(1.06)}
  /* pop animation for preview and modal images */
  @keyframes popScale{0%{transform:scale(.8);opacity:0}60%{transform:scale(1.08);opacity:1}100%{transform:scale(1);opacity:1}}
  .pop-scale{animation:popScale 420ms cubic-bezier(.2,.9,.25,1) both}
  .preview img{width:100%;height:100%;object-fit:cover}
  /* confetti */
  .confetti{position:fixed;width:10px;height:14px;z-index:9999;pointer-events:none;opacity:0;transform-origin:center;animation:confettiFall 1400ms linear forwards}
  @keyframes confettiFall{0%{opacity:1;transform:translateY(-20vh) rotate(0deg)}100%{opacity:1;transform:translateY(100vh) rotate(540deg)}}
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(6,10,20,0.5);z-index:400}
    .modal.show{display:flex}
  .modal-card{background: linear-gradient(120deg, #f3ecec 80%, #fffdfd 100%);padding:18px;border-radius:12px;max-width:480px;width:92%;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,0.12);border:2px solid;border-image: linear-gradient(90deg,#f6d365 0%, #fda085 100%);border-image-slice:1}
    .modal-card img{width:260px;height:160px;object-fit:cover;border-radius:10px}
    .modal-actions{display:flex;gap:10px;justify-content:center;margin-top:12px}
    @media (max-width:640px){.preview .img{width:84px;height:84px}.modal-card img{width:220px;height:140px}}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Can't decide? Let the wheel choose 🎡</h1>
    <div class="sub">Tap spin and watch the wheel — a delicious recipe will be picked for you.</div>

    <div class="canvas-wrap">
      <div class="pointer" aria-hidden="true"><div class="triangle"></div></div>
      <canvas id="wheelCanvas" width="640" height="640"></canvas>
    </div>

    <div class="controls">
      <button id="spinBtn" class="btn primary">Spin</button>
      <button id="againBtn" class="btn ghost">Reset</button>
    </div>

    <div class="preview" id="previewArea" style="visibility:hidden">
      <div class="img"><img id="previewImg" src="" alt=""></div>
      <div class="label"><strong id="previewName"></strong></div>
    </div>

  </div>

  <div id="resultModal" class="modal" role="dialog" aria-hidden="true">
    <div class="modal-card">
      <h3 id="modalTitle">Selected</h3>
      <img id="modalImage" src="" alt="">
      <div style="margin-top:10px;font-weight:700" id="modalName"></div>
      <div class="modal-actions">
        <button id="goRecipe" class="btn primary">Go to Recipe</button>
        <button id="closeModal" class="btn ghost">Close</button>
      </div>
    </div>
  </div>

  <script>
  // Recipes provided by PHP (filtered to pages that exist)
  const RECIPES = <?php echo json_encode($recipes, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP); ?>;

  // Canvas wheel implementation
  const canvas = document.getElementById('wheelCanvas');
  const ctx = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;
  const cx = W/2, cy = H/2, radius = Math.min(W,H)*0.42;
  const pointerEl = document.querySelector('.pointer');

  // --- WebAudio spin sound helpers (no external files required) ---
  let audioCtx = null;
  let masterGain = null;
  let spinSound = null;
  // set loud default volume
  let MASTER_VOLUME = 1.0; // 0.0 .. 1.0
  function ensureAudioCtx(){ 
    if(!audioCtx){ 
      audioCtx = new (window.AudioContext || window.webkitAudioContext)(); 
      // master gain to control overall volume (spin + win chime)
      masterGain = audioCtx.createGain();
      masterGain.gain.setValueAtTime(MASTER_VOLUME, audioCtx.currentTime);
      masterGain.connect(audioCtx.destination);
    } 
  }

  function createSpinSound(){
    // Replace the harsh sawtooth with a percussive "wood" click engine.
    ensureAudioCtx();
    let lastTrigger = audioCtx.currentTime;
    // create a short noise-buffered click
    function makeClick(){
      const t0 = audioCtx.currentTime;
      const len = Math.max(0.012, 0.02);
      const buffer = audioCtx.createBuffer(1, Math.floor(audioCtx.sampleRate * len), audioCtx.sampleRate);
      const data = buffer.getChannelData(0);
      // fill with short noise shaped by decay to sound like a wooden thud
      for(let i=0;i<data.length;i++){
        // exponential decay envelope applied to white noise
        const env = Math.exp(-6 * i / data.length);
        data[i] = (Math.random()*2 - 1) * 0.6 * env;
      }
      const src = audioCtx.createBufferSource();
      src.buffer = buffer;
      // gentle lowpass to remove harsh high freq
      const lp = audioCtx.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.setValueAtTime(2400, t0);
      // small bandpass to add woody character
      const bp = audioCtx.createBiquadFilter(); bp.type = 'bandpass'; bp.frequency.setValueAtTime(800, t0); bp.Q.setValueAtTime(0.8, t0);
  const g = audioCtx.createGain(); g.gain.setValueAtTime(0.0001, t0);
  src.connect(lp); lp.connect(bp); bp.connect(g); g.connect(masterGain || audioCtx.destination);
      // quick attack and decay
      g.gain.linearRampToValueAtTime(0.12, t0 + 0.002);
      g.gain.exponentialRampToValueAtTime(0.001, t0 + 0.18);
      src.start(t0);
      src.stop(t0 + 0.22);
    }

    let running = true;
    let currentClickRate = 2; // clicks per second

    return {
      update(freq){
        // map incoming 'freq' value to click rate (rough mapping)
        // freq ~ 400..3400 -> clickRate ~ 1..28
        const rate = Math.max(0.5, Math.min(28, 1 + (freq - 200)/120));
        currentClickRate = rate;
        const now = audioCtx.currentTime;
        const interval = 1 / currentClickRate;
        if(now - lastTrigger >= interval){
          lastTrigger = now;
          makeClick();
        }
      },
      stop(){ running = false; }
    };
  }

  function playWinSound(){
    ensureAudioCtx();
    const t0 = audioCtx.currentTime;
    // two soft pitched knocks to resemble wood + soft bell
    const makeTone = (freq, dur, gainTarget, delay)=>{
      const o = audioCtx.createOscillator(); const g = audioCtx.createGain();
      o.type = 'triangle'; o.frequency.setValueAtTime(freq, t0 + delay);
      g.gain.setValueAtTime(0.0001, t0 + delay);
      o.connect(g); g.connect(masterGain || audioCtx.destination);
      g.gain.linearRampToValueAtTime(gainTarget, t0 + delay + 0.005);
      g.gain.exponentialRampToValueAtTime(0.0001, t0 + delay + dur);
      o.start(t0 + delay); o.stop(t0 + delay + dur + 0.02);
    };
    makeTone(420, 0.16, 0.08, 0);
    makeTone(640, 0.18, 0.06, 0.08);
  }

  // preloader for images
  const images = {};
  let loaded = 0;
  const total = RECIPES.length;

  /* volume UI removed per user request — audio controlled at full volume by default */

  function preloadAll(cb){
    if(total === 0){ cb(); return; }
    RECIPES.forEach((r, i)=>{
      const img = new Image();
      img.src = r.image ? ('images/'+r.image) : '';
      img.onload = ()=>{ images[r.id]=img; loaded++; if(loaded>=total) cb(); };
      img.onerror = ()=>{ images[r.id]=null; loaded++; if(loaded>=total) cb(); };
    });
  }

  // draw wheel sectors
  function drawWheel(angle, highlightIndex){
    ctx.clearRect(0,0,W,H);
    const n = RECIPES.length;
    const slice = (Math.PI*2)/n;
    // curated palette (warm & complementary tones to match site)
    const PALETTE = ['#f6d365', '#fda085', '#ff9f1c', '#6c63ff', '#8a7bff', '#43e97b', '#ffd166', '#ff6b6b'];
    for(let i=0;i<n;i++){
      const start = angle + i*slice;
      const end = start + slice;
      // nice palette
      ctx.beginPath();
      ctx.moveTo(cx,cy);
      ctx.arc(cx,cy,radius,start,end,false);
      ctx.closePath();
      const color = PALETTE[i % PALETTE.length];
      ctx.fillStyle = color;
      ctx.fill();
      // draw inner rim for separation
      ctx.strokeStyle = 'rgba(255,255,255,0.06)';
      ctx.lineWidth = 2;
      ctx.stroke();

  // draw circular thumbnail on the rim
      const mid = (start+end)/2;
      const thumbR = 44; // radius of thumb
      const tx = cx + Math.cos(mid)*(radius - thumbR - 8);
      const ty = cy + Math.sin(mid)*(radius - thumbR - 8);
      ctx.save();
      // circular clip
      ctx.beginPath();
      ctx.arc(tx,ty,thumbR,0,Math.PI*2);
      ctx.closePath();
      ctx.clip();
      const r = RECIPES[i];
      const img = images[r.id];
      if(img){
        // draw centered
        ctx.drawImage(img, tx-thumbR, ty-thumbR, thumbR*2, thumbR*2);
      } else {
        // placeholder
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        ctx.fillRect(tx-thumbR, ty-thumbR, thumbR*2, thumbR*2);
      }
      ctx.restore();
      // highlight glow if this is the highlighted index
      if(typeof highlightIndex !== 'undefined' && highlightIndex % n === i){
        ctx.beginPath();
        ctx.arc(tx,ty,thumbR+8,0,Math.PI*2);
        ctx.fillStyle = 'rgba(255,255,255,0.06)';
        ctx.fill();
      }

  // labels removed for cleaner look (names not drawn on wheel)
    }
  // center circle (transparent so card background shows through)
  ctx.beginPath();
  ctx.arc(cx,cy, radius*0.34,0,Math.PI*2);
  ctx.fillStyle = 'transparent';
  ctx.fill();
  ctx.lineWidth = 6; ctx.strokeStyle = 'rgba(16,24,64,0.04)'; ctx.stroke();
    // center text
    ctx.fillStyle = '#111'; ctx.font = '800 16px Inter, Arial'; ctx.textAlign = 'center'; ctx.fillText('SPIN', cx, cy+6);
  }

  // angular animation
  let anim = null; let currentAngle = 0;
  function indexFromAngle(angle, n){
    const slice = (Math.PI*2)/n;
    // normalize so 0..2PI where 0 corresponds to the top (-PI/2 in wheel coordinates)
    const normalized = ((Math.PI/2 - angle) % (Math.PI*2) + Math.PI*2) % (Math.PI*2);
    return Math.floor(normalized / slice) % n;
  }

  function spinTo(index){
    const n = RECIPES.length;
    const slice = (Math.PI*2)/n;
    // choose target so that chosen slice lands at top ( -Math.PI/2 )
    const targetMid = index*slice + slice/2;
    // normalize
    const rotations = 6;
    const targetAngle = rotations*Math.PI*2 + (Math.PI*2 - targetMid) + Math.PI/2;
    const start = currentAngle % (Math.PI*2);
    const duration = 4200;
    const startTime = performance.now();
  if(anim) cancelAnimationFrame(anim);
  // add pointer animation
  pointerEl.classList.add('spin');
  previewArea.classList.add('pulse');
  // start spin sound (resume AudioContext on user gesture)
  try{ ensureAudioCtx(); if(audioCtx.state === 'suspended') audioCtx.resume(); }catch(e){}
  if(spinSound){ try{ spinSound.stop(); }catch(e){} spinSound = null; }
  spinSound = createSpinSound();
  let prevAngle = currentAngle;
    function frame(t){
      const p = Math.min(1, (t-startTime)/duration);
      // easeOutCubic
      const ease = 1 - Math.pow(1-p,3);
      currentAngle = start + (targetAngle - start)*ease;
  // update spin sound frequency based on angular velocity
  if(spinSound){
    const vel = Math.abs(currentAngle - prevAngle);
    const freq = 420 + Math.min(3000, vel * 12000);
    spinSound.update(freq);
    prevAngle = currentAngle;
  }
  // compute highlighted index based on angle (which slice is at top)
  const highlighted = indexFromAngle(currentAngle, n);
  drawWheel(currentAngle, highlighted);
  updatePreview(highlighted);
      if(p < 1) anim = requestAnimationFrame(frame);
      else {
        // small bounce
        const bounceStart = performance.now();
        const bounceDur = 900;
        function bounceLoop(bt){
          const q = Math.min(1,(bt-bounceStart)/bounceDur);
          const bease = Math.sin(q*Math.PI);
          const bounceAngle = currentAngle + bease* (Math.PI/36);
          // show intermediate bounce highlight
          const bIndex = indexFromAngle(bounceAngle, n);
          drawWheel(bounceAngle, bIndex);
          if(q<1) requestAnimationFrame(bounceLoop);
          else {
            // finalize
            currentAngle = targetAngle;
            const finalIndex = indexFromAngle(currentAngle, n);
            drawWheel(currentAngle, finalIndex);
            // stop pointer/pulse
            pointerEl.classList.remove('spin');
            previewArea.classList.remove('pulse');
            // stop spin sound and play win chime
            try{ if(spinSound){ spinSound.stop(); spinSound = null; } }catch(e){}
            try{ playWinSound(); }catch(e){}
            showResult(finalIndex);
          }
        }
        requestAnimationFrame(bounceLoop);
      }
    }
    anim = requestAnimationFrame(frame);
  }

  // preview update: show a larger clear image and name of index
  const previewArea = document.getElementById('previewArea');
  const previewImg = document.getElementById('previewImg');
  const previewName = document.getElementById('previewName');
  function updatePreview(i){
    const r = RECIPES[(i+RECIPES.length)%RECIPES.length];
    if(!r) return;
    if(images[r.id]) previewImg.src = images[r.id].src; else previewImg.src = '';
    previewName.textContent = r.name;
    previewArea.style.visibility = 'visible';
    // animate pop on preview image
    previewImg.classList.remove('pop-scale');
    // force reflow to restart animation
    void previewImg.offsetWidth;
    previewImg.classList.add('pop-scale');
    setTimeout(()=>{ previewImg.classList.remove('pop-scale'); }, 520);
  }

  // show modal with final selection
  const modal = document.getElementById('resultModal');
  const modalImg = document.getElementById('modalImage');
  const modalName = document.getElementById('modalName');
  const modalTitle = document.getElementById('modalTitle');
  const goRecipe = document.getElementById('goRecipe');
  const closeModal = document.getElementById('closeModal');
  let chosenIndex = null;
  function showResult(i){
    chosenIndex = i;
    const r = RECIPES[i];
    modalImg.src = images[r.id] ? images[r.id].src : '';
    modalName.textContent = r.name;
    modalTitle.textContent = 'You got:';
    modal.classList.add('show');
    // confetti burst
    confettiBurst();
    // pop animate modal image
    modalImg.classList.remove('pop-scale');
    void modalImg.offsetWidth;
    modalImg.classList.add('pop-scale');
    setTimeout(()=>{ modalImg.classList.remove('pop-scale'); }, 620);
  }

  // small confetti burst
  function confettiBurst(){
    const colors = ['#f6d365','#fda085','#ff9f1c','#6c63ff','#43e97b','#ff6b6b'];
    for(let i=0;i<22;i++){
      const el = document.createElement('div');
      el.className = 'confetti';
      el.style.background = colors[i % colors.length];
      el.style.left = (50 + (Math.random()*40-20)) + '%';
      el.style.top = (40 + Math.random()*10) + '%';
      el.style.transform = `translate(-50%,-50%) rotate(${Math.random()*360}deg)`;
      el.style.opacity = '1';
      document.body.appendChild(el);
      setTimeout(()=>{ el.remove(); }, 1700 + Math.random()*800);
    }
  }

  document.getElementById('spinBtn').addEventListener('click', ()=>{
    if(RECIPES.length === 0) return;
    // pick random index and spin
    const idx = Math.floor(Math.random()*RECIPES.length);
    spinTo(idx);
  });

  document.getElementById('againBtn').addEventListener('click', ()=>{
    // reset view
    previewArea.style.visibility = 'hidden';
    modal.classList.remove('show');
    try{ if(spinSound){ spinSound.stop(); spinSound = null; } }catch(e){}
  });

  goRecipe.addEventListener('click', ()=>{
    if(chosenIndex === null) return;
    const id = RECIPES[chosenIndex].id;
  window.location.href = 'recipes/recipe-'+id+'.php';
  });
  closeModal.addEventListener('click', ()=>{ modal.classList.remove('show'); });

  // volume UI removed — no DOM bindings

  // initial preload and draw
  preloadAll(()=>{ drawWheel(0); });
  </script>
</body>
</html>
