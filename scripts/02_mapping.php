<?php

echo "Loading Taiwan basecode data from TopJSON...\n";

$topojsonFile = '/home/kiang/public_html/taiwan_basecode/cunli/topo/20240807.json';
if (!file_exists($topojsonFile)) {
    echo "Error: TopJSON file not found at $topojsonFile\n";
    exit(1);
}

$topojsonData = json_decode(file_get_contents($topojsonFile), true);
if (!$topojsonData) {
    echo "Error: Failed to parse TopJSON file\n";
    exit(1);
}

echo "Building cunli lookup table from TopJSON...\n";

$cunliLookup = [];

if (isset($topojsonData['objects'])) {
    foreach ($topojsonData['objects'] as $objectName => $object) {
        if (isset($object['geometries'])) {
            foreach ($object['geometries'] as $geometry) {
                if (isset($geometry['properties'])) {
                    $props = $geometry['properties'];
                    $countyName = trim($props['COUNTYNAME'] ?? '');
                    $townName = trim($props['TOWNNAME'] ?? '');
                    $cunliName = trim($props['VILLNAME'] ?? '');
                    $villCode = $props['VILLCODE'] ?? '';
                    
                    if ($countyName && $townName && $cunliName && $villCode) {
                        $key = $countyName . '|' . $townName . '|' . $cunliName;
                        $cunliLookup[$key] = $villCode;
                    }
                }
            }
        }
    }
}

echo "Found " . count($cunliLookup) . " cunli records in basecode data\n";

// Manual mapping for unmatched records
$manualMapping = [
    '嘉義縣|朴子市|雙溪里' => '10010020012',
    '嘉義縣|中埔鄉|鹽館村' => '10010130002',
    '嘉義縣|中埔鄉|石弄村' => '10010130014',
    '嘉義縣|梅山鄉|雙溪村' => '10010150010',
    '嘉義縣|梅山鄉|瑞峰村' => '10010150015',
    '嘉義縣|竹崎鄉|文峰村' => '10010140018',
    '嘉義縣|民雄鄉|雙福村' => '10010050023',
    '臺南市|鹽水區|頭港里' => '67000020033',
    '臺南市|七股區|鹽埕里' => '67000150007',
    '臺南市|安南區|鹽田里' => '67000350019',
    '臺南市|麻豆區|寮部里' => '67000070015',
    '臺南市|山上區|玉峰里' => '67000220005',
    '臺南市|官田區|南部里' => '67000100008',
    '臺南市|永康區|鹽行里' => '67000310010',
    '臺南市|永康區|鹽興里' => '67000310043',
    '臺南市|永康區|鹽洲里' => '67000310029',
    '臺南市|新化區|山腳里' => '67000180016',
    '雲林縣|斗六市|崙峰里' => '10009010018',
    '雲林縣|水林鄉|舊埔村' => '10009200021',
    '雲林縣|麥寮鄉|瓦瑤村' => '10009130003',
    '嘉義市|西區|磚瑤里' => '10020020018',
    '高雄市|湖內區|公館里' => '64000250004',
    '高雄市|鳥松區|帝埔里' => '64000180004',
    '高雄市|阿蓮區|峰山里' => '64000230003',
    '屏東縣|萬丹鄉|廈北村' => '10013050012',
    '屏東縣|里港鄉|三部村' => '10013090012',
    '新竹縣|北埔鄉|水砌村' => '10004090004',
    '彰化縣|芳苑鄉|頂部村' => '10007230013',
];

// Merge manual mapping into main lookup
$cunliLookup = array_merge($cunliLookup, $manualMapping);
echo "Added " . count($manualMapping) . " manual mappings\n";

echo "Loading Taipower cunli data...\n";

$cunliCsvFile = dirname(__DIR__) . '/docs/cunli.csv';
if (!file_exists($cunliCsvFile)) {
    echo "Error: cunli.csv not found at $cunliCsvFile\n";
    exit(1);
}

$taipowerCsvFile = dirname(__DIR__) . '/docs/taipower.csv';
$taipowerData = [];
$taipowerHeader = ['VILLCODE', 'c0', 'c1'];

$cunliHandle = fopen($cunliCsvFile, 'r');
$header = fgetcsv($cunliHandle);

$matchedCount = 0;
$unmatchedCount = 0;
$processedCount = 0;
$unmatchedRecords = [];

echo "Processing cunli records...\n";

while (($row = fgetcsv($cunliHandle)) !== false) {
    $processedCount++;
    
    if (count($row) < 7) {
        continue;
    }
    
    $countyName = trim($row[0]);
    $townName = trim($row[1]);
    $cunliName = trim($row[2]);
    $c0 = (int)$row[3];
    $c1 = (int)$row[4];
    
    $key = $countyName . '|' . $townName . '|' . $cunliName;
    
    if (isset($cunliLookup[$key])) {
        $villCode = $cunliLookup[$key];
        $taipowerData[] = [$villCode, $c0, $c1];
        $matchedCount++;
    } else {
        $unmatchedCount++;
        $unmatchedRecords[$key] = '';
        echo "  Unmatched: $countyName > $townName > $cunliName\n";
    }
    
    if ($processedCount % 1000 == 0) {
        echo "  Processed: $processedCount records\n";
    }
}

fclose($cunliHandle);

echo "\nSorting data by VILLCODE...\n";

// Sort by VILLCODE (column 0)
usort($taipowerData, function($a, $b) {
    return strcmp($a[0], $b[0]);
});

// Write sorted data to CSV
$taipowerHandle = fopen($taipowerCsvFile, 'w');
fputcsv($taipowerHandle, $taipowerHeader);
foreach ($taipowerData as $row) {
    fputcsv($taipowerHandle, $row);
}
fclose($taipowerHandle);

echo "\nMapping completed:\n";
echo "  Processed: $processedCount records\n";
echo "  Matched: $matchedCount records\n";
echo "  Unmatched: $unmatchedCount records\n";
echo "  Output file: docs/taipower.csv (sorted by VILLCODE)\n";

if ($unmatchedCount > 0) {
    echo "\nRemaining unmatched records:\n";
    foreach ($unmatchedRecords as $key => $value) {
        echo "  " . str_replace('|', ' > ', $key) . "\n";
    }
    
    echo "\nNote: Some records could not be matched. This may be due to:\n";
    echo "- Different naming conventions\n";
    echo "- Administrative boundary changes\n";
    echo "- Data inconsistencies\n";
} else {
    echo "\nAll records matched successfully!\n";
}

?>