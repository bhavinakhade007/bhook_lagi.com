<?php
// AI Image to Ingredient endpoint (naive heuristics with optional AI integration)
header('Content-Type: application/json; charset=utf-8');
// Quiet PHP warnings and ensure we return JSON-only responses
ini_set('display_errors', 0);
set_error_handler(function($errno, $errstr, $errfile, $errline){ throw new ErrorException($errstr, 0, $errno, $errfile, $errline); });
register_shutdown_function(function(){ $err = error_get_last(); if ($err) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']); exit(); } });
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Authentication required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'POST required']);
    exit();
}

if (empty($_FILES['image']['tmp_name'])){
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'No image uploaded']);
    exit();
}

$tmp = $_FILES['image']['tmp_name'];
$name = strtolower($_FILES['image']['name'] ?? '');

// Very simple heuristic: check filename words for known ingredients
$ingredientsDB = ['potato'=>'potato','aloo'=>'potato','paneer'=>'paneer','chicken'=>'chicken','egg'=>'egg','rice'=>'rice','chickpea'=>'chickpeas','chole'=>'chickpeas','samosa'=>'potato, peas','maida'=>'wheat flour','wheat'=>'wheat flour','paneer'=>'paneer','tomato'=>'tomato','onion'=>'onion','garlic'=>'garlic','ginger'=>'ginger','spinach'=>'spinach','dal'=>'lentils','potatoes'=>'potato','peas'=>'peas','paneer'=>'paneer','fish'=>'fish','mutton'=>'mutton'];
$found = [];
foreach ($ingredientsDB as $k => $v){ if (strpos($name,$k) !== false) $found[] = $v; }

