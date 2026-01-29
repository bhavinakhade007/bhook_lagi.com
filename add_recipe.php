<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ($_GET['prefill_name'] ?? ''));
    $ingredients = trim($_POST['ingredients'] ?? ($_GET['prefill_ingredients'] ?? ''));
    $steps = trim($_POST['steps'] ?? ($_GET['prefill_steps'] ?? ''));

    if ($name === '' || $ingredients === '' || $steps === '') {
        $errors[] = 'Please fill name, ingredients and steps.';
    }

    // handle image upload
    $imageName = '';
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['jpg','jpeg','png','gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Invalid image type. Allowed: jpg, jpeg, png, gif.';
        } else {
            $imagesDir = __DIR__ . '/images';
            if (!is_dir($imagesDir)) mkdir($imagesDir, 0755, true);
            $base = preg_replace('/[^a-z0-9_-]+/i', '_', pathinfo($_FILES['image']['name'], PATHINFO_FILENAME));
            $imageName = $base . '-' . time() . '.' . $ext;
            $dest = $imagesDir . '/' . $imageName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $errors[] = 'Failed to move uploaded image.';
                $imageName = '';
            }
        }
    }

    if (empty($errors)) {
        // try DB insert
        $dbPath = __DIR__ . '/recipes.db';
        $newId = null;
        if (file_exists($dbPath)) {
            try {
                $pdo = new PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->prepare('INSERT INTO recipes (name, ingredients, steps, image) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $ingredients, $steps, $imageName]);
                $newId = $pdo->lastInsertId();
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        } else {
            // fallback to CSV
            $csvPath = __DIR__ . '/recipes.csv';
            $nextId = 1;
            if (file_exists($csvPath)) {
                if (($h = fopen($csvPath, 'r')) !== FALSE) {
                    $hdr = fgetcsv($h);
                    $max = 0;
                    while (($row = fgetcsv($h)) !== FALSE) {
                        $max = max($max, intval($row[0]));
                    }
                    fclose($h);
                    $nextId = $max + 1;
                }
            } else {
                // create with header
                $h = fopen($csvPath, 'w');
                fputcsv($h, ['id','name','ingredients','steps','image']);
                fclose($h);
                $nextId = 1;
            }
            if (($h = fopen($csvPath, 'a')) !== FALSE) {
                fputcsv($h, [$nextId, $name, $ingredients, $steps, $imageName]);
                fclose($h);
                $newId = $nextId;
            } else {
                $errors[] = 'Unable to write to CSV file.';
            }
        }

        // create static recipe page so existing links work
        if ($newId) {
            $pagePath = __DIR__ . '/recipes/recipe-' . $newId . '.php';
            // create safe values for HTML
            $safeName = htmlspecialchars($name, ENT_QUOTES);
            $safeIngredients = htmlspecialchars($ingredients, ENT_QUOTES);
            // split steps into list items
            $stepsList = [];
            // try splitting by newlines first
            $rawSteps = preg_split('/\r?\n/', $steps);
            foreach ($rawSteps as $s) {
                $s = trim($s);
                if ($s !== '') $stepsList[] = $s;
            }
            if (count($stepsList) === 0) {
                // fallback split by sentences
                $parts = preg_split('/(?<=[.!?])\s+/', $steps);
                foreach ($parts as $p) { $p = trim($p); if ($p !== '') $stepsList[] = $p; }
            }
            $imgSrc = $imageName ? ('../images/' . $imageName) : '../assets/default_recipe.jpg';

            $html = "<!DOCTYPE html>\n";
            $html .= "<html>\n<head>\n    <meta charset=\"UTF-8\">\n    <title>" . $safeName . "</title>\n    <style>\n        body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(120deg, #f6d365cc 0%, #fda085cc 100%), url('https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80') center/cover no-repeat fixed; margin:0; min-height:100vh }\n        .recipe-container{ background:#fff;border-radius:18px;box-shadow:0 4px 24px #0002;max-width:720px;margin:48px auto;padding:28px;text-align:center }\n        .recipe-img{ width:100%; max-width:560px; height:320px; object-fit:cover; border-radius:12px; box-shadow:0 2px 12px #fda08533; margin-bottom:18px; background:#eee }\n        h1{ color:#f76b1c; font-size:2rem; margin-bottom:8px }\n        .back-link{ display:inline-block;margin-top:18px;padding:10px 22px;background:linear-gradient(90deg,#43e97b,#38f9d7);color:#fff;border-radius:6px;text-decoration:none;font-weight:700 }\n    </style>\n</head>\n<body>\n<div class=\"recipe-container\">\n    <img class=\"recipe-img\" src=\"" . $imgSrc . "\" alt=\"" . $safeName . "\" onerror=\"this.onerror=null;this.src='../assets/default_recipe.jpg';\">\n    <h1>" . $safeName . "</h1>\n    <h3>Ingredients:</h3>\n    <p>" . nl2br(htmlspecialchars($ingredients, ENT_QUOTES)) . "</p>\n    <h3>Steps:</h3>\n    <ol style=\"text-align:left;max-width:620px;margin:0 auto 10px auto;padding-left:1.2em;\">\n";
            foreach ($stepsList as $st) {
                $html .= "        <li>" . htmlspecialchars($st, ENT_QUOTES) . "</li>\n";
            }
            $html .= "    </ol>\n    <a class=\"back-link\" href=\"../index.php\">⬅ Back to Recipes</a>\n    <a href=\"../spin.php\" style=\"display:inline-block;margin-top:18px;margin-left:8px;padding:10px 18px;background:#6c63ff;color:#fff;border:none;border-radius:6px;font-size:0.95rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px #6c63ff55;\">Can't decide?</a>\n</div>\n</body>\n</html>";
        $html .= "    </ol>\n    <a class=\"back-link\" href=\"../index.php\">⬅ Back to Recipes</a>\n    <a href=\"../spin.php\" style=\"display:inline-block;margin-top:18px;margin-left:8px;padding:10px 18px;background:#6c63ff;color:#fff;border:none;border-radius:6px;font-size:0.95rem;font-weight:700;text-decoration:none;box-shadow:0 2px 8px #6c63ff55;\">Can't decide?</a>\n</div>\n<?php include_once __DIR__ . '/../recipe_ai_snippet.php'; ?>\n</body>\n</html>";

            // attempt to write the file
            if (file_put_contents($pagePath, $html) === false) {
                $errors[] = 'Failed to create recipe page file.';
            } else {
                $success = 'Recipe added successfully.';
            }
        }
    }
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Add Recipe</title>
  <style>
    body{font-family:Segoe UI, Arial, sans-serif;background:linear-gradient(120deg,#f6d365cc 0%,#fda085cc 100%);min-height:100vh;margin:0;padding:24px}
    .card{max-width:760px;margin:36px auto;background:#fff;padding:20px;border-radius:12px;box-shadow:0 6px 36px rgba(0,0,0,0.08)}
    label{display:block;margin-top:12px;font-weight:700}
    input[type=text], textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #ddd;margin-top:6px}
    .row{display:flex;gap:12px}
    .actions{margin-top:14px;display:flex;align-items:center;gap:12px}
    .actions .btn{display:inline-flex;align-items:center;gap:8px}
    .actions .cancel{color:#374151;text-decoration:none;padding:8px 12px;border-radius:8px}
    .btn{padding:10px 14px;border-radius:8px;border:0;background:#6c63ff;color:#fff;font-weight:700;cursor:pointer}
    .err{color:#b91c1c}
    .ok{color:#15803d}
  </style>
</head>
<body>
  <div class="card">
    <h2>Add New Recipe</h2>
    <?php if (!empty($errors)): ?>
      <div class="err"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="ok"><?php echo htmlspecialchars($success); ?> <a href="recipes/recipe-<?php echo intval($newId); ?>.php">View recipe</a></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <label for="name">Name</label>
    <input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($name ?? ''); ?>">

      <label for="ingredients">Ingredients (comma separated or newline)</label>
    <textarea id="ingredients" name="ingredients" rows="3"><?php echo htmlspecialchars($ingredients ?? ''); ?></textarea>

      <label for="steps">Steps (each step on new line preferred)</label>
    <textarea id="steps" name="steps" rows="6"><?php echo htmlspecialchars($steps ?? ''); ?></textarea>

      <label for="image">Image (optional)</label>
      <input id="image" name="image" type="file" accept="image/*">
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
                <button id="detectBtn" type="button" class="btn" style="background:linear-gradient(90deg,#ffb86b,#ff7a5c);">Detect Ingredients</button>
                <span id="detectStatus" style="font-size:0.95rem"></span>
            </div>

      <div class="actions">
                <button class="btn" type="submit">Add Recipe</button>
                <a class="cancel" href="index.php">Cancel</a>
      </div>
    </form>
  </div>
    <script>
        (function(){
            const detectBtn = document.getElementById('detectBtn');
            const inputFile = document.getElementById('image');
            const statusEl = document.getElementById('detectStatus');
            const ingredientsEl = document.getElementById('ingredients');
            detectBtn.addEventListener('click', async ()=>{
                statusEl.textContent = '';
                if (!inputFile.files || inputFile.files.length === 0) { statusEl.textContent = 'Choose an image first.'; return; }
                const file = inputFile.files[0];
                statusEl.textContent = 'Detecting...';
                const fd = new FormData(); fd.append('image', file);
                try {
                    const r = await fetch('ai_image_parse.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                    const j = await r.json();
                    if (j.ok) {
                        const confidence = (typeof j.confidence === 'number') ? j.confidence : parseFloat(j.confidence || 0);
                        if (confidence >= 0.5) {
                            ingredientsEl.value = j.ingredients;
                            statusEl.textContent = 'Detected: ' + j.ingredients + (j.note ? ' — ' + j.note : '');
                        } else {
                            statusEl.textContent = '';
                            const txt = document.createTextNode('Suggested: ' + j.ingredients + (j.note ? ' — ' + j.note : ''));
                            statusEl.appendChild(txt);
                            const apply = document.createElement('button'); apply.type='button'; apply.textContent = 'Apply';
                            apply.style.marginLeft = '8px'; apply.className = 'btn'; apply.addEventListener('click', ()=>{ ingredientsEl.value = j.ingredients; statusEl.textContent = 'Applied suggestion.'; });
                            statusEl.appendChild(apply);
                        }
                    } else {
                        statusEl.textContent = 'Error: ' + (j.error || 'unknown');
                    }
                } catch (e){ statusEl.textContent = 'Network error'; }
            });
        })();
    </script>
</body>
</html>
