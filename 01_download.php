<?php

$tmpPath = __DIR__ . '/tmp/hospitals';
if (!file_exists($tmpPath)) {
    mkdir($tmpPath, 0777, true);
}
$targetPath = __DIR__ . '/data/hospitals';
if (!file_exists($targetPath)) {
    mkdir($targetPath, 0777, true);
}

// get PageNum
$page1File = __DIR__ . '/tmp/pageNumber';
if(!file_exists($page1File)) {
    file_put_contents($page1File, file_get_contents('http://www.nhi.gov.tw/Query/query3_list.aspx'));
}
$page1 = file_get_contents($page1File);
$parts = explode('<span id="lblDataCount"><font color="Red">', $page1);
$parts = explode('</font></span>', $parts[1]);

$listUrl = 'http://www.nhi.gov.tw/Query/query3_list.aspx?PageNum=' . $parts[0];
$listFile = $tmpPath . '/list';
if (!file_exists($listFile)) {
    file_put_contents($listFile, file_get_contents($listUrl));
}
$list = file_get_contents($listFile);
$fh = fopen(__DIR__ . '/data/hospitals.csv', 'w');
fputcsv($fh, array(
    '醫事機構代碼',
    '醫事機構名稱',
    '特約類別',
    '電話',
    '地址',
));

$token = 'Query3_Detail.aspx?HospID=';
$tokenLength = strlen($token);
$pos = strpos($list, $token);
$count = 0;
while (false !== $pos) {
    $pos += $tokenLength;
    $posEnd = strpos($list, '\'', $pos);
    $nhiId = substr($list, $pos, $posEnd - $pos);
    ++$count;
    echo "processing {$nhiId} [{$count} / {$parts[0]}]\n";
    $hospitalUrl = 'http://www.nhi.gov.tw/Query/Query3_Detail.aspx?HospID=' . $nhiId;
    $hospitalFile = $tmpPath . '/f_' . $nhiId;
    if (!file_exists($hospitalFile) || filesize($hospitalFile) === 0) {
        file_put_contents($hospitalFile, file_get_contents($hospitalUrl));
    }
    if (file_exists($hospitalFile) && filesize($hospitalFile) > 0) {
        $data = array(
            'nhi_id' => $nhiId,
            'url' => $hospitalUrl,
            'type' => '',
            'name' => '',
            'category' => '',
            'biz_type' => '',
            'service' => '',
            'phone' => '',
            'address' => '',
            'latitude' => '',
            'longitude' => '',
            'nhi_admin' => '',
            'nhi_end' => '',
        );
        $hospital = file_get_contents($hospitalFile);
        $pos = strpos($hospital, 'new google.maps.LatLng(');
        if (false !== $pos) {
            $pos += 23;
            list($data['latitude'], $data['longitude']) = explode(', ', substr($hospital, $pos, strpos($hospital, ')', $pos) - $pos));
        }
        $lines = explode('</tr>', $hospital);
        $lineNo = 0;
        foreach ($lines AS $line) {
            ++$lineNo;
            $cols = explode('</td>', $line);
            switch ($lineNo) {
                case 1:
                    unset($cols[0]);
                    break;
                case 2:
                    $data['name'] = html_entity_decode(trim(strip_tags($cols[1])));
                    break;
                case 3:
                    $data['biz_type'] = html_entity_decode(trim(strip_tags($cols[1])));
                    $data['phone'] = html_entity_decode(trim(strip_tags($cols[3])));
                    break;
                case 4:
                    $data['address'] = html_entity_decode(trim(strip_tags($cols[1])));
                    break;
                case 5:
                    $data['nhi_admin'] = html_entity_decode(trim(strip_tags($cols[1])));
                    $data['type'] = html_entity_decode(trim(strip_tags($cols[3])));
                    break;
                case 6:
                    $data['service'] = html_entity_decode(trim(strip_tags($cols[1])));
                    break;
                case 7:
                    $data['category'] = html_entity_decode(trim(strip_tags($cols[1])));
                    $data['nhi_end'] = html_entity_decode(trim(strip_tags($cols[3])));
                    break;
                case 9:
                    foreach ($cols AS $k => $v) {
                        $cols[$k] = str_replace('&nbsp;', '', trim(strip_tags($v)));
                    }
                    unset($cols[0]);
                    unset($cols[8]);
                    $data['morning'] = $cols;
                    break;
                case 10:
                    foreach ($cols AS $k => $v) {
                        $cols[$k] = str_replace('&nbsp;', '', trim(strip_tags($v)));
                    }
                    unset($cols[0]);
                    unset($cols[8]);
                    $data['afternoon'] = $cols;
                    break;
                case 11:
                    foreach ($cols AS $k => $v) {
                        $cols[$k] = str_replace('&nbsp;', '', trim(strip_tags($v)));
                    }
                    unset($cols[0]);
                    unset($cols[8]);
                    $data['evening'] = $cols;
                    break;
            }
        }
        fputcsv($fh, array(
            $data['nhi_id'],
            $data['name'],
            $data['type'],
            $data['phone'],
            $data['address'],
        ));
        file_put_contents($targetPath . '/' . $data['nhi_id'] . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $pos = strpos($list, $token, $posEnd);
}
