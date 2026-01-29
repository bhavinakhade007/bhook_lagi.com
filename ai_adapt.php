<?php
// AI Recipe Adaptor endpoint
// Accepts POST with either: id OR name + ingredients + steps, and 'target' (e.g., 'vegan', 'gluten-free', 'low-sodium')
header('Content-Type: application/json; charset=utf-8');
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Authentication required']);
    exit();
}

$input = $_POST;
$target = trim(strtolower($input['target'] ?? ''));
$name = trim($input['name'] ?? '');
$ingredients = trim($input['ingredients'] ?? '');
$steps = trim($input['steps'] ?? '');

// If id provided, try to load from DB or CSV
if (empty($name) && !empty($input['id'])) {
    $id = intval($input['id']);
    // try DB
    $dbPath = __DIR__ . '/recipes.db';
    if (file_exists($dbPath)) {
        try{
            $pdo = new PDO('sqlite:'.$dbPath);
            $stmt = $pdo->prepare('SELECT name, ingredients, steps FROM recipes WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { $name = $row['name']; $ingredients = $row['ingredients']; $steps = $row['steps']; }
        }catch(
            Exception $e){ }
    }
    // fallback CSV
    if (empty($name)) {
        if (($h = fopen(__DIR__.'/recipes.csv','r')) !== FALSE) {
            fgetcsv($h);
            while (($r = fgetcsv($h)) !== FALSE) {
                if (intval($r[0]) === $id) { $name = $r[1]; $ingredients = $r[2]; $steps = $r[3]; break; }
            }
            fclose($h);
        }
    }
}

if (empty($name)){
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Missing recipe data']);
    exit();
}

// Wrap adaptation in try/catch so we can log unexpected exceptions
try {
    // Basic local adaptation heuristics if no AI key is present
    $aiKey = getenv('AI_API_KEY') ?: ($_SERVER['AI_API_KEY'] ?? '');
    $provider = getenv('AI_API_PROVIDER') ?: 'none';

    function simple_substitute($ingredients, $mappings){
        $out = $ingredients;
        foreach ($mappings as $from => $to) {
            $out = preg_replace('/\b'.preg_quote($from,'/').'\b/i', $to, $out);
        }
        return $out;
    }

    if (empty($aiKey) || $provider === 'none') {
        // do straightforward substitutions depending on target
        $adaptedIngredients = $ingredients;
        $adaptedSteps = $steps;
        $adaptedName = $name;
        if (strpos($target,'vegan') !== false) {
            $sub = [ 'paneer'=>'tofu', 'butter'=>'plant-based butter', 'milk'=>'plant milk', 'ghee'=>'plant-based butter', 'yogurt'=>'plant-based yogurt', 'egg'=>'flax egg', 'chicken'=>'tofu', 'fish'=>'tofu', 'meat'=>'seitan' ];
            $adaptedIngredients = simple_substitute($ingredients, $sub);
            $adaptedSteps = simple_substitute($steps, $sub);
            if (!preg_match('/vegan/i',$adaptedName)) $adaptedName .= ' (Vegan)';
        } elseif (strpos($target,'gluten') !== false) {
            $sub = [ 'wheat flour'=>'gluten-free flour', 'maida'=>'gluten-free flour', 'atta'=>'gluten-free flour' ];
            $adaptedIngredients = simple_substitute($ingredients, $sub);
            $adaptedSteps = simple_substitute($steps, $sub);
            if (!preg_match('/gluten[- ]?free/i',$adaptedName)) $adaptedName .= ' (Gluten-Free)';
        } elseif (strpos($target,'low') !== false || strpos($target,'sodium') !== false) {
            $adaptedIngredients = preg_replace('/\b(salt|salted)\b/i','salt (reduced)', $ingredients);
            $adaptedSteps = preg_replace('/\b(add salt|salt to taste)\b/i','reduce salt; consider low-sodium alternatives', $steps);
            if (!preg_match('/low[- ]?sodium/i',$adaptedName)) $adaptedName .= ' (Low Sodium)';
        } else {
            // generic friendly rewrite: shorten steps
            $adaptedSteps = preg_replace('/\b(Heat|Now|Step|Then)\b/i', '', $steps);
        }

        echo json_encode(['ok'=>true,'adapted'=>['name'=>$adaptedName,'ingredients'=>$adaptedIngredients,'steps'=>$adaptedSteps],'note'=>'Adapted locally (no external AI key configured).']);
        exit();
    }

    // If AI key exists, prefer to call OpenAI chat completions to adapt (implementation placeholder)
    // Implementation: Currently we provide the local heuristic when no external API configured—to avoid requiring keys.
    echo json_encode(['ok'=>false,'error'=>'AI integration not configured. Set AI_API_KEY (OpenAI) for better results.']);
    exit();

} catch (Throwable $e) {
    @include_once __DIR__ . '/ai_utils.php';
    ai_log('error', 'ai_adapt_exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'post' => array_slice($_POST,0,20),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Server error']);
    exit();
}

// If AI key exists, prefer to call OpenAI chat completions to adapt (implementation placeholder)
// Implementation: Currently we provide the local heuristic when no external API configured—to avoid requiring keys.
echo json_encode(['ok'=>false,'error'=>'AI integration not configured. Set AI_API_KEY (OpenAI) for better results.']);
exit();

?>
