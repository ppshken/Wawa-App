<?php
session_start();

// 🔐 กำหนดค่า Username & Password ที่ถูกต้อง
$valid_username = "admin";
$valid_password = "123456";

// รับค่าจาก URL
$username = isset($_GET["username"]) ? $_GET["username"] : "";
$password = isset($_GET["password"]) ? $_GET["password"] : "";

// ตรวจสอบสิทธิ์
if ($username !== $valid_username || $password !== $valid_password) {
    echo "❌ เข้าถึงไม่ได้! กรุณาใส่ Username และ Password ที่ถูกต้อง";
    exit();
}

// รับค่า Device ID จาก URL (กำหนดค่าเริ่มต้นเป็น '30339' ถ้าไม่มี)
$deviceId = isset($_GET['device']) ? intval($_GET['device']) : 30339;

// รับค่า start และ end ถ้าไม่มีให้ใช้ค่าเริ่มต้นเป็นวันนี้ 00:00:00 และ 23:59:59
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d 00:00:00');
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d 23:59:59');

// ตรวจสอบว่าค่าที่รับมาเป็นรูปแบบวันที่ที่ถูกต้อง
function validateDate($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    // ตรวจสอบว่าเป็นวันที่ที่ถูกต้อง และตรวจสอบว่า วันที่ตรงกับรูปแบบที่กำหนด
    return $d && $d->format($format) === $date && $d->getTimestamp() !== false;
}
if (!validateDate($start) || !validateDate($end)) {
    die("รูปแบบวันที่ไม่ถูกต้อง");
}

// แสดงค่าที่ได้รับ (สำหรับ debug)
//echo "Device ID: " . htmlspecialchars($deviceId) . "<br>";
//echo "Start Time: " . htmlspecialchars($start) . "<br>";
//echo "End Time: " . htmlspecialchars($end) . "<br>";

$apiUrl = "https://api.gpsiam.app/device/log/" . $deviceId . "?start=" . urlencode($start) . "&end=" . urlencode($end);
$apiKey = "13dade62-5bd6-4082-b0ce-36757dec0d47"; // 🔑 เพิ่ม API Key

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey", // 🔑 เพิ่ม Header Authorization
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
?>

<!DOCTYPE html>
<html lang="TH">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Tracking</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body {
            font-family: Prompt, Prompt;
			font-size: 12px;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        #map {
            width: 100%;
            height: 500px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <h2 class="text-center mb-4">ประวัติการติดตามรถ ทะเบียน : <?php echo htmlspecialchars($data['device']['name'] ?? 'Unknown'); ?></h2>
        <button class="btn btn-outline-primary mb-3" onclick="goBack()">กลับไปหน้าก่อนหน้า</button>
        <div id="map" class="rounded shadow-sm"></div>
    </div>

    <script>
    var map = L.map('map').setView([13.801931, 100.586581], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var coordinates = <?php echo json_encode(array_map(function($log) {
        return [$log['latitude'], $log['longitude']];
    }, $data['logs'] ?? [])); ?>;

    if (coordinates.length > 0) {
        var polyline = L.polyline(coordinates, {color: 'blue'}).addTo(map);
        map.fitBounds(polyline.getBounds());

        L.marker(coordinates[0]).addTo(map)
            .bindPopup("📍 จุดเริ่มต้น").openPopup();

        L.marker(coordinates[coordinates.length - 1]).addTo(map)
            .bindPopup("🏁 จุดล่าสุด").openPopup();
   
    }
    </script>
    <script>
        // ฟังก์ชันสำหรับย้อนกลับไปหน้าก่อนหน้า
        function goBack() {
            window.history.back();
        }

        function fetchPOIs() {
            fetch('fetch_pois.php')  // สร้างไฟล์ PHP แยกเพื่อดึง API POIs
                .then(response => response.json())
                .then(data => {
                    updatePOIsOnMap(data.pois);
                })
                .catch(error => console.error('Error fetching POIs:', error));
        }
        

        function updatePOIsOnMap(pois) {
            pois.forEach(poi => {
                let poiMarker = L.marker([poi.latitude, poi.longitude], { icon: L.icon({
                    iconUrl: 'shops.png',
                    iconSize: [20, 20],
                    iconAnchor: [16, 32],
                    popupAnchor: [0, -32]
                }) }).addTo(map)
                .bindPopup(`<b>${poi.name}</b>`)
            });
        }

        fetchPOIs();  // ดึงข้อมูล POIs ตอนโหลดหน้า
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
