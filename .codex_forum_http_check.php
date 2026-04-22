<?php
require __DIR__ . '/tests/bootstrap.php';
ini_set('session.save_path', __DIR__ . '/var/codex_sessions');
$request = \Symfony\Component\HttpFoundation\Request::create('/forum/', 'POST', [
    'yield_forecast' => [
        'surface' => '12.5',
        'cropCoefficient' => '1.3',
        'weatherCoefficient' => '0.85',
    ],
]);
$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$response = $kernel->handle($request);
file_put_contents('.codex_forum_yield_response.html', $response->getContent());
echo 'yield_status=' . $response->getStatusCode() . PHP_EOL;
echo 'yield_title=' . (preg_match('/<title>(.*?)<\/title>/s', $response->getContent(), $m) ? trim($m[1]) : 'none') . PHP_EOL;
echo 'yield_contains_result_id=' . (strpos($response->getContent(), 'yield-result-value') !== false ? '1' : '0') . PHP_EOL;
echo 'yield_contains_13_81=' . (strpos($response->getContent(), '>13.81<') !== false ? '1' : '0') . PHP_EOL;
echo 'yield_contains_panel_show=' . (strpos($response->getContent(), 'forumYieldForecastPanel') !== false && strpos($response->getContent(), 'collapse mb-4 show') !== false ? '1' : '0') . PHP_EOL;
$kernel->terminate($request, $response);

$request2 = \Symfony\Component\HttpFoundation\Request::create('/forum/', 'POST', [
    'profitability' => [
        'revenue' => '5000',
        'fertilizer' => '800',
        'water' => '300',
        'labor' => '1200',
        'seeds' => '450',
    ],
]);
$kernel2 = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$response2 = $kernel2->handle($request2);
file_put_contents('.codex_forum_profit_response.html', $response2->getContent());
echo 'profit_status=' . $response2->getStatusCode() . PHP_EOL;
echo 'profit_title=' . (preg_match('/<title>(.*?)<\/title>/s', $response2->getContent(), $m2) ? trim($m2[1]) : 'none') . PHP_EOL;
echo 'profit_contains_result_id=' . (strpos($response2->getContent(), 'profit-result-value') !== false ? '1' : '0') . PHP_EOL;
echo 'profit_contains_2250=' . (strpos($response2->getContent(), '>2250.00<') !== false ? '1' : '0') . PHP_EOL;
echo 'profit_contains_panel_show=' . (strpos($response2->getContent(), 'forumProfitabilityPanel') !== false && strpos($response2->getContent(), 'collapse mb-4 show') !== false ? '1' : '0') . PHP_EOL;
$kernel2->terminate($request2, $response2);
