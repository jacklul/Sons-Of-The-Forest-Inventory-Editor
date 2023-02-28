<?php

$projectName = preg_replace("/[^A-Za-z0-9]/", '-', basename(__DIR__));
$outputDir = __DIR__ . '/dist/';
$tmpDir = sys_get_temp_dir() . '/' . $projectName . '-build/';

$downloads = [
    'https://github.com/cztomczak/phpdesktop/releases/download/chrome-v57.0-rc/phpdesktop-chrome-57.0-rc-php-7.1.3.zip',
];

#------------------------------------------------------------------------------#

function rrmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                    rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                else
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
        rmdir($dir);
    }
}

function recurseCopy(string $sourceDirectory, string $destinationDirectory, string $childFolder = '') {
    $directory = opendir($sourceDirectory);

    if (is_dir($destinationDirectory) === false) {
        mkdir($destinationDirectory, 0777);
    }

    if ($childFolder !== '') {
        if (is_dir($destinationDirectory . '/' . $childFolder) === false) {
            mkdir($destinationDirectory . '/' . $childFolder, 0777);
        }

        while (($file = readdir($directory)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (is_dir($sourceDirectory . '/' . $file) === true) {
                recurseCopy($sourceDirectory . '/' . $file, $destinationDirectory . '/' . $childFolder . '/' . $file);
            } else {
                copy($sourceDirectory . '/' . $file, $destinationDirectory . '/' . $childFolder . '/' . $file);
            }
        }

        closedir($directory);

        return;
    }

    while (($file = readdir($directory)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        if (is_dir("$sourceDirectory/$file") === true) {
            recurseCopy($sourceDirectory . '/' . $file, $destinationDirectory . '/' . $file);
        } else {
            copy($sourceDirectory . '/' . $file, $destinationDirectory . '/' . $file);
        }
    }

    closedir($directory);
}

function fileRenameMoveCopyLog($file1, $files2, $operation) {
    $operations = [
        'copy' => 'Copying',
        'move' => 'Moving',
        'rename' => 'Renaming',
    ];

    echo $operations[strtolower($operation)] . ': ' . str_replace(__DIR__ . '/', '', $file1) . ' => ' . str_replace(__DIR__ . '/', '', $files2) . PHP_EOL;
}

function chmod_r($path) {
    $dir = new DirectoryIterator($path);
    foreach ($dir as $item) {
        chmod($item->getPathname(), 0755);
        if ($item->isDir() && !$item->isDot()) {
            chmod_r($item->getPathname());
        }
    }
}

#------------------------------------------------------------------------------#

$outputDir = rtrim($outputDir, '/');

if (!is_dir($outputDir)) {
    mkdir($outputDir);
}

$outputDir = realpath($outputDir);

if (!is_dir($tmpDir)) {
    mkdir($tmpDir);
}

$options = [
    "http" => [
        "user_agent" => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.77 Safari/537.36'
    ]
];
$context = stream_context_create($options);

// Get latest PHP 7.4
echo 'Getting latest PHP 7.4 download link...' . PHP_EOL;

$phpReleases = file_get_contents('https://windows.php.net/download/', false, $context);

preg_match_all('/(\/downloads\/releases\/php-7\.4\.\d+-nts-Win32-vc15-x64\.zip)/', $phpReleases, $matches);

if (isset($matches[1][0])) {
    $downloads[] = 'https://windows.php.net'  . $matches[1][0];
} else {
    exit('Failed to find latest PHP 7.4 download link!' . PHP_EOL);
}

$phpDesktopDir = '';
$phpDir = '';

// Download and extract archives
foreach ($downloads as $download) {
    if (!file_exists($tmpDir . basename($download))) {
        echo 'Downloading "' . $download . '"' . PHP_EOL;

        if ($file = file_get_contents($download, false, $context)) {
            file_put_contents($tmpDir . basename($download), $file);
        }
    }

    $basenameWithoutExtension = explode('.', basename($download));
    unset($basenameWithoutExtension[count($basenameWithoutExtension) - 1]);
    $basenameWithoutExtension = implode('.', $basenameWithoutExtension);

    if (!is_dir($tmpDir . $basenameWithoutExtension)) {
        $zip = new ZipArchive;
        if ($zip->open($tmpDir . basename($download))) {
            $zip->extractTo($tmpDir . $basenameWithoutExtension);
            $zip->close();

            echo 'Extracted "' . basename($download) . '" to "' . $tmpDir . $basenameWithoutExtension . '"' . PHP_EOL;

            strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' && chmod_r($tmpDir . $basenameWithoutExtension);
        } else {
            echo 'Failed to open "' . basename($download) . '"' . PHP_EOL;
        }
    }

    if (strpos($basenameWithoutExtension, 'phpdesktop-') !== false) {
        $phpDesktopDir = $basenameWithoutExtension;
    } elseif (strpos($basenameWithoutExtension, 'php-') !== false) {
        $phpDir = $basenameWithoutExtension;
    }
}

if (is_dir($outputDir)) {
    echo 'Cleaning up "' . str_replace(__DIR__ . '/', '', $outputDir) . '"...' . PHP_EOL;

    rrmdir($outputDir);
    mkdir($outputDir);
}

echo 'Copying dependencies...' . PHP_EOL;

$phpDesktopFilesToCopy = [
    'locales/en-US.pak',
    'cef.pak',
    'cef_100_percent.pak',
    'cef_200_percent.pak',
    'cef_extensions.pak',
    'chrome_elf.dll',
    'd3dcompiler_43.dll',
    'd3dcompiler_47.dll',
    'devtools_resources.pak',
    'icudtl.dat',
    'libcef.dll',
    'libEGL.dll',
    'libGLESv2.dll',
    'natives_blob.bin',
    'snapshot_blob.bin',
    'widevinecdmadapter.dll',
    'phpdesktop-chrome.exe',
    'license.txt',
];

if (!empty($phpDesktopDir) && !file_exists($outputDir . '/phpdesktop-chrome.exe')) {
    if (is_dir($tmpDir . $phpDesktopDir . '/' . $phpDesktopDir)) {
        if (empty($phpDesktopFilesToCopy)) {
            recurseCopy($tmpDir . $phpDesktopDir . '/' . $phpDesktopDir, $outputDir);

            fileRenameMoveCopyLog($tmpDir . $phpDesktopDir . '/' . $phpDesktopDir, $outputDir, 'copy');

            if (is_dir($outputDir . '/php/')) {
                rrmdir($outputDir . '/php/');
                rrmdir($outputDir . '/www/');
            }
        } else {
            foreach ($phpDesktopFilesToCopy as $fileToCopy) {
                if (file_exists($tmpDir . $phpDesktopDir . '/' . $phpDesktopDir . '/' . $fileToCopy)) {
                    if (strpos($fileToCopy, '/') !== false) {
                        $tmp = explode('/', $fileToCopy);
                        unset($tmp[count($tmp) - 1]);
                        mkdir($outputDir . '/' . implode('/', $tmp), 0777, true);
                    }

                    copy($tmpDir . $phpDesktopDir . '/' . $phpDesktopDir . '/' . $fileToCopy, $outputDir . '/' . $fileToCopy);
            
                    fileRenameMoveCopyLog($tmpDir . $phpDesktopDir . '/' . $phpDesktopDir . '/' . $fileToCopy,$outputDir . '/' . $fileToCopy, 'copy');
                }
            }
        }
    }
}

$phpFilesToCopy = [
    'php.exe',
    'php7.dll',
    'php-cgi.exe',
    'license.txt',
];

if (!empty($phpDir) && !file_exists($outputDir . '/php/php.exe')) {
    if (is_dir($tmpDir . $phpDir)) {
        if (empty($phpFilesToCopy)) {
            recurseCopy($tmpDir . $phpDir, $outputDir . '/php');

            fileRenameMoveCopyLog($tmpDir . $phpDir, $outputDir . '/php', 'copy');
        } else {
            if (!is_dir($outputDir . '/php')) {
                mkdir($outputDir . '/php');
            }

            foreach ($phpFilesToCopy as $fileToCopy) {
                if (file_exists($tmpDir . $phpDir . '/' . $fileToCopy)) {
                    if (strpos($fileToCopy, '/') !== false) {
                        $tmp = explode('/', $fileToCopy);
                        unset($tmp[count($tmp) - 1]);
                        mkdir($outputDir . '/' . implode('/', $tmp), 0777, true);
                    }

                    copy($tmpDir . $phpDir . '/' . $fileToCopy, $outputDir . '/php/' . $fileToCopy);
            
                    fileRenameMoveCopyLog($tmpDir . $phpDir . '/' . $fileToCopy,$outputDir . '/php/' . $fileToCopy, 'copy');
                }
            }
        }
    }
}

echo 'Renaming files...' . PHP_EOL;

$filesToRename = [
    'license.txt' => 'phpdesktop-license.txt',
];

foreach ($filesToRename as $fileToRename => $fileToRenameTo) {
    if (file_exists($outputDir . '/' . $fileToRename)) {
        rename($outputDir . '/' . $fileToRename, $outputDir . '/' . $fileToRenameTo);

        fileRenameMoveCopyLog($outputDir . '/' . $fileToRename, $outputDir . '/' . $fileToRenameTo, 'rename');
    }
}

echo 'Copying project files...' . PHP_EOL;

$filesToCopy = [
    __DIR__ . '/LICENSE',
    __DIR__ . '/php/php.ini',
    __DIR__ . '/settings.json',
    __DIR__ . '/www/',
];

foreach ($filesToCopy as $fileToCopy) {
    $destination = str_replace(__DIR__, $outputDir, $fileToCopy);
    $destinationDir = dirname($destination);

    if (is_dir($fileToCopy)) {
        recurseCopy($fileToCopy, $destination);
    } else {
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        copy($fileToCopy, $destination);
    }

    fileRenameMoveCopyLog($fileToCopy, $destination, 'copy');
}

echo 'Creating ZIP archive...' . PHP_EOL;

$zip = new ZipArchive();
$zip->open($outputDir . '/' . $projectName . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($outputDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $name => $file) {
    $filePath = $file->getRealPath();
    $relativePath = substr($filePath, strlen($outputDir) + 1);
    $relativePath = str_replace('\\', '/', $relativePath);

    if (empty($relativePath))
        continue;

    if ($file->isDir()) {
        echo 'Creating "' . $relativePath . '"...' . PHP_EOL;
        $zip->addEmptyDir($projectName . '/' . $relativePath);
    } else {
        echo 'Adding "' . $relativePath . '"...' . PHP_EOL;
        $zip->addFile($filePath, $projectName . '/' . $relativePath);
    }
}

echo 'Finishing archive...' . PHP_EOL;

$zip->close();

echo 'Finished' . PHP_EOL;