// If no filename hints, try improved color-based analysis to guess ingredients
if (empty($found)){
    $imgData = @file_get_contents($tmp);
    $colorHints = [];
    $saturationSum = 0;
    $saturationCount = 0;
    $counts = [];
    $saturatedCounts = [];
    if ($imgData && function_exists('imagecreatefromstring')){
        $im = @imagecreatefromstring($imgData);
        if ($im !== false){
            $w = imagesx($im);
            $h = imagesy($im);
            $counts = []; // reset counts for this image
            $saturatedCounts = []; // track highly saturated colors separately for better confidence
            $step = max(1, intval(min($w,$h) / 80)); // sample up to ~80x80 grid for better accuracy
            for ($x=0;$x<$w;$x+=$step){
                for ($y=0;$y<$h;$y+=$step){
                    $rgb = imagecolorat($im,$x,$y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // calculate saturation (vividness)
                    $max = max($r,$g,$b);
                    $min = min($r,$g,$b);
                    $delta = $max - $min;
                    $saturation = $max > 0 ? ($delta / $max) : 0;
                    $saturationSum += $saturation;
                    $saturationCount++;
                    
                    // convert to hue
                    $hue = 0;
                    if ($delta > 0) {
                        if ($max == $r) {
                            $hue = 60 * fmod((($g - $b) / $delta), 6);
                        } elseif ($max == $g) {
                            $hue = 60 * ((($b - $r) / $delta) + 2);
                        } else {
                            $hue = 60 * ((($r - $g) / $delta) + 4);
                        }
                        if ($hue < 0) $hue += 360;
                    }
                    
                    // improved bucket classification with better thresholds
                    $bucket = 'other';
                    if ($max < 40 && $min < 30 && $delta < 20) {
                        $bucket = 'dark';
                    } elseif ($max > 210 && $g > 190 && $r > 190 && $b > 190 && $delta < 30) {
                        $bucket = 'white';
                    } elseif ($hue >= 50 && $hue <= 160 && $saturation > 0.3) {
                        $bucket = 'green';
                    } elseif (($hue >= 350 || ($hue >= 0 && $hue <= 30)) && $saturation > 0.4) {
                        $bucket = 'red';
                    } elseif ($hue > 20 && $hue < 50 && $saturation > 0.35) {
                        $bucket = 'yellow_orange';
                    } elseif ($saturation > 0.5) {
                        // highly saturated colors that don't fit other categories
                        if ($hue >= 160 && $hue < 250) $bucket = 'blue_purple';
                        elseif ($hue >= 250 && $hue < 350) $bucket = 'purple_pink';
                    }
                    
                    if (!isset($counts[$bucket])) $counts[$bucket] = 0;
                    $counts[$bucket]++;
                    
                    // track saturated pixels separately (more reliable for ingredient detection)
                    if ($saturation > 0.4 && $bucket !== 'dark' && $bucket !== 'white' && $bucket !== 'other') {
                        if (!isset($saturatedCounts[$bucket])) $saturatedCounts[$bucket] = 0;
                        $saturatedCounts[$bucket]++;
                    }
                }
            }
            arsort($counts);
            arsort($saturatedCounts);
            
            // Build ingredient mapping dynamically from recipes.db
            $bucketMap = [];
            
            // Function to categorize ingredient by color/type
            $categorizeIngredient = function($ingredient) {
                $ing = strtolower(trim($ingredient));
                // Green ingredients
                if (preg_match('/\b(spinach|coriander|cilantro|peas|green chile|green chili|green pepper|mint|fenugreek|curry leaf|basil|parsley|capsicum|bell pepper)\b/', $ing)) {
                    return 'green';
                }
                // Red ingredients
                if (preg_match('/\b(tomato|red chilli|red chili|red pepper|red capsicum|paprika|bell pepper|chili powder)\b/', $ing)) {
                    return 'red';
                }
                // Yellow/Orange ingredients
                if (preg_match('/\b(potato|potatoes|carrot|turmeric|onion|onions|pumpkin|ginger|mango|lemon|yellow|orange)\b/', $ing)) {
                    return 'yellow_orange';
                }
                // White ingredients
                if (preg_match('/\b(rice|paneer|onion|garlic|cream|milk|yogurt|curd|cheese|cauliflower|coconut|cashew)\b/', $ing)) {
                    return 'white';
                }
                // Dark ingredients
                if (preg_match('/\b(chicken|meat|mutton|fish|mushroom|mushrooms|fried|brown|soy|black|dal|lentil)\b/', $ing)) {
                    return 'dark';
                }
                // Blue/Purple ingredients
                if (preg_match('/\b(eggplant|brinjal|purple cabbage|blueberry)\b/', $ing)) {
                    return 'blue_purple';
                }
                // Purple/Pink ingredients
                if (preg_match('/\b(beetroot|beet|radish|red cabbage)\b/', $ing)) {
                    return 'purple_pink';
                }
                return null; // Skip if can't categorize
            };
            
            // Load ingredients from database
            $dbPath = __DIR__ . '/recipes.db';
            $ingredientFrequency = [];
            
            if (file_exists($dbPath)) {
                try {
                    $pdo = new PDO('sqlite:' . $dbPath);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $stmt = $pdo->query('SELECT ingredients FROM recipes WHERE ingredients IS NOT NULL AND ingredients != ""');
                    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Parse all ingredients and count frequency by category
                    foreach ($rows as $ingredientString) {
                        // Split by comma, semicolon, or newline and clean
                        $ingredients = preg_split('/[,;\n\r]+/', $ingredientString);
                        foreach ($ingredients as $ing) {
                            $ing = trim($ing);
                            // Skip empty or very short ingredients (likely parsing errors)
                            if (empty($ing) || strlen($ing) < 2) continue;
                            
                            $category = $categorizeIngredient($ing);
                            if ($category) {
                                // Normalize for deduplication: lowercase, remove extra spaces
                                $ingLower = strtolower(preg_replace('/\s+/', ' ', trim($ing)));
                                if (!isset($ingredientFrequency[$category])) {
                                    $ingredientFrequency[$category] = [];
                                }
                                // Count frequency - keep original case of most common variant
                                if (!isset($ingredientFrequency[$category][$ingLower])) {
                                    $ingredientFrequency[$category][$ingLower] = ['name' => $ing, 'count' => 0];
                                } else {
                                    // If this variant is longer, prefer shorter name (more common format)
                                    if (strlen($ing) < strlen($ingredientFrequency[$category][$ingLower]['name'])) {
                                        $ingredientFrequency[$category][$ingLower]['name'] = $ing;
                                    }
                                }
                                $ingredientFrequency[$category][$ingLower]['count']++;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Database error - will use fallback below
                }
            }
            
            // Build bucketMap from database data, prioritizing by frequency
            $defaultBuckets = [
                'green' => ['spinach','coriander','peas','green chile'],
                'red' => ['tomato','red chilli','bell pepper'],
                'yellow_orange' => ['potato','carrot','turmeric','onion'],
                'white' => ['rice','paneer','onion','garlic'],
                'dark' => ['chicken','meat','mushroom'],
                'blue_purple' => ['eggplant'],
                'purple_pink' => ['beetroot'],
                'other' => []
            ];
            
            foreach (['green', 'red', 'yellow_orange', 'white', 'dark', 'blue_purple', 'purple_pink'] as $bucket) {
                if (isset($ingredientFrequency[$bucket]) && !empty($ingredientFrequency[$bucket])) {
                    // Sort by frequency (descending), then by name length (ascending - prefer shorter names)
                    uasort($ingredientFrequency[$bucket], function($a, $b) {
                        if ($a['count'] !== $b['count']) {
                            return $b['count'] - $a['count']; // Higher count first
                        }
                        return strlen($a['name']) - strlen($b['name']); // Shorter name first
                    });
                    
                    // Take top 4 most frequent ingredients from database
                    $bucketMap[$bucket] = array_slice(array_map(function($item) {
                        return $item['name'];
                    }, array_values($ingredientFrequency[$bucket])), 0, 4);
                } else {
                    // Fallback to default if no database data
                    $bucketMap[$bucket] = $defaultBuckets[$bucket] ?? [];
                }
            }
            
            // Always skip 'other' category
            $bucketMap['other'] = [];
            
            // prioritize saturated colors for ingredient hints, but be more conservative
            $total = array_sum($counts);
            $priorityBuckets = !empty($saturatedCounts) ? array_keys($saturatedCounts) : array_keys($counts);
            $addedBuckets = 0;
            foreach ($priorityBuckets as $bucket){
                $cnt = $counts[$bucket] ?? 0;
                if ($cnt <= 0) continue;
                
                // require minimum 8% of total pixels for a bucket to be considered significant
                $bucketRatio = $total > 0 ? ($cnt / $total) : 0;
                if ($bucketRatio < 0.08 && $bucket !== 'dark' && $bucket !== 'white') continue;
                
                // for dark/white, require higher threshold (15%) as they're less specific
                if (($bucket === 'dark' || $bucket === 'white') && $bucketRatio < 0.15) continue;
                
                $hints = $bucketMap[$bucket] ?? [];
                // skip empty or vague buckets
                if (empty($hints) || $bucket === 'other') continue;
                
                // take only top 1 hint from each bucket for better accuracy
                $topHint = $hints[0] ?? null;
                if ($topHint && !in_array($topHint, $colorHints)) {
                    $colorHints[] = $topHint;
                    $addedBuckets++;
                }
                // limit to top 3 most significant color buckets for better accuracy
                if ($addedBuckets >= 3) break;
            }
            imagedestroy($im);
        }
    }

    // First, try to call a local vision server if available (higher-quality model)
    $visionUrl = getenv('VISION_SERVER') ?: 'http://127.0.0.1:5000/parse';
    $visionOk = false;
    $visionResp = null;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $visionUrl);
    curl_setopt($curl, CURLOPT_TIMEOUT, 3);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    $cfile = new CURLFile($tmp, mime_content_type($tmp), basename($tmp));
    $post = ['image' => $cfile];
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    $vresp = @curl_exec($curl);
    $vinfo = curl_getinfo($curl);
    $verr = curl_error($curl);
    curl_close($curl);
    // if we got a response, attempt to parse JSON, but log and ignore HTML or malformed content
    if ($vresp) {
        $looksLikeHtml = preg_match('/<\s*!?doctype|<html/i', substr($vresp,0,512));
        $visionResp = @json_decode($vresp, true);
        if ($visionResp && is_array($visionResp) && !empty($visionResp['ok']) && !empty($visionResp['ingredients'])){
            // use vision server's mapping
            $found = array_map('trim', explode(',', $visionResp['ingredients']));
            $note = 'Vision server: ' . ($visionResp['note'] ?? '');
            $confidence = isset($visionResp['confidence']) ? floatval($visionResp['confidence']) : 0.0;
            $visionOk = true;
        } else {
            // log unexpected responses from vision server for debugging
            @include_once __DIR__ . '/ai_utils.php';
            $extra = [
                'url' => $visionUrl,
                'http_code' => $vinfo['http_code'] ?? null,
                'curl_error' => $verr ?: null,
                'looks_like_html' => (bool)$looksLikeHtml,
                'response_snippet' => mb_substr($vresp, 0, 200),
            ];
            if ($looksLikeHtml) {
                // attempt to extract "on line" and filename from common PHP error pages
                if (preg_match('/in\s+([^\s<]+)\s+on\s+line\s+(\d+)/i', $vresp, $m)){
                    $extra['parsed_file'] = $m[1];
                    $extra['parsed_line'] = intval($m[2]);
                }
            }
            ai_log('warning', 'vision_response_unexpected', $extra);
            // do not use vision response if it's not valid JSON with expected fields
        }
    } elseif ($verr) {
        @include_once __DIR__ . '/ai_utils.php';
        ai_log('warning', 'vision_connection_error', ['url'=>$visionUrl,'error'=>$verr]);
    }

    if (!$visionOk) {
        if (!empty($colorHints)){
            // improved multi-factor confidence calculation
            $total = array_sum($counts);
            $topCounts = array_values($counts);
            $top1 = $topCounts[0] ?? 0;
            $top2 = $topCounts[1] ?? 0;
            $top3 = $topCounts[2] ?? 0;
            $topRatio = $total > 0 ? $top1 / $total : 0;
            $topTwoRatio = $total > 0 ? ($top1 + $top2) / $total : 0;
            $topThreeRatio = $total > 0 ? ($top1 + $top2 + $top3) / $total : 0;
            
            // calculate average saturation (higher = more vivid colors = better confidence)
            $avgSaturation = $saturationCount > 0 ? ($saturationSum / $saturationCount) : 0;
            
            // count distinct color buckets (diversity bonus)
            $distinctBuckets = count(array_filter($counts, function($cnt) { return $cnt > 0; }));
            $diversityBonus = min(0.15, ($distinctBuckets - 1) * 0.05); // up to 15% bonus
            
            // saturation factor (vivid colors indicate real ingredients)
            $saturationFactor = min(0.25, $avgSaturation * 0.5); // up to 25% bonus
            
            // base confidence from color dominance
            $baseConfidence = $topRatio;
            
            // if topTwoRatio is strong, boost confidence
            if ($topTwoRatio > 0.6) {
                $baseConfidence = max($baseConfidence, $topTwoRatio * 0.85);
            }
            
            // more conservative confidence calculation - don't over-inflate
            // base confidence should be primary factor
            $confidence = $baseConfidence;
            
            // add smaller bonuses only if base confidence is already reasonable
            if ($baseConfidence >= 0.25) {
                // add saturation bonus only if colors are vivid (indicates real food items)
                if ($avgSaturation > 0.4) {
                    $confidence = min(1.0, $confidence + ($saturationFactor * 0.5)); // reduce bonus to 50% of factor
                }
                // add diversity bonus only if we have 2-3 distinct colors (not too many, not too few)
                if ($distinctBuckets >= 2 && $distinctBuckets <= 4) {
                    $confidence = min(1.0, $confidence + ($diversityBonus * 0.6)); // reduce bonus to 60% of factor
                }
            }
            
            // adjust confidence based on saturated color presence (more conservative)
            $saturatedTotal = array_sum($saturatedCounts);
            if ($saturatedTotal > 0 && $total > 0) {
                $saturatedRatio = $saturatedTotal / $total;
                // only boost if we have strong saturated color presence AND good base confidence
                if ($saturatedRatio > 0.3 && $baseConfidence >= 0.3) {
                    $confidence = min(1.0, $confidence + ($saturatedRatio * 0.15)); // reduced boost
                }
            }
            
            // more conservative acceptance threshold - require stronger signals
            // also ensure we actually have valid ingredient hints
            $accepted = !empty($colorHints) && (
                       ($baseConfidence >= 0.35) || 
                       ($topTwoRatio >= 0.50 && $avgSaturation > 0.35) || 
                       ($topRatio >= 0.25 && $avgSaturation > 0.45 && count($colorHints) <= 3)
            );
            
            if ($accepted && !empty($colorHints)) {
                $found = array_unique($colorHints);
                $note = sprintf('Heuristic: color-analysis (confidence %.0f%%, saturation %.0f%%)', $confidence * 100, $avgSaturation * 100);
            } else {
                // do not suggest specific ingredients when color signal is weak
                $found[] = 'salt, water, vegetable oil (unable to confidently detect specific ingredients from image)';
                $note = sprintf('Color-analysis returned weak signal (confidence %.0f%%). Add AI_API_KEY for better recognition.', $confidence * 100);
            }
        } else {
            // last resort: maintain previous generic fallback
            $found[] = 'salt, water, vegetable oil (unable to confidently detect specific ingredients from image)';
            $note = 'No filename or color hints found. Add AI_API_KEY for better recognition.';
            $confidence = 0.0;
        }
    }
} else {
    $confidence = 0.85;
    $note = 'Filename-based match (higher confidence)';
}

echo json_encode(['ok'=>true,'ingredients'=>implode(', ', array_unique($found)),'note'=>$note,'confidence'=>round($confidence,2)]);
exit();

?>
