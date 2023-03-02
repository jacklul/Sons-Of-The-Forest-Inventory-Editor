<?php

$dataPath = __DIR__ . '/../data';

if (!is_dir($dataPath)) {
    mkdir($dataPath);
}

$dataPath = realpath($dataPath);

if (!file_exists(__DIR__ . '/items.json')) {
    die('No items.json found');
}

$itemData = json_decode(file_get_contents(__DIR__ . '/items.json'), true);

if (empty($itemData)) {
    die('No item data found');
}

$itemDataNew = [];
foreach ($itemData as $data) {
    $itemDataNew[$data['id']] = $data;
}
$itemData = $itemDataNew;
unset($itemDataNew);

function addCurrentVersionToItemArray(array $item, string $version) {
    foreach ($item['Modules'] as &$data) {
        if (isset($data['Version']) && (empty($data['Version']) || $data['Version'] === '0.0.0')) {
            $data['Version'] = $version;
        }
    }
    return $item;
}

function backLink($br = true) {
    return ($br ? '<br>' : '') . '<a href="javascript:history.back()">Go Back</a>';
}

$configFile = $dataPath . '/config.json';
$config = [
    'savesPath' => null,
    'allowExceedMaxCapacity' => false,
];

if (file_exists($configFile)) {
    $default = $config;
    $config = json_decode(file_get_contents($configFile), true);

    $modified = false;
    foreach ($default as $var => $val) {
        if (!isset($config[$var])) {
            $config[$var] = $val;
            $modified = true;
        }
    }

    if ($modified) {
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    }
}

// Migrate old file
if (file_exists($dataPath . '/savespath')) {
    $config['savesPath'] = file_get_contents($dataPath . '/savespath');
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    unlink($dataPath . '/savespath');
}

if (is_null($config['savesPath'])) {
    $username = getenv("username");
    $config['savesPath'] = '';

    if (!empty($username)) {
        if (is_dir('C:\Users\\' . $username . '\AppData\LocalLow\Endnight\SonsOfTheForest\Saves')) {
            $config['savesPath'] = 'C:\Users\\' . $username . '\AppData\LocalLow\Endnight\SonsOfTheForest\Saves';
        }
    }
    
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
}

if (empty($config['savesPath'])) {
    if (isset($_GET['savespath']) && is_dir($_GET['savespath'])) {
        $config['savesPath'] = $_GET['savespath'];
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        header('location: /');
    }

    echo '<form method="get">Saves path: <input type="text" name="savespath" size="100" maxlength="1000" value=""><input type="submit" value="SAVE">';

    exit;
}

$saves = [];
if (is_dir($config['savesPath'])) {
    foreach ($iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($config['savesPath'], RecursiveDirectoryIterator::SKIP_DOTS)) as $item) {
        if ($item->getFilename() === 'PlayerInventorySaveData.json') {
            $saves[$item->getPathname()] = $item->getMTime();
        }
    }
}

echo '<form><select name="path" onchange="if(this.value != \'\') this.form.submit()" ><option value="" selected disabled>-- select save file --</option>';

asort($saves);
foreach (array_reverse($saves) as $save => $mtime) {
    $path = str_replace(DIRECTORY_SEPARATOR . 'PlayerInventorySaveData.json', '', $save);
    $title = str_replace([realpath($savesPath) . DIRECTORY_SEPARATOR], '', $path);
    $mtime = date('Y-m-d H:i:s', $mtime);
    $selected = isset($_GET['path']) && $_GET['path'] == $path;

    echo '<option value="' . $path . '"' . ($selected ? 'selected' : '') . '>' . $title . ' (' . $mtime . ')' . '</option>';
}

echo '</select><input type="submit" value="OPEN">&nbsp;<a href="?path=' . ($_GET['path'] ?? '') . '&print">PRINT INVENTORY ARRAY</a></form>';

