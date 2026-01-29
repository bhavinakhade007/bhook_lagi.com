<?php
// Shared snippet to add AI adapt/detect UI and JS to recipe pages
?>

<!-- AI Adapt and Detect UI -->
<div style="margin-top:18px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
    <button id="aiAdaptBtn_snippet" style="display:inline-block;padding:10px 18px;background:#ff7a5c;color:#fff;border:none;border-radius:6px;font-size:0.95rem;font-weight:700;cursor:pointer;box-shadow:0 2px 8px #ff7a5c55;">Adapt with AI</button>
    <button id="aiDetectBtn_snippet" style="display:inline-block;padding:10px 18px;background:linear-gradient(90deg,#ffb86b,#ff7a5c);color:#fff;border:none;border-radius:6px;font-size:0.95rem;font-weight:700;cursor:pointer;box-shadow:0 2px 8px #ff7a5c55;">Detect Ingredient</button>
</div>
<div id="aiStatus_snippet" style="margin-top:12px;text-align:center;font-size:0.9rem;color:#065f46;"></div>

<!-- Modal for AI Adaptation -->
<div id="aiModal_snippet" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:24px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto;box-shadow:0 4px 24px rgba(0,0,0,0.3);">
        <h3 style="margin-top:0;color:#f76b1c;">Adapt Recipe</h3>
        <label for="aiTarget_snippet" style="display:block;margin-top:12px;font-weight:600;">Adaptation Type:</label>
        <select id="aiTarget_snippet" style="width:100%;padding:8px;margin-top:6px;border-radius:6px;border:1px solid #ddd;font-size:0.95rem;">
            <option value="vegan">Vegan</option>
            <option value="gluten-free">Gluten-Free</option>
            <option value="low-sodium">Low Sodium</option>
            <option value="healthier">Make Healthier</option>
        </select>
        <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
            <button id="aiCancel_snippet" style="padding:8px 12px;border-radius:8px;border:none;background:#ddd;cursor:pointer;">Cancel</button>
            <button id="aiSave_snippet" style="padding:8px 12px;border-radius:8px;border:none;background:#6c63ff;color:#fff;cursor:pointer;">Save as New</button>
            <button id="aiRun_snippet" style="padding:8px 12px;border-radius:8px;border:none;background:#43e97b;color:#fff;cursor:pointer;">Run</button>
        </div>
        <div id="aiResult_snippet" style="margin-top:12px;white-space:pre-wrap;display:none;background:#f9fafb;padding:8px;border-radius:8px"></div>
    </div>
</div>

