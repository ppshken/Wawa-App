<?php

$group_id = isset($_GET["group_id"]) ? $_GET["group_id"] : "";

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
            height: 700px;
        }
        #loading {
            display: none; /* ✅ ซ่อนตอนเริ่มต้น */
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <h2 class="text-center mb-4">การติดตามรถ ทะเบียน : <?php echo htmlspecialchars($data['device']['name'] ?? 'Unknown'); ?></h2>       
    </div>
    <div id="loading" class="text-center my-3">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">กำลังโหลด...</span>
    </div>
    <p>กำลังโหลดข้อมูล โปรดรอสักครู่...</p>
    </div>
    <div id="map" class="rounded shadow-sm"></div>
    <script>
    var map = L.map('map').setView([13.801931, 100.586581], 10);
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
            .bindPopup("🚛 จุดล่าสุด").openPopup();
   
    }
    </script>
    <script>
        var group_id = "<?php echo htmlspecialchars($group_id); ?>";
    </script>
    <script>
        function fetchAdditionalPOIs() {
    fetch(`https://script.google.com/macros/s/AKfycbxryA9gHLZQjNi4DQQWIBCRIEd3kDYGaQdhKsWxHZSurEkPrHJc2nkBnocwHu-S9C4u/exec?group_store_id=${encodeURIComponent(group_id)}`)
        .then(response => response.json())
        .then(data => {
            clearExistingPOIs(); // ลบร้านค้าเก่าออกจากแผนที่
            updatePOIsOnMap(data);
        })
        .catch(error => console.error('Error fetching additional POIs:', error));
}

// ฟังก์ชันลบร้านค้าเก่าทั้งหมดจากแผนที่
let poiMarkers = [];

function clearExistingPOIs() {
    poiMarkers.forEach(marker => map.removeLayer(marker));
    poiMarkers = []; // เคลียร์อาร์เรย์ของมาร์คเกอร์ร้านค้า
}

// ฟังก์ชันอัปเดตร้านค้าใหม่ลงแผนที่
function updatePOIsOnMap(pois) {
    pois.forEach(poi => {
        let [lat, lng] = poi.lat_long.split(',').map(Number); // แปลงพิกัดเป็นตัวเลข
        let poiIcon = L.icon({
            iconUrl: 'shops.png', // ตรวจสอบว่าไฟล์นี้อยู่ในโฟลเดอร์ที่สามารถเข้าถึงได้
            iconSize: [32, 32], // ปรับขนาดไอคอนให้ใหญ่ขึ้น
            iconAnchor: [16, 32], // ทำให้ไอคอนอยู่ตรงกับจุดพิกัด
            popupAnchor: [0, -32] // กำหนดตำแหน่งของ popup ให้ไม่ทับกับไอคอน
        });

        let poiMarker = L.marker([lat, lng], { icon: poiIcon }).addTo(map)
            .bindTooltip(`<b>🏪 ${poi.store_name_result}</b>`, { 
                permanent: true, 
                direction: "top", 
                offset: [0, -15], // ปรับค่าตามต้องการ
                className: "store-label" // สามารถใช้ CSS ปรับแต่งเพิ่มเติมได้
            });

        poiMarkers.push(poiMarker); // เพิ่มมาร์กเกอร์ลงในอาร์เรย์
    });
}
async function fetchAdditionalPOIs() {
    showLoading(); // ✅ แสดง Loading ก่อนโหลดข้อมูล
    
    try {
        let response = await fetch(`https://script.google.com/macros/s/AKfycbxryA9gHLZQjNi4DQQWIBCRIEd3kDYGaQdhKsWxHZSurEkPrHJc2nkBnocwHu-S9C4u/exec?group_store_id=${encodeURIComponent(group_id)}`);
        let data = await response.json();
        
        clearExistingPOIs(); // ลบร้านค้าเก่าจากแผนที่
        updatePOIsOnMap(data);

        adjustMapView(); // ✅ ปรับซูมแผนที่ให้เห็นร้านค้า

    } catch (error) {
        console.error('Error fetching additional POIs:', error);
    } finally {
        hideLoading(); // ✅ ซ่อน Loading หลังโหลดเสร็จ
    }
}
function showLoading() {
    document.getElementById("loading").style.display = "block"; // ✅ แสดง Loading
}

function hideLoading() {
    document.getElementById("loading").style.display = "none"; // ✅ ซ่อน Loading
}

function adjustMapView() {
    let bounds = L.latLngBounds([]);

    // ✅ เพิ่มขอบเขตจากเส้นทางของรถ
    coordinates.forEach(coord => bounds.extend(coord));

    // ✅ เพิ่มขอบเขตจากพิกัดร้านค้า (ถ้ามี)
    poiMarkers.forEach(marker => bounds.extend(marker.getLatLng()));

    // ✅ ซูมออกเฉพาะถ้ามีข้อมูล
    if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [100, 100] }); // ปรับ padding เพื่อขยายมุมมอง
    }
}

// ✅ โหลดข้อมูลร้านค้าใหม่ และปรับซูมแผนที่
fetchAdditionalPOIs();

    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
