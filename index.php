<?php
const SERPAPI_KEY = '2ade034ad96b3143a8c65f6eef01d76aa19be6d875cebd2c7d01f367496270f8';

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function buildSerpApiUrl(array $form): string {
    $params = [
        'engine' => 'google',
        'q' => $form['keyword'],
        'location' => $form['location'],
        'hl' => 'tr',
        'gl' => 'tr',
        'api_key' => SERPAPI_KEY,
        'num' => $form['limit'] === '10' ? 10 : 100
    ];
    return 'https://serpapi.com/search.json?' . http_build_query($params);
}

function fetchResults(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || !$response) {
        throw new Exception("API Hatası: $error");
    }

    return json_decode($response, true);
}

function prepareChartData(array $results): array {
    $labels = [];
    $positions = [];

    foreach (array_slice($results['organic_results'] ?? [], 0, 10) as $index => $result) {
        $link = $result['link'] ?? 'unknown';
        $host = parse_url($link, PHP_URL_HOST) ?? 'unknown';
        $labels[] = $host;
        $positions[] = $index + 1;
    }

    return ['labels' => $labels, 'positions' => $positions];
}

function displayResults(array $results, string $targetDomain, bool $showSnippets, bool $showLinks): void {
    $found = false;
    echo '<div class="result-card mt-4">';
    foreach ($results['organic_results'] ?? [] as $index => $result) {
        $position = $index + 1;
        $link = $result['link'] ?? '';
        $title = $result['title'] ?? '';
        $snippet = $result['snippet'] ?? '';

        $isMatch = stripos($link, $targetDomain) !== false;

        if ($isMatch) {
            $found = true;
            echo "<h5 class='text-success mb-2'>✅ Hedef domain bulundu!</h5>";
            echo "<p><strong>{$targetDomain}</strong> → <span class='badge bg-success'>{$position}. sırada</span></p>";
            echo $showLinks ? "<p>Sayfa: <a href='{$link}' target='_blank'>{$link}</a></p>" : '';
            break;
        }
    }

    if (!$found) {
        echo "<h5 class='text-danger'>❌ {$targetDomain} ilk 100 içinde bulunamadı.</h5>";
    }

    echo "<hr><h6 class='mt-3 text-muted'>🔍 Arama Sonuçları:</h6>";
    foreach ($results['organic_results'] ?? [] as $index => $result) {
        echo '<div class="mb-3">';
        echo "<strong>" . ($index + 1) . ". </strong>" . ($result['title'] ?? '[Başlık yok]');
        if ($showSnippets && isset($result['snippet'])) {
            echo "<div class='text-muted'><small>{$result['snippet']}</small></div>";
        }
        if ($showLinks && isset($result['link'])) {
            echo "<div><a href='{$result['link']}' target='_blank'>{$result['link']}</a></div>";
        }
        echo '</div>';
    }

    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Google Sıralama Kontrolü</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f9f9f9; }
        .result-card {
            border-left: 5px solid #0d6efd;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,.05);
        }
    </style>
</head>
<body>
<div class="container my-5">
    <h2 class="mb-4 text-center text-primary">🔍 Google Sıralama Kontrolü</h2>

    <form method="POST" class="row g-3 bg-white p-4 rounded shadow-sm border">
        <div class="col-md-4">
            <label class="form-label">Arama Kelimesi</label>
            <input type="text" class="form-control" name="keyword" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Şehir</label>
            <input type="text" class="form-control" name="location" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Hedef Domain</label>
            <input type="text" class="form-control" name="target_domain" placeholder="ornek.com" required>
        </div>

        <div class="col-md-12 mt-2">
            <label class="form-label">Ekstra Ayarlar</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="show_snippets" id="show_snippets" checked>
                <label class="form-check-label" for="show_snippets">Snippet açıklamaları göster</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="show_links" id="show_links" checked>
                <label class="form-check-label" for="show_links">Sonuç linklerini göster</label>
            </div>
            <div class="form-group mt-3">
                <label class="form-label">Kaç sonuç kontrol edilsin?</label>
                <select class="form-select" name="limit">
                    <option value="10">İlk 10 Sonuç</option>
                    <option value="100" selected>İlk 100 Sonuç</option>
                </select>
            </div>
        </div>

        <div class="col-12 text-end mt-3">
            <button type="submit" class="btn btn-primary">Sorgula</button>
        </div>
    </form>

    <div class="mt-5">
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $form = [
                    'keyword' => sanitize($_POST['keyword']),
                    'location' => sanitize($_POST['location']),
                    'target_domain' => sanitize($_POST['target_domain']),
                    'show_snippets' => isset($_POST['show_snippets']),
                    'show_links' => isset($_POST['show_links']),
                    'limit' => $_POST['limit'] === '10' ? '10' : '100'
                ];

                $url = buildSerpApiUrl($form);
                $results = fetchResults($url);
                displayResults($results, $form['target_domain'], $form['show_snippets'], $form['show_links']);

                $chartData = prepareChartData($results);
                $labels = json_encode($chartData['labels']);
                $positions = json_encode($chartData['positions']);

                echo <<<HTML
                    <hr class="mt-5">
                    <h5 class="mt-4">📊 İlk 10 Sonuç - Domain Grafiği</h5>
                    <canvas id="rankingChart" height="200"></canvas>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                        const ctx = document.getElementById('rankingChart').getContext('2d');
                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: $labels,
                                datasets: [{
                                    label: 'Sıralama Pozisyonu',
                                    data: $positions,
                                    backgroundColor: 'rgba(13, 110, 253, 0.7)'
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        reverse: true,
                                        ticks: {
                                            stepSize: 1,
                                            precision: 0
                                        }
                                    }
                                }
                            }
                        });
                    </script>
                HTML;

            } catch (Exception $e) {
                echo "<div class='alert alert-danger'>❌ Hata: " . $e->getMessage() . "</div>";
            }
        }
        ?>
    </div>
</div>
</body>
</html>