if (isset($_GET['path']) && is_dir($_GET['path']) && file_exists($_GET['path'].'/PlayerInventorySaveData.json')) {
    $inventoryData = json_decode(file_get_contents($_GET['path'].'/PlayerInventorySaveData.json'), true);
    $inventoryContents = json_decode($inventoryData['Data']['PlayerInventory'], true);
    $version = $inventoryContents['ItemInstanceManagerData']['Version'];

    if (isset($_GET['print'])) {
        echo '<pre>';
        print_r($inventoryContents);
        echo '</pre>';
        exit;
    }

    if (isset($_GET['action'])) {
        if (!isset($_GET['id'])) {
            die('No item ID specified' . backLink());
        }

        if (!isset($itemData[$_GET['id']])) {
            die('Invalid item ID specified' . backLink());
        }

        $itemHandle = null;
        foreach ($inventoryContents['ItemInstanceManagerData']['ItemBlocks'] as &$itemInstance) {
            if ($itemInstance['ItemId'] === (int)$_GET['id']) {
                $itemHandle = &$itemInstance;
                break;
            }
        }

        $max = $itemData[$_GET['id']]['max'];
        $count = null;
        if ($itemHandle !== null) {
            $count = $itemHandle['TotalCount'];
        }

        if ($_GET['action'] !== 'add' && $count === null) {
            die('Item not found in the inventory' . backLink());
        }

        switch ($_GET['action']) {
            case 'add':
                if ($count !== null) {
                    die('Item is already in the inventory' . backLink());
                }

                $uniqueItems = [];
                if (isset($itemData[$_GET['id']]['item_data'])) {
                    $uniqueItems[] = addCurrentVersionToItemArray($itemData[$_GET['id']]['item_data'], $version);
                }

                $inventoryContents['ItemInstanceManagerData']['ItemBlocks'][] = [
                    'ItemId' => (int)$_GET['id'],
                    'TotalCount' => 1,
                    'UniqueItems' => $uniqueItems
                ];

                $inventoryContents['ItemInstanceManagerData']['ItemBlocks'] = array_values($inventoryContents['ItemInstanceManagerData']['ItemBlocks']);
                $inventoryData['Data']['PlayerInventory'] = json_encode($inventoryContents);
                file_put_contents($_GET['path'].'/PlayerInventorySaveData.json', json_encode($inventoryData), LOCK_EX);

                break;
            case 'fill':
                if ($count >= $max) {
                    break;
                }

                $_GET['count'] = '+' . ($max - $count);
            case 'modify':
                $modifier = substr($_GET['count'], 0, 1);
                $value = substr($_GET['count'], 1);

                switch ($modifier) {
                    case '+':
                        if (!$config['allowExceedMaxCapacity'] && $value > $max - $count) {
                            $value = $max - $count;
                        }

                        $itemHandle['TotalCount'] += $value;

                        if (isset($itemData[$_GET['id']]['item_data'])) {
                            $newItem = addCurrentVersionToItemArray($itemData[$_GET['id']]['item_data'], $version);

                            for ($j = $value; $j > 0; $j--) {
                                $itemHandle['UniqueItems'][] = $newItem;
                            }
                        }

                        break;
                    case '-':
                        if ($value > $count) {
                            $value = $count;
                        }

                        $itemHandle['TotalCount'] -= $value;

                        for ($j = $value; $j > 0; $j--) {
                            /** @var array $itemHandle */
                            array_pop($itemHandle['UniqueItems']);
                        }

                        break;
                    default:
                        die('Invalid modifier' . backLink());
                }

                if ($itemHandle['TotalCount'] > 0) {
                    $inventoryContents['ItemInstanceManagerData']['ItemBlocks'] = array_values($inventoryContents['ItemInstanceManagerData']['ItemBlocks']);
                    $inventoryData['Data']['PlayerInventory'] = json_encode($inventoryContents);
                    file_put_contents($_GET['path'].'/PlayerInventorySaveData.json', json_encode($inventoryData), LOCK_EX);
                    break;
                }
            case 'remove':
                if (isset($itemData[$_GET['id']]['essential'])) {
                    die('Item cannot be removed' . backLink());
                }

                for ($i = 0; $i < count($inventoryContents['ItemInstanceManagerData']['ItemBlocks']); $i++) {
                    if ($inventoryContents['ItemInstanceManagerData']['ItemBlocks'][$i]['ItemId'] === (int)$_GET['id']) {
                        unset($inventoryContents['ItemInstanceManagerData']['ItemBlocks'][$i]);

                        $inventoryContents['ItemInstanceManagerData']['ItemBlocks'] = array_values($inventoryContents['ItemInstanceManagerData']['ItemBlocks']);
                        $inventoryData['Data']['PlayerInventory'] = json_encode($inventoryContents);
                        file_put_contents($_GET['path'].'/PlayerInventorySaveData.json', json_encode($inventoryData), LOCK_EX);
                        break;
                    }
                }

                break;
            default:
                die('Invalid action specified' . backLink());
        }

        @header('location: ?path=' . $_GET['path']);
        echo '<script>location.href = "?path=' . urlencode($_GET['path']) . '"</script>';
        exit;
    }

    foreach ($inventoryContents['EquippedItems'] as &$equippedItem) {
        $equippedItem['equipped'] = true;
    }
    
    $inventoryContents['ItemInstanceManagerData']['ItemBlocks'] = array_merge($inventoryContents['EquippedItems'], $inventoryContents['ItemInstanceManagerData']['ItemBlocks']);
    
    $addedItems = [];
    foreach ($inventoryContents['ItemInstanceManagerData']['ItemBlocks'] as $itemInstance) {
        $addedItems[] = $itemInstance['ItemId'];
    }

    echo '<form style="display: inline-block;margin: 0;" method="get"><input type="hidden" name="action" value="add"><input type="hidden" name="path" value="' . ($_GET['path'] ?? '') . '"><select name="id"><option value="0" selected disabled>-- select item --</option>';

    foreach ($itemData as $itemDataEntry) {
        if (in_array($itemDataEntry['id'], $addedItems) || isset($itemDataEntry['hidden']) || isset($itemDataEntry['equippable']))
            continue;

        echo '<option value="' . $itemDataEntry['id'] . '">' . ($itemDataEntry['name'] == '??' ? $itemDataEntry['id'] . ' - unknown item' : $itemDataEntry['name'] . ' - ' . $itemDataEntry['reference'] . ' [' . $itemDataEntry['id'] . ']') . '' . ($itemDataEntry['unknown'] ? ' &nbsp; ⚠️' : '') . '</option>';
    }

    echo '</select><input type="submit" value="ADD"></form>';

    echo '<table border="1" cellpadding="1" cellspacing="1">';
    echo '<thead><td>ID</td><td>Name</td><td>Count</td><td>Max</td><td>Unique</td><td>Essential</td><td></td></thead>';
    echo '<tbody>';
    foreach ($inventoryContents['ItemInstanceManagerData']['ItemBlocks'] as $itemInstance) {
        $itemId = $itemInstance['ItemId'];
        $itemName = 'Unrecognized item';
        $itemReference = 'UnrecognizedItem';
        $itemCount = '?';
        $itemMaxCount = '?';
        $itemUnique = false;
        $itemEssential = true;
        $itemEquipped = false;
        $itemHidden = false;
        $itemUnknown = false;

        if (isset($itemData[$itemId])) {
            $itemName = $itemData[$itemId]['name'];
            $itemReference = $itemData[$itemId]['reference'];
            $itemCount = $itemInstance['TotalCount'] ?? 1;
            $itemMaxCount = $itemData[$itemInstance['ItemId']]['max'];
            $itemUnique = isset($itemData[$itemInstance['ItemId']]['item_data']);
            $itemEssential = isset($itemData[$itemInstance['ItemId']]['essential']);
            $itemEquipped = isset($itemInstance['equipped']);
            $itemHidden = isset($itemData[$itemId]['hidden']);
            $itemUnknown = isset($itemData[$itemId]['unknown']);
        }

        echo '<tr>';
        echo '<td>' . $itemId . '</td><td>' . $itemName . ' &nbsp; (' . $itemReference . ')' . ($itemUnknown ? ' <span title="Unknown/Unobtainable item'."\n".'Data on this item is not available and keeping it might break your save">⚠️</span> ' : '') . '</td><td>' . $itemCount . '</td><td>' . $itemMaxCount . '</td>';
        echo '<td>' . ($itemUnique ? 'yes' : 'no') . '</td>';
        echo '<td>' . ($itemEssential ? 'yes' : 'no') . '</td>';
        
        echo '<td>';
        if (isset($itemData[$itemId]) && !$itemEssential && !$itemEquipped) {
            echo '<form style="display: inline-block;margin: 0;" method="get"><input type="hidden" name="action" value="modify"><input type="hidden" name="id" value="' . $itemId . '"><input type="hidden" name="path" value="' . ($_GET['path'] ?? '') . '">';
            echo '<input type="text" name="count" pattern="[-|+][0-9]+" size="3" placeholder="+1 / -1"><input type="submit" value="MODIFY">';
            echo '</form>';

            if ($itemCount < $itemMaxCount) {
                echo ' &nbsp; <a href="?path=' . $_GET['path'] . '&action=fill&id=' . $itemInstance['ItemId'] .'">FILL</a>';
            }

            if (!$itemEssential) {
                echo ' &nbsp; <a href="?path=' . $_GET['path'] . '&action=remove&id=' . $itemInstance['ItemId'] .'" onclick="return confirm(\'Removing: ' . $itemId . ' - ' . $itemName . '\n\nAre you sure?\')">DELETE</a>';
            }
        } elseif ($itemEquipped) {
            echo 'EQUIPPED';
        }
        echo '</td>';

        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}

?>