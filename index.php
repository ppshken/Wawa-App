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


// ✅ ถ้ารหัสผ่านถูกต้อง ให้เริ่ม Session และโหลดข้อมูล
$_SESSION["logged_in"] = true;

$apiUrl = "https://api.gpsiam.app/devices";
$apiKey = "13dade62-5bd6-4082-b0ce-36757dec0d47";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
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
    <title>Wawa Car Release</title>
	<link rel="icon" href="car_release_logo.jpg">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
	<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
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
            height: 400px;
            margin-top: 10px;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            text-align: left;
            cursor: pointer;
        }
        td {
            padding: 5px;
        }
        th {
            background-color: #f2f2f2;
            padding: 10px;
        }
        tr:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        .selected {
            background-color: rgba(0, 0, 0, 0.2) !important;
        }
        .status-on td:nth-child(5) {
            background-color: #007bff;
            color: white;
        }
        .status-off td:nth-child(5) {
            background-color: #ff4d4d;
            color: white;
        }
        .reset-btn {
        background-color: #007bff;
        color: white;
        font-size: 12px;
        font-weight: bold;
        padding: 10px 10px;
        border: none;
        margin-top: 10px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.3s ease, transform 0.2s ease;
        box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.2);
        display: inline-block;
        text-decoration: none;
        }

        .reset-btn:hover {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        .reset-btn:active {
            background-color: #003d80;
            transform: scale(0.95);
        }
        .btn_log {
            padding: 12px;
            border: 1px;
            border-radius: 15px;
        }

    </style>

</head>
<body>
    <div class="container py-4">
        <h2>Car Release</h2>
        <div id="map"></div>
        <button class="btn btn-outline-primary mt-2" onclick="location.reload();">รีเซ็ต</button>
        <div class="overflow-auto">
            <table>
                <thead>
                    <tr>
                        <th>ทะเบียน</th>
                        <th>รายละเอียด</th>
                        <th>ความเร็ว</th>
                        <th>จำกัดความเร็ว</th>
                        <th>สถานะ</th>
                        <th>ดูประวัติ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['devices'] as $device) : ?>
                        <tr onclick="selectRow(this); zoomTo(<?php echo $device['latitude']; ?>, <?php echo $device['longitude']; ?>)" class="<?php echo $device['engined'] ? 'status-on' : 'status-off'; ?>">
                            <td data-label="Name"><?php echo htmlspecialchars($device['name']); ?></td>
                            <td data-label="Detail"><?php echo htmlspecialchars($device['detail']); ?></td>
                            <td data-label="Speed (km/h)"><?php echo htmlspecialchars($device['speed']); ?></td>
                            <td data-label="SpeedLimit"><?php echo htmlspecialchars($device['speedLimit']); ?></td>
                            <td data-label="Status"><?php echo $device['engined'] ? 'ติดเครื่อง' : 'ดับเครื่อง'; ?></td>
                            <td data-label="Log">
                                <a class="btn_log" href="car_log.php?device=${device.id}">ดูประวัติ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    var map = L.map('map').setView([17.128265, 102.965351], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    
    var carIcon = L.icon({
        iconUrl: 'wawa-logo.png',
        iconSize: [32, 32],
        iconAnchor: [12, 25],
        popupAnchor: [0, -25]
    });

    var markers = [];

    function fetchData() {
        fetch('fetch_data.php')  // สร้างไฟล์ PHP แยกเพื่อดึง API
            .then(response => response.json())
            .then(data => {
                updateTable(data.devices);
                updateMap(data.devices);
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    function updateTable(devices) {
        let tableBody = document.querySelector("tbody");
        tableBody.innerHTML = ""; // เคลียร์ข้อมูลเก่า

        devices.forEach(device => {
            let row = document.createElement("tr");
            row.className = device.engined ? "status-on" : "status-off";
            row.onclick = function() { zoomTo(device.latitude, device.longitude); };

            row.innerHTML = `
                <td>${device.name}</td>
                <td>${device.detail}</td>
                <td>${device.speed}</td>
                <td>${device.speedLimit}</td>
                <td>${device.engined ? "ติดเครื่อง" : "ดับเครื่อง"}</td>
                <td data-label="Log">
                    <a class="btn_log" href="car_log.php?device=${device.id}&username=<?php echo urlencode($username); ?>&password=<?php echo urlencode($password); ?>">ดูประวัติ</a>
                </td>

            `;
            tableBody.appendChild(row);
        });
    }

        function updateMap(devices) {
        markers.forEach(marker => map.removeLayer(marker));
        markers = [];

        devices.forEach(device => {
            let statusText = device.engined ? "🔵 ติดเครื่อง" : "🔴 ดับเครื่อง"; // แสดงสถานะพร้อมไอคอนสี
            let marker = L.marker([device.latitude, device.longitude], { icon: carIcon }).addTo(map)
                .bindPopup(`${device.name}<br>Status: ${statusText}`)
                .bindTooltip(`${device.name} (${statusText})`, { permanent: true, direction: "right" }); // แสดงชื่อ + สถานะ ตลอดเวลา

            markers.push(marker);
        });
    }



        function zoomTo(lat, lon) {
            map.setView([lat, lon], 15);
        }

        fetchData();  // ดึงข้อมูลตอนโหลดหน้า
        setInterval(fetchData, 5000);  // ดึงข้อมูลทุก 30 วินาที

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

</body>
</html>
