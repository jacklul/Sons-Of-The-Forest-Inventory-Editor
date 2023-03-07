<?php

$gameDir = $argv[1] ?? null;

if (!$gameDir || !is_dir($gameDir . '/SonsOfTheForest_Data')) {
    die('Directory does not exist: ');
}

ini_set('memory_limit', '4G');

$gameDir .= '/SonsOfTheForest_Data';

if (!file_exists(__DIR__ . '/extract.cache')) {
    echo 'Loading resources.assets...' . PHP_EOL;
    $data = file_get_contents($gameDir . '/resources.assets');

    echo 'Extracting data...' . PHP_EOL;

    preg_match_all('/\x0{3}([0-9A-z ]+) ID\((\d+)\)\x0/mU', $data, $matches);

    $items = [];
    for ($i  = 0; $i < count($matches[0]); $i++) {
        $items[$matches[2][$i]] = [
            'id'   => $matches[2][$i],
            'name_singular' => '',
            //'name_plural' => '',
            'name_pascal' => $matches[1][$i],
            'name_snake' => '',
        ];
    }

    //file_put_contents('extract_items.txt', print_r($items, true));

    echo 'Extracting names...' . PHP_EOL;

    $previous = 0;
    foreach ($items as &$item) {
        $pos = strpos($data, "\0" . $item['name_pascal'] . ' ID(');

        //echo $item['name_pascal'] . PHP_EOL;

        if ($previous === 0) {
            $part = substr($data, $pos - 1000, 1000 + strlen($item['name_pascal'] . ' ID') + 10);
        } else {
            $part = substr($data, $previous);
            $part = substr($part, 0, $pos - $previous);

            if (strlen($part) > 10000) {
                echo 'Stripped data is too long - ' . strlen($part) . ' - ' . $item['name_pascal'];
                exit;
            }
        }

        $previous = $pos;

        preg_match_all('/[\x00-\x1F]([ A-Za-z0-9_\+\-\{\}\@\.\/\#\&]{3,})[\x00-\x1F]/U', $part, $matches);

        //if ($item['id'] == 523) {
        //    print_r($matches);
        //    //file_put_contents('extract_tmp.txt', $part);
        //    exit;
        //}

        if (isset($matches[1]) && ($total = count($matches[1])) >= 1) {
            $found = 0;
            for ($i = count($matches[1]) - 1; $i >= 0; $i--) {
                if ($val = preg_match('/[A-Z_]{3,}/', $matches[1][$i])) {
                    $found = $i;
                    break;
                }
            }

            if ($found < 2) {
                $found2 = 0;
                for ($i = count($matches[1]) - 1; $i >= 0; $i--) {
                    if ($val = preg_match('/\{TitlePlural\}/', $matches[1][$i])) {
                        $found2 = $i;
                        break;
                    }
                }

                if ($found2 < 1) {
                    $item['name_singular'] = $item['name_pascal'];
                } else {
                    $item['name_singular'] = $matches[1][$i - 1];
                    //$item['name_plural'] = $matches[1][$i];
                }
            } else {
                $item['name_singular'] = $matches[1][$i - 2];
                //$item['name_plural'] = $matches[1][$i - 1];
                $item['name_snake'] = $matches[1][$i];
            }
        } else {
            //print_r($matches);
            echo 'Failed to extract names for ' . $item['id'] . ' - ' . $item['name_pascal'] . PHP_EOL;
            exit;
        }
    }
    unset($item);

    file_put_contents(__DIR__ . '/extract.cache', json_encode($items));
} else {
    $items = json_decode(file_get_contents(__DIR__ . '/extract.cache'), true);
}

echo 'Saving...' . PHP_EOL;

$fp = fopen(__DIR__ . '/items.csv', 'w');
//fputcsv($fp, ['ID', 'Name (singular)', 'Name (plural)', 'Name (PascalCase)', 'Name (SNAKE_CASE)']);
fputcsv($fp, ['ID', 'Name (singular)', 'Name (PascalCase)', 'Name (SNAKE_CASE)']);

foreach ($items as $item) {
    fputcsv($fp, [
        (int)$item['id'],
        $item['name_singular'],
        $item['name_pascal'],
        $item['name_snake'],
    ]);
}

fclose($fp);

echo 'Updating items.json...' . PHP_EOL;

if (file_exists(__DIR__ . '/www/items.json')) {
    $json = json_decode(file_get_contents(__DIR__ . '/www/items.json'), true);

    $newJson = [];
    foreach ($items as $item) {
        foreach ($json as &$jsonItem) {
            if ($jsonItem['id'] == (int)$item['id']) {
                $newItem = [
                    "id" => (int)$item['id'],
                    "name" => $item['name_singular'],
                    "reference" => $item['name_pascal'],
                    "max" => $jsonItem['max'] ?? 1
                ];
        
                foreach ($jsonItem as $key => $value) {
                    if (!isset($newItem[$key])) {
                        $newItem[$key] = $value;
                    }
                }

                $newJson[] = $newItem;
                continue 2;
            }
        }
        unset($jsonItem);

        // Not found in items.json
        $newJson[] = [
            "id" => (int)$item['id'],
            "name" => $item['name_singular'],
            "reference" => $item['name_pascal'],
            "max" => 1,
        ];
    }

    file_put_contents(__DIR__ . '/www/items.json', json_encode($newJson, JSON_PRETTY_PRINT));
}
