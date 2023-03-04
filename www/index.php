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
    'protectEssential' => true,
    'addItemData' => false,
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
    $title = str_replace([realpath($config['savesPath']) . DIRECTORY_SEPARATOR], '', $path);
    $mtime = date('Y-m-d H:i:s', $mtime);
    $selected = isset($_GET['path']) && $_GET['path'] == $path;

    $label = '';
    if (file_exists($path . '/label.txt')) {
        $label = ' [' . trim(file_get_contents($path . '/label.txt')) . ']';
    }

    echo '<option value="' . $path . '"' . ($selected ? 'selected' : '') . '>' . $title . $label . ' (' . $mtime . ')' . '</option>';
}

echo '</select><input type="submit" value="OPEN">&nbsp;<a href="?path=' . ($_GET['path'] ?? '') . '&print">PRINT INVENTORY ARRAY</a> &nbsp; <a href="?path=' . ($_GET['path'] ?? '') . '&extras">EXTRAS</a></form>';

if (isset($_GET['path']) && is_dir($_GET['path']) && file_exists($_GET['path'].'/PlayerInventorySaveData.json')) {
    $version = '0.0.0';

    if (file_exists($_GET['path'].'/PlayerInventorySaveData.json')) {
        $inventoryData = json_decode(file_get_contents($_GET['path'].'/PlayerInventorySaveData.json'), true);

        $inventoryContents = [];
        if (isset($inventoryData['Data']['PlayerInventory'])) {
            $inventoryContents = json_decode($inventoryData['Data']['PlayerInventory'], true);

            $version = $inventoryContents['ItemInstanceManagerData']['Version'];
        } else {
            die('PlayerInventorySaveData.json is not a valid');
        }
    } else {
        die('PlayerInventorySaveData.json does not exist');
    }

    if (file_exists($_GET['path'].'/PlayerClothingSystemSaveData.json')) {
        $clothingData = json_decode(file_get_contents($_GET['path'].'/PlayerClothingSystemSaveData.json'), true);

        $clothingContents = ['Clothing' => []];
        if (isset($clothingData['Data']['PlayerClothingSystem'])) {
            $clothingContents = json_decode($clothingData['Data']['PlayerClothingSystem'], true);
        }
    }

    if (isset($_GET['print'])) {
        echo '<pre>';
        print_r($inventoryContents);
        echo '</pre>';
        exit;
    }

    if (isset($_GET['extras'])) {
        if (isset($_GET['revive']) || isset($_GET['teleport'])) {
            if (file_exists($_GET['path'].'/PlayerStateSaveData.json')) {
                $playerStateData = json_decode(file_get_contents($_GET['path'].'/PlayerStateSaveData.json'), true);

                $playerStateContents = [];
                if (isset($playerStateData['Data']['PlayerState'])) {
                    $playerStateContents = json_decode($playerStateData['Data']['PlayerState'], true);
                } else {
                    die('PlayerStateSaveData.json is not a valid');
                }
            } else {
                die('PlayerStateSaveData.json does not exist');
            }

            if (isset($playerStateContents['_entries'])) {
                foreach ($playerStateContents['_entries'] as $entry) {
                    if ($entry['Name'] === 'player.position') {
                        $playerPosition = [
                            'x' => $entry['FloatArrayValue'][0],
                            'y' => $entry['FloatArrayValue'][1],
                            'z' => $entry['FloatArrayValue'][2],
                        ];
                        break;
                    }
                }
            } else {
                die('PlayerStateSaveData.json has no entries');
            }
            
            if (file_exists($_GET['path'].'/SaveData.json')) {
                $saveData = json_decode(file_get_contents($_GET['path'].'/SaveData.json'), true);

                $saveVailWorldSimContents = [];
                if (isset($saveData['Data']['VailWorldSim'])) {
                    $saveVailWorldSimContents = json_decode($saveData['Data']['VailWorldSim'], true);
                } else {
                    die('SaveData.json is not a valid');
                }
            } else {
                die('SaveData.json does not exist');
            }
        }

        if (isset($_GET['revive'])) {
            if (file_exists($_GET['path'].'/GameStateSaveData.json')) {
                $gameStateData = json_decode(file_get_contents($_GET['path'].'/GameStateSaveData.json'), true);

                $gameStateContents = [];
                if (isset($gameStateData['Data']['GameState'])) {
                    $gameStateContents = json_decode($gameStateData['Data']['GameState'], true);
                } else {
                    die('GameStateSaveData.json is not a valid');
                }
            } else {
                die('GameStateSaveData.json does not exist');
            }

            switch (strtolower($_GET['revive'])) {
                case 'kelvin':
                    if ($gameStateContents['IsRobbyDead'] === true) {
                        $gameStateContents['IsRobbyDead'] = false;

                        $hasEntry = false;
                        $uniqueIds = [];
                        foreach ($saveVailWorldSimContents['Actors'] as &$actor) {
                            $uniqueIds[] = $actor['UniqueId'];

                            if ($actor['TypeId'] === 9) {
                                $hasEntry = true;
                                $actor['State'] = 2;
                                $actor['Stats']['Health'] = 100;
                                $actor['Position'] = [
                                    'x' => $playerPosition['x'],
                                    'y' => $playerPosition['y'],
                                    'z' => $playerPosition['z'],
                                ];
                                break;
                            }
                        }

                        foreach ($saveVailWorldSimContents['KillStatsList'] as &$killStat) {
                            if ($killStat['TypeId'] === 9) {
                                $killStat['PlayerKilled'] = 0;
                            }
                        }

                        if (!$hasEntry) {
                            $uniqueId = 7013;
                            while (in_array($uniqueId, $uniqueIds)) {
                                $uniqueId++;
                            }

                            $saveVailWorldSimContents['Actors'][] = [
                                'UniqueId' => $uniqueId,
                                'TypeId' => 9,
                                'FamilyId' => 0,
                                'Position' => [
                                    'x' => $playerPosition['x'],
                                    'y' => $playerPosition['y'],
                                    'z' => $playerPosition['z'],
                                ],
                                'Rotation' => [
                                    'x' => 0.0,
                                    'y' => 0.0,
                                    'z' => 0.0,
                                    'w' => 0.0,
                                ],
                                'SpawnerId' => -1,
                                'ActorSeed' => -1,
                                'VariationId' => -1,
                                'State' => 2,
                                'GraphMask' => 1,
                                'OutfitId' => -1,
                                'NextGiftTime' => 0,
                                'LastVisitTime' => -100,
                                'Stats' => [
                                    'Health' => 100.0,
                                    'Anger' => 0,
                                    'Fear' => 0,
                                    'Fullness' => 0,
                                    'Hydration' => 0,
                                    'Energy' => 100.0,
                                    'Affection' => 0,
                                ],
                                'StateFlags' => 0,
                            ];
                        }
                    } else {
                        die('Kelvin is not dead' . backLink());
                    }

                    break;
                case 'virginia':
                    if ($gameStateContents['IsVirginiaDead'] === true) {
                        $gameStateContents['IsVirginiaDead'] = false;

                        $hasEntry = false;
                        $uniqueIds = [];
                        foreach ($saveVailWorldSimContents['Actors'] as &$actor) {
                            $uniqueIds[] = $actor['UniqueId'];

                            if ($actor['TypeId'] === 10) {
                                $hasEntry = true;
                                $actor['State'] = 2;
                                $actor['Stats']['Health'] = 100;
                                $actor['Position'] = [
                                    'x' => $playerPosition['x'],
                                    'y' => $playerPosition['y'] + 1.5,
                                    'z' => $playerPosition['z'],
                                ];
                                break;
                            }
                        }

                        foreach ($saveVailWorldSimContents['KillStatsList'] as &$killStat) {
                            if ($killStat['TypeId'] === 10) {
                                $killStat['PlayerKilled'] = 0;
                            }
                        }

                        if (!$hasEntry) {
                            $uniqueId = 6154;
                            while (in_array($uniqueId, $uniqueIds)) {
                                $uniqueId++;
                            }

                            $saveVailWorldSimContents['Actors'][] = [
                                'UniqueId' => $uniqueId,
                                'TypeId' => 10,
                                'FamilyId' => 0,
                                'Position' => [
                                    'x' => $playerPosition['x'],
                                    'y' => $playerPosition['y'] + 1.5,
                                    'z' => $playerPosition['z'],
                                ],
                                'Rotation' => [
                                    'x' => 0.0,
                                    'y' => 0.0,
                                    'z' => 0.0,
                                    'w' => 0.0,
                                ],
                                'SpawnerId' => -1,
                                'ActorSeed' => -1,
                                'VariationId' => -1,
                                'State' => 2,
                                'GraphMask' => 1,
                                'OutfitId' => -1,
                                'NextGiftTime' => 0,
                                'LastVisitTime' => -100,
                                'Stats' => [
                                    'Health' => 100.0,
                                    'Anger' => 0,
                                    'Fear' => 0,
                                    'Fullness' => 0,
                                    'Hydration' => 0,
                                    'Energy' => 100.0,
                                    'Affection' => 0,
                                ],
                                'StateFlags' => 0,
                            ];
                        }
                    } else {
                        die('Virginia is not dead' . backLink());
                    }
                    break;

                default:
                    die('Bad target');
            }

            $gameStateData['Data']['GameState'] = json_encode($gameStateContents, JSON_PRESERVE_ZERO_FRACTION);
            file_put_contents($_GET['path'].'/GameStateSaveData.json', json_encode($gameStateData), LOCK_EX);
            
            $saveData['Data']['VailWorldSim'] = json_encode($saveVailWorldSimContents, JSON_PRESERVE_ZERO_FRACTION);
            file_put_contents($_GET['path'].'/SaveData.json', json_encode($saveData), LOCK_EX);
            
            die('Done' . backLink());
            exit;
        } elseif (isset($_GET['teleport'])) {
            if (!isset($playerPosition)) {
                die('Player position is not known');
            }

            switch (strtolower($_GET['teleport'])) {
                case 'kelvin':
                    foreach ($saveVailWorldSimContents['Actors'] as &$actor) {
                        if ($actor['TypeId'] === 9) {
                            $actor['Position'] = [
                                'x' => $playerPosition['x'],
                                'y' => $playerPosition['y'] + 1.5,
                                'z' => $playerPosition['z'],
                            ];
                            break;
                        }
                    }

                    break;
                case 'virginia':
                    foreach ($saveVailWorldSimContents['Actors'] as &$actor) {
                        if ($actor['TypeId'] === 10) {
                            $actor['Position'] = [
                                'x' => $playerPosition['x'],
                                'y' => $playerPosition['y'] + 1.5,
                                'z' => $playerPosition['z'],
                            ];
                            break;
                        }
                    }

                    break;
                default:
                    die('Bad target');
            }

            $saveData['Data']['VailWorldSim'] = json_encode($saveVailWorldSimContents, JSON_PRESERVE_ZERO_FRACTION);
            file_put_contents($_GET['path'].'/SaveData.json', json_encode($saveData), LOCK_EX);
            
            die('Done' . backLink());
            exit;
        }

        echo '<a href="?path=' . ($_GET['path'] ?? '') . '&extras&revive=kelvin">Revive Kelvin</a><br>';
        echo '<a href="?path=' . ($_GET['path'] ?? '') . '&extras&revive=virginia">Revive Virginia</a><br>';
        echo '<a href="?path=' . ($_GET['path'] ?? '') . '&extras&teleport=kelvin">Teleport Kelvin to your location</a><br>';
        echo '<a href="?path=' . ($_GET['path'] ?? '') . '&extras&teleport=virginia">Teleport Virginia to your location</a><br>';

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
                if ($config['addItemData'] === true && isset($itemData[$_GET['id']]['item_data'])) {
                    $uniqueItems[] = addCurrentVersionToItemArray($itemData[$_GET['id']]['item_data'], $version);
                }

                $inventoryContents['ItemInstanceManagerData']['ItemBlocks'][] = [
                    'ItemId' => (int)$_GET['id'],
                    'TotalCount' => 1,
                    'UniqueItems' => $uniqueItems
                ];

                $inventoryContents['ItemInstanceManagerData']['ItemBlocks'] = array_values($inventoryContents['ItemInstanceManagerData']['ItemBlocks']);
                $inventoryData['Data']['PlayerInventory'] = json_encode($inventoryContents, JSON_PRESERVE_ZERO_FRACTION);
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

                        if ($config['addItemData'] === true && isset($itemData[$_GET['id']]['item_data'])) {
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
                    $inventoryData['Data']['PlayerInventory'] = json_encode($inventoryContents, JSON_PRESERVE_ZERO_FRACTION);
                    file_put_contents($_GET['path'].'/PlayerInventorySaveData.json', json_encode($inventoryData), LOCK_EX);
                    break;
                }
            case 'remove':
                if ($config['protectEssential'] === true && isset($itemData[$_GET['id']]['essential'])) {
                    die('Item cannot be removed' . backLink());
                }

                for ($i = 0; $i < count($inventoryContents['ItemInstanceManagerData']['ItemBlocks']); $i++) {
                    if ($inventoryContents['ItemInstanceManagerData']['ItemBlocks'][$i]['ItemId'] === (int)$_GET['id']) {
                        unset($inventoryContents['ItemInstanceManagerData']['ItemBlocks'][$i]);

                        $inventoryContents['ItemInstanceManagerData']['ItemBlocks'] = array_values($inventoryContents['ItemInstanceManagerData']['ItemBlocks']);
                        $inventoryData['Data']['PlayerInventory'] = json_encode($inventoryContents, JSON_PRESERVE_ZERO_FRACTION);
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

    $clothes = [];
    foreach ($clothingContents['Clothing'] as $itemId) {
        $wornItem['equipped'] = true;
        $clothes[] = [
            "ItemId" => $itemId,
            "TotalCount" => 1,
            "UniqueItems" => [],
            "equipped" => true,
        ];
    }
    
    $inventoryContents['ItemInstanceManagerData']['ItemBlocks'] = array_merge($inventoryContents['EquippedItems'], $clothes, $inventoryContents['ItemInstanceManagerData']['ItemBlocks']);
    
    $addedItems = [];
    foreach ($inventoryContents['ItemInstanceManagerData']['ItemBlocks'] as $itemInstance) {
        $addedItems[] = $itemInstance['ItemId'];
    }

    echo '<form style="display: inline-block;margin: 0;" method="get"><input type="hidden" name="action" value="add"><input type="hidden" name="path" value="' . ($_GET['path'] ?? '') . '"><select name="id" id="add-search-select"><option value="0" selected disabled>-- select item --</option>';

    foreach ($itemData as $itemDataEntry) {
        if (in_array($itemDataEntry['id'], $addedItems) || isset($itemDataEntry['hidden']) || isset($itemDataEntry['equippable']))
            continue;

        echo '<option value="' . $itemDataEntry['id'] . '">' . ($itemDataEntry['name'] == '??' ? $itemDataEntry['id'] . ' - unknown item' : $itemDataEntry['name'] . ' - ' . $itemDataEntry['reference'] . ' [' . $itemDataEntry['id'] . ']') . '' . ($itemDataEntry['unknown'] ? ' &nbsp; ⚠️' : '') . '</option>';
    }

    echo '</select><input type="submit" value="ADD"><input type="text" name="search" placeholder="SEARCH" id="add-search"><input type="button" value="RESET" onclick="document.getElementById(\'add-search\').value=\'\';document.getElementById(\'add-search\').dispatchEvent(new Event(\'input\', {bubbles:true}));"></form>';

    echo '<table border="1" cellpadding="1" cellspacing="1">';
    echo '<thead><td>ID</td><td>Name</td><td>Count</td><td>Max</td><td>Essential</td><td></td></thead>';
    echo '<tbody>';
    foreach ($inventoryContents['ItemInstanceManagerData']['ItemBlocks'] as $itemInstance) {
        $itemId = $itemInstance['ItemId'];
        $itemName = 'Unrecognized item';
        $itemReference = 'UnrecognizedItem';
        $itemCount = '?';
        $itemMaxCount = '?';
        $itemEssential = true;
        $itemEquipped = false;
        $itemHidden = false;
        $itemUnknown = false;

        if (isset($itemData[$itemId])) {
            $itemName = $itemData[$itemId]['name'];
            $itemReference = $itemData[$itemId]['reference'];
            $itemCount = $itemInstance['TotalCount'] ?? 1;
            $itemMaxCount = $itemData[$itemInstance['ItemId']]['max'];
            $itemEssential = isset($itemData[$itemInstance['ItemId']]['essential']);
            $itemEquipped = isset($itemInstance['equipped']);
            $itemHidden = isset($itemData[$itemId]['hidden']);
            $itemUnknown = isset($itemData[$itemId]['unknown']);
        }

        echo '<tr>';
        echo '<td>' . $itemId . '</td><td>' . $itemName . ' &nbsp; (' . $itemReference . ')' . ($itemUnknown ? ' <span title="Unknown/Unobtainable item'."\n".'Data on this item is not available and keeping it might break your save">⚠️</span> ' : '') . '</td><td>' . $itemCount . '</td><td>' . $itemMaxCount . '</td>';
        echo '<td>' . ($itemEssential ? 'yes' : 'no') . '</td>';
        
        echo '<td>';
        if (isset($itemData[$itemId]) && (!$itemEssential || ($itemEssential && $config['protectEssential'] !== true)) && !$itemEquipped) {
            echo '<form style="display: inline-block;margin: 0;" method="get"><input type="hidden" name="action" value="modify"><input type="hidden" name="id" value="' . $itemId . '"><input type="hidden" name="path" value="' . ($_GET['path'] ?? '') . '">';
            echo '<input type="text" name="count" pattern="[-|+][0-9]+" size="3" placeholder="+1 / -1"><input type="submit" value="MODIFY">';
            echo '</form>';

            if ($itemCount < $itemMaxCount) {
                echo ' &nbsp; <a href="?path=' . $_GET['path'] . '&action=fill&id=' . $itemInstance['ItemId'] .'">FILL</a>';
            }

            if (!$itemEssential || ($itemEssential && $config['protectEssential'] !== true)) {
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
<script type="text/javascript">
var selectInputOriginalText = '';
document.getElementById("add-search").addEventListener('input', function(e) {
    var selectInput = document.getElementById("add-search-select");

    if (selectInputOriginalText == '') {
        selectInputOriginalText = selectInput.querySelector('[value="0"]').innerHTML;
    }

    if (this.value == "") {
        selectInput.querySelector('[value="0"]').innerHTML = selectInputOriginalText;
    }

    var options = selectInput.querySelectorAll('option');
    var hasOption = 0;
    for (let i = 0; i < options.length; i++) {
        if (options[i].value == "0")
            continue;

        if (options[i].innerHTML.toLowerCase().includes(this.value) || this.value == "") {
            options[i].removeAttribute('hidden');
            hasOption++;
        } else {
            options[i].setAttribute('hidden', true);
        }
    }

    if (!hasOption) {
        selectInput.querySelector('[value="0"]').innerHTML = '-- no items found --';
    } else if (this.value != "") {
        selectInput.querySelector('[value="0"]').innerHTML = '-- found ' + hasOption + ' items --';
    }
});
</script>