<script>
(function(){
    // Avoid duplicate bindings when snippet used multiple times
    if (window._recipe_ai_snippet_installed) return; window._recipe_ai_snippet_installed = true;

    // Detect if in recipes folder to compute base path for endpoints
    const basePath = (window.location.pathname.indexOf('/recipes/') !== -1) ? '../' : '';
    const adaptBtn = document.getElementById('aiAdaptBtn') || document.getElementById('aiAdaptBtn_snippet');
    const detectBtn = document.getElementById('aiDetectBtn_snippet');
    const modal = document.getElementById('aiModal') || document.getElementById('aiModal_snippet');
    const cancel = document.getElementById('aiCancel') || document.getElementById('aiCancel_snippet');
    const run = document.getElementById('aiRun') || document.getElementById('aiRun_snippet');
    const targetSel = document.getElementById('aiTarget') || document.getElementById('aiTarget_snippet');
    const resDiv = document.getElementById('aiResult') || document.getElementById('aiResult_snippet');
    const saveBtn = document.getElementById('aiSave') || document.getElementById('aiSave_snippet');
    const statusEl = document.getElementById('aiStatus') || document.getElementById('aiStatus_snippet');

    const img = document.querySelector('img.recipe-img');
    const pageTitle = document.querySelector('h1') ? document.querySelector('h1').textContent.trim() : '';
    // find ingredients: look for a header 'Ingredients:' and next p or list
    let ingredients = '';
    const headers = Array.from(document.querySelectorAll('h3'));
    for (const h of headers) {
        if (h.textContent.toLowerCase().includes('ingredient')) {
            const next = h.nextElementSibling;
            if (next) {
                if (next.tagName.toLowerCase() === 'p') ingredients = next.textContent.trim();
                else if (next.tagName.toLowerCase() === 'ul' || next.tagName.toLowerCase() === 'ol') {
                    ingredients = Array.from(next.querySelectorAll('li')).map(li=>li.textContent.trim()).join(', ');
                }
            }
            break;
        }
    }
    // steps: collect ol li text content
    const stepsList = document.querySelectorAll('ol li');
    const steps = Array.from(stepsList).map(li=>li.textContent.trim()).join('\n');

    // Open/close modal
    if (adaptBtn) adaptBtn.addEventListener('click', ()=>{ modal.style.display='flex'; resDiv.style.display='none'; resDiv.textContent=''; });
    if (cancel) cancel.addEventListener('click', ()=>{ modal.style.display='none'; });

    // Run adaptation
    if (run) run.addEventListener('click', async ()=>{
        resDiv.style.display='block'; resDiv.textContent = 'Adapting...';
        const target = targetSel.value;
        try {
            const fd = new FormData(); fd.append('target', target); fd.append('name', pageTitle); fd.append('ingredients', ingredients); fd.append('steps', steps);
            const r = await fetch(basePath + 'ai_adapt.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const text = await r.text();
            let j = null;
            try { j = JSON.parse(text); } catch(e) { j = null; }
            if (!r.ok) {
                if (r.status === 401) {
                    // build safe message with link
                    resDiv.textContent = '';
                    const m = document.createElement('span'); m.textContent = 'Authentication required. Please ';
                    const link = document.createElement('a'); link.href = basePath + 'login.php'; link.textContent = 'log in';
                    m.appendChild(link);
                    m.appendChild(document.createTextNode(' to use AI features.'));
                    resDiv.appendChild(m);
                } else {
                    // avoid inserting raw server HTML — show plain text and hide HTML pages
                    const safeText = (text && /<\s*!?doctype|<html/i.test(text)) ? 'Server returned an unexpected error page.' : (text || '');
                    resDiv.textContent = 'Server error: ' + r.status + ' ' + r.statusText + (safeText ? '\n' + safeText : '');
                }
            } else if (j && j.ok && j.adapted) {
                const a = j.adapted;
                // build safe content nodes rather than using innerHTML
                resDiv.textContent = '';
                const title = document.createElement('div'); const b = document.createElement('b'); b.textContent = a.name; title.appendChild(b);
                const ingr = document.createElement('pre'); ingr.style.whiteSpace = 'pre-wrap'; ingr.style.marginTop = '8px'; ingr.textContent = 'Ingredients:\n' + a.ingredients;
                const stepsEl = document.createElement('pre'); stepsEl.style.whiteSpace = 'pre-wrap'; stepsEl.style.marginTop = '8px'; stepsEl.textContent = 'Steps:\n' + a.steps;
                resDiv.appendChild(title); resDiv.appendChild(ingr); resDiv.appendChild(stepsEl);
            } else {
                resDiv.textContent = 'Error: ' + (j ? (j.error || j.note || JSON.stringify(j)) : 'Unknown response');
            }
        } catch (e) { resDiv.textContent = 'Network error: ' + (e && e.message ? e.message : e); }
    });

    // Save adapted result as a new recipe via add_recipe prefill
    if (saveBtn) saveBtn.addEventListener('click', ()=>{
        let adaptedText = resDiv.textContent || resDiv.innerText || '';
        const nameMatch = adaptedText.match(/^(.*?)\n\nIngredients:/s);
        const ingredientsMatch = adaptedText.match(/Ingredients:\n([\s\S]*?)\n\nSteps:/i);
        const stepsMatch = adaptedText.match(/Steps:\n([\s\S]*)$/i);
        const nameVal = nameMatch ? nameMatch[1].trim() : pageTitle;
        const ingrVal = ingredientsMatch ? ingredientsMatch[1].trim() : ingredients;
        const stepsVal = stepsMatch ? stepsMatch[1].trim() : steps;
        const qs = new URLSearchParams({ prefill_name: nameVal, prefill_ingredients: ingrVal, prefill_steps: stepsVal }).toString();
        window.location.href = basePath + 'add_recipe.php?' + qs;
    });

    // Image detect: fetch the recipe image and send as FormData
    if (detectBtn) detectBtn.addEventListener('click', async ()=>{
        // if page image is missing, open file dialog as fallback
        if (!img) { if (statusEl) statusEl.textContent = 'No recipe image found on page.'; return; }
        try {
            const response = await fetch(img.src, {mode: 'cors', credentials: 'same-origin'});
            if (!response.ok) {
                if (statusEl) { statusEl.textContent = 'Could not fetch image for detection: ' + response.status + ' ' + response.statusText; statusEl.style.color = '#b91c1c'; }
                // fallback to uploading a file
                promptUploadAndDetect();
                return;
            }
            const blob = await response.blob();
            const fd = new FormData();
            fd.append('image', blob, 'recipe.jpg');
            const r = await fetch(basePath + 'ai_image_parse.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const text = await r.text();
            let j = null; try { j = JSON.parse(text); } catch(e) { j = null; }
            if (!r.ok) {
                if (r.status === 401) {
                    if (statusEl) { statusEl.textContent = 'Authentication required. Please log in to use AI features.'; statusEl.style.color = '#b91c1c'; }
                    else alert('Authentication required. Please log in to use AI features.');
                } else {
                    const safeText = (text && /<\s*!?doctype|<html/i.test(text)) ? 'Server returned an unexpected error page.' : (text || '');
                    if (statusEl) { statusEl.textContent = 'Server error: ' + r.status + ' ' + r.statusText + (safeText ? '\n' + safeText : ''); statusEl.style.color = '#b91c1c'; }
                    else alert('Server error: ' + r.status + ' ' + r.statusText + '\n' + (safeText||''));
                }
            } else if (j && j.ok) {
                // handle confidence before updating page
                const confidence = (typeof j.confidence === 'number') ? j.confidence : parseFloat(j.confidence || 0);
                const ingredientsP = (() => {
                    const headers = Array.from(document.querySelectorAll('h3'));
                    for (const h of headers) if (h.textContent.toLowerCase().includes('ingredient')) return h.nextElementSibling;
                    return null;
                })();
                if (confidence >= 0.5) {
                    if (ingredientsP) ingredientsP.textContent = j.ingredients;
                    if (statusEl) { statusEl.textContent = 'Detected: ' + j.ingredients + (j.note ? ' — ' + j.note : ''); statusEl.style.color = '#065f46'; }
                } else {
                    if (statusEl) {
                        statusEl.textContent = '';
                        const txt = document.createTextNode('Suggested: ' + j.ingredients + (j.note ? ' — ' + j.note : ''));
                        statusEl.appendChild(txt);
                        const apply = document.createElement('button'); apply.textContent = 'Apply';
                        apply.style.marginLeft = '8px'; apply.style.padding = '4px 8px'; apply.style.borderRadius = '6px'; apply.style.background = '#6c63ff'; apply.style.color = '#fff'; apply.style.border = 'none'; apply.style.cursor = 'pointer';
                        apply.addEventListener('click', ()=>{ if (ingredientsP) ingredientsP.textContent = j.ingredients; statusEl.textContent = 'Applied suggestion.'; statusEl.style.color = '#065f46'; });
                        statusEl.appendChild(apply);
                        statusEl.style.color = '#b45309';
                    }
                }
            } else {
                if (statusEl) { statusEl.textContent = 'Detect error: ' + (j ? (j.error || j.note || JSON.stringify(j)) : 'Unknown'); statusEl.style.color = '#b91c1c'; }
            }
        } catch (e) {
            if (statusEl) { statusEl.textContent = 'Network error during image detection: ' + (e.message || e); statusEl.style.color = '#b91c1c'; }
            // attempt to prompt a local upload as fallback
            promptUploadAndDetect();
        }
    });

    function promptUploadAndDetect() {
        const fi = document.createElement('input'); fi.type = 'file'; fi.accept = 'image/*';
        fi.addEventListener('change', async ()=>{
            if (!fi.files || fi.files.length === 0) {
                if (statusEl) { statusEl.textContent = 'No file selected.'; statusEl.style.color = '#b91c1c'; }
                return;
            }
            const file = fi.files[0];
            const fd = new FormData(); fd.append('image', file);
            try {
                const r = await fetch(basePath + 'ai_image_parse.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                const text = await r.text(); let j = null; try { j = JSON.parse(text); } catch(e) { j = null; }
                if (!r.ok) {
                    if (r.status === 401) { if (statusEl) { statusEl.textContent = 'Authentication required. Please log in.'; statusEl.style.color = '#b91c1c'; } }
                    else { if (statusEl) { statusEl.textContent = 'Server error: ' + r.status; statusEl.style.color = '#b91c1c'; } }
                } else if (j && j.ok) {
                    const headers = Array.from(document.querySelectorAll('h3'));
                    let ingredientsP = null;
                    for (const h of headers) if (h.textContent.toLowerCase().includes('ingredient')) { ingredientsP = h.nextElementSibling; break; }
                    const confidence = (typeof j.confidence === 'number') ? j.confidence : parseFloat(j.confidence || 0);
                    if (confidence >= 0.5) {
                        if (ingredientsP) ingredientsP.textContent = j.ingredients;
                        if (statusEl) { statusEl.textContent = 'Detected: ' + j.ingredients + (j.note ? ' — ' + j.note : ''); statusEl.style.color = '#065f46'; }
                    } else {
                        if (statusEl) {
                            statusEl.textContent = '';
                            const txt = document.createTextNode('Suggested: ' + j.ingredients + (j.note ? ' — ' + j.note : ''));
                            statusEl.appendChild(txt);
                            const apply = document.createElement('button'); apply.textContent = 'Apply';
                            apply.style.marginLeft = '8px'; apply.style.padding = '4px 8px'; apply.style.borderRadius = '6px'; apply.style.background = '#6c63ff'; apply.style.color = '#fff'; apply.style.border = 'none'; apply.style.cursor = 'pointer';
                            apply.addEventListener('click', ()=>{ if (ingredientsP) ingredientsP.textContent = j.ingredients; statusEl.textContent = 'Applied suggestion.'; statusEl.style.color = '#065f46'; });
                            statusEl.appendChild(apply);
                            statusEl.style.color = '#b45309';
                        }
                    }
                }
            } catch (err) {
                if (statusEl) { statusEl.textContent = 'Network error on upload detect: ' + (err.message || err); statusEl.style.color = '#b91c1c'; }
            }
        });
        // open file dialog
        fi.click();
    }
})();
</script>

<!-- end snippet -->
