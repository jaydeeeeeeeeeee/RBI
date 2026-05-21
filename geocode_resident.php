<?php
session_start();
if(!isset($_SESSION['admin'])){http_response_code(403);echo json_encode(['ok'=>false,'msg'=>'Unauthorized']);exit();}
require_once 'Residents_DB.php';
header('Content-Type: application/json');

$id      = (int)($_POST['id']      ?? 0);
$address = trim($_POST['address']  ?? '');

if(!$id || !$address){
    echo json_encode(['ok'=>false,'msg'=>'Missing parameters']);
    exit();
}

// Build two queries: specific (barangay+city) then fallback (city only)
$queries = [
    $address . ', Barangay 410, Tondo, Manila, Metro Manila, Philippines',
    $address . ', Tondo, Manila, Philippines',
    $address . ', Manila, Philippines',
];

$ctx = stream_context_create(['http'=>[
    'header'  => "User-Agent: ProjectRBI-Barangay410/1.0\r\n",
    'timeout' => 10,
]]);

$lat = null; $lng = null;
foreach($queries as $q){
    $url = 'https://nominatim.openstreetmap.org/search?q='.urlencode($q).'&format=json&limit=1&countrycodes=ph';
    $raw = @file_get_contents($url, false, $ctx);
    if($raw !== false){
        $data = json_decode($raw, true);
        if(!empty($data)){
            $lat = (float)$data[0]['lat'];
            $lng = (float)$data[0]['lon'];
            break;
        }
    }
    usleep(300000); // 300ms between fallback attempts
}

if($lat !== null){
    // Ensure lat/lng columns exist
    $conn->query("ALTER TABLE residents ADD COLUMN IF NOT EXISTS lat DECIMAL(10,7) DEFAULT NULL");
    $conn->query("ALTER TABLE residents ADD COLUMN IF NOT EXISTS lng DECIMAL(10,7) DEFAULT NULL");

    $stmt = $conn->prepare("UPDATE residents SET lat=?, lng=? WHERE id=?");
    $stmt->bind_param('ddi', $lat, $lng, $id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok'=>true, 'id'=>$id, 'lat'=>$lat, 'lng'=>$lng]);
} else {
    echo json_encode(['ok'=>false, 'id'=>$id, 'msg'=>'Address not found']);
}
