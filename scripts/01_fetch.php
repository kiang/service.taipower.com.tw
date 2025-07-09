<?php

function fetchData($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        echo "cURL Error: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "HTTP Error: $httpCode\n";
        return false;
    }
    
    return $result;
}

$rawDir = dirname(__DIR__) . '/raw';
if (!file_exists($rawDir)) {
    mkdir($rawDir, 0755, true);
}

$docsDir = dirname(__DIR__) . '/docs';
if (!file_exists($docsDir)) {
    mkdir($docsDir, 0755, true);
}

$csvFile = $docsDir . '/cunli.csv';
$csvData = [];
$csvHeader = ['county_name', 'town_name', 'cunli_name', 'c0', 'c1', 'fcid', 'gid'];

echo "Fetching counties from Taipower API...\n";

$countiesUrl = 'https://service.taipower.com.tw/psvs1/nj_psvs_attr/RangeInfo/Country';
$countiesData = fetchData($countiesUrl);

if ($countiesData === false) {
    echo "Failed to fetch counties data\n";
    exit(1);
}

file_put_contents($rawDir . '/counties.json', $countiesData);
echo "Saved counties data to raw/counties.json\n";

$counties = json_decode($countiesData, true);

if (!$counties) {
    echo "Failed to parse counties JSON\n";
    exit(1);
}

echo "Found " . count($counties) . " counties\n";

foreach ($counties as $county) {
    $gid = $county['gid'];
    $fcid = $county['fcid'];
    $countyName = trim($county['name'] ?? 'Unknown');
    
    echo "Fetching data for county: $countyName (gid: $gid, fcid: $fcid)\n";
    
    $townUrl = "https://service.taipower.com.tw/psvs1/nj_psvs_attr/RangeInfo/rangeFG?gid=$gid&fcid=$fcid";
    $townData = fetchData($townUrl);
    
    if ($townData === false) {
        echo "Failed to fetch town data for county: $countyName\n";
        continue;
    }
    
    $filename = $rawDir . '/towns_' . $gid . '_' . $fcid . '.json';
    file_put_contents($filename, $townData);
    
    $towns = json_decode($townData, true);
    
    if (!$towns) {
        echo "Failed to parse town JSON for county: $countyName\n";
        continue;
    }
    
    echo "Found " . count($towns) . " towns in $countyName, saved to " . basename($filename) . "\n";
    
    foreach ($towns as $town) {
        if (!isset($town['info'])) {
            continue;
        }
        
        $infoItems = explode(',', $town['info']);
        
        foreach ($infoItems as $infoItem) {
            $fields = explode(':', $infoItem);
            if (count($fields) < 5) {
                continue;
            }
            
            $townName = trim($fields[0]);
            $infoFcid = $fields[1];
            $infoGid = $fields[2];
            $c0 = (int)$fields[3];
            $c1 = (int)$fields[4];
            
            if ($c0 > 0 || $c1 > 0) {
                echo "  Fetching next level data for: $townName (gid: $infoGid, fcid: $infoFcid, c0: $c0, c1: $c1)\n";
                
                $nextLevelUrl = "https://service.taipower.com.tw/psvs1/nj_psvs_attr/RangeInfo/rangeFG?gid=$infoGid&fcid=$infoFcid";
                $nextLevelData = fetchData($nextLevelUrl);
                
                if ($nextLevelData === false) {
                    echo "    Failed to fetch next level data for: $townName\n";
                    continue;
                }
                
                $nextLevelFilename = $rawDir . '/level_' . $infoGid . '_' . $infoFcid . '.json';
                file_put_contents($nextLevelFilename, $nextLevelData);
                
                $nextLevelJson = json_decode($nextLevelData, true);
                if ($nextLevelJson) {
                    echo "    Saved " . count($nextLevelJson) . " items to " . basename($nextLevelFilename) . "\n";
                    
                    foreach ($nextLevelJson as $cunli) {
                        if (!isset($cunli['info'])) {
                            continue;
                        }
                        
                        $cunliInfoItems = explode(',', $cunli['info']);
                        
                        foreach ($cunliInfoItems as $cunliInfoItem) {
                            $cunliFields = explode(':', $cunliInfoItem);
                            if (count($cunliFields) < 5) {
                                continue;
                            }
                            
                            $cunliName = trim($cunliFields[0]);
                            $cunliFcid = $cunliFields[1];
                            $cunliGid = $cunliFields[2];
                            $cunliC0 = (int)$cunliFields[3];
                            $cunliC1 = (int)$cunliFields[4];
                            
                            $csvData[] = [$countyName, $townName, $cunliName, $cunliC0, $cunliC1, $cunliFcid, $cunliGid];
                        }
                    }
                }
                
                usleep(100000);
            }
        }
    }
    
    usleep(100000);
}

echo "Sorting data by gid...\n";

// Sort by gid (column 6)
usort($csvData, function($a, $b) {
    return $a[6] <=> $b[6];
});

// Write sorted data to CSV
$csvHandle = fopen($csvFile, 'w');
fputcsv($csvHandle, $csvHeader);
foreach ($csvData as $row) {
    fputcsv($csvHandle, $row);
}
fclose($csvHandle);

echo "All raw data saved to raw/ directory\n";
echo "CSV file generated: docs/cunli.csv (sorted by gid)\n";

?>