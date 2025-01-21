<?php
// Global configuration for MRTG server and Unimus endpoint
$MRTG_SERVER_IP = getenv('MRTG_SERVER_IP') ?: "default-mrtg-ip:88";
$UNIMUS_ENDPOINT = getenv('UNIMUS_ENDPOINT') ?: "http://default-unimus-endpoint/api/v2";
$UNIMUS_TOKEN = getenv('UNIMUS_TOKEN') ?: "default-unimus-token";
$USE_IFRAME = getenv('USE_IFRAME') ?: "true"; // Predvolená hodnota: true (používa iframe)


function fetch_devices_from_unimus($search = "") {
    global $UNIMUS_ENDPOINT, $UNIMUS_TOKEN;

    $url = "$UNIMUS_ENDPOINT/devices";

    if ($search !== "") {
        if (filter_var($search, FILTER_VALIDATE_IP)) {
            $url = "$UNIMUS_ENDPOINT/devices/findByAddress/$search?attr=s,c";
        } elseif (strpos($search, ".") !== false) {
            $url = "$UNIMUS_ENDPOINT/devices/findByDescription/$search?attr=s,c";
        } else {
            $url = "$UNIMUS_ENDPOINT/devices/findByDescription/$search?attr=s,c";
        }
    } else {
        $url .= "?page=0&size=100&attr=s,c";
    }

    $headers = [
        "Accept: application/json",
        "Authorization: Bearer $UNIMUS_TOKEN"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        error_log("cURL error: " . curl_error($ch));
        curl_close($ch);
        return ["data" => []];
    }

    curl_close($ch);
    $data = json_decode($response, true);

    // Debugging log for API response
    error_log("API Response: " . json_encode($data));

    // Ensure consistent response format
    if (!isset($data['data']) || empty($data['data'])) {
        return ["data" => []];
    }

    return $data['data'];
}

// function display_devices_with_iframe($devices) {
//    global $MRTG_SERVER_IP;
//
//    echo "<ul style='text-align: center;' id='device-list'>";
//    foreach ($devices as $device) {
//        // Skip devices with missing address or description
//        if (empty($device['address']) || empty($device['description'])) {
//            continue;
//        }
//
//        $address = htmlspecialchars($device['address']);
//        $description = htmlspecialchars($device['description']);
//
//        echo "<li data-name='$description' data-ip='$address'>";
//        echo "<a href='#' title='View graph for $description' onclick=\"openGraphWindow('$address')\">$description</a> - IP: $address";
//        echo "</li>";
//    }
//    echo "</ul>";
//}

function display_devices_with_iframe($devices) {
    global $MRTG_SERVER_IP, $USE_IFRAME;

    echo "<ul style='text-align: center;' id='device-list'>";
    foreach ($devices as $device) {
        if (empty($device['address']) || empty($device['description'])) {
            continue;
        }

        $address = htmlspecialchars($device['address']);
        $description = htmlspecialchars($device['description']);

        echo "<li data-name='$description' data-ip='$address'>";

        if ($USE_IFRAME === "true") {
            echo "<a href='#' title='View graph for $description' onclick=\"openGraphWindow('$address')\">$description</a> - IP: $address";
        } else {
            $mrtgUrl = "http://$MRTG_SERVER_IP/mrtg/$address.html";
            echo "<a href='$mrtgUrl' target='_blank' title='Open graph for $description'>$description</a> - IP: $address";
        }

        echo "</li>";
    }
    echo "</ul>";
}

if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $devices = fetch_devices_from_unimus($search);
    header('Content-Type: application/json');
    echo json_encode($devices);
    exit;
}

if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $devices = fetch_devices_from_unimus($search);
    header('Content-Type: application/json');
    echo json_encode($devices);
    exit;
}

// Proxy for MRTG content
if (isset($_GET['proxy_address'])) {
    $address = htmlspecialchars($_GET['proxy_address']);
    $mrtgUrl = "http://$MRTG_SERVER_IP/mrtg/$address.html";

    $options = [
        "http" => [
            "header" => "User-Agent: MRTG Proxy\r\n"
        ]
    ];
    $context = stream_context_create($options);

    $content = file_get_contents($mrtgUrl, false, $context);
    if ($content === false) {
        echo "Unable to fetch MRTG graph.";
    } else {
        // Debugging log
        error_log("Fetched HTML content for $address");

        // Rewrite resource URLs (src, href) to pass through the proxy
$content = preg_replace_callback(
    '/(src|href)=[\'\"]([^\'\":\/][^\'\"]*)[\'\"]/i',
    function ($matches) {
        $attribute = $matches[1]; // napr. "src" alebo "href"
        $relativePath = $matches[2]; // relatívna cesta k súboru
        $proxyPath = "?file=" . urlencode($relativePath);
        error_log("Rewriting $relativePath to $proxyPath");
        return "$attribute=\"$proxyPath\"";
    },
    $content
);
        echo $content;
    }
    exit;
}


if (isset($_GET['file'])) {
    // Bezpečné načítanie premenných
    $filePath = isset($_GET['file']) ? htmlspecialchars($_GET['file']) : '';
    $address = isset($_GET['proxy_address']) ? htmlspecialchars($_GET['proxy_address']) : null;

    // Ak nie je definovaný $filePath, vráťte chybu
    if (empty($filePath)) {
        http_response_code(400);
        // echo "Missing required parameter: file";
        exit;
    }

    // Regex na odstránenie nepotrebnej časti cesty
    $fixedFilePath = preg_replace(
        "#^$address/#", // Regex na začiatok cesty obsahujúci IP adresu a lomítko
        "",             // Odstránenie nepotrebnej časti
        $filePath
    );

    // Vytvorenie URL
    $fileUrl = "$MRTG_SERVER_IP/mrtg/$fixedFilePath";

    error_log("Fetching file from backend: $fileUrl");

    // Použitie cURL na získanie obsahu zo vzdialeného servera
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $content = curl_exec($ch);

    if (curl_errno($ch) || !$content) {
        error_log("Error fetching file: " . curl_error($ch));
        http_response_code(404);
        echo "Unable to fetch the file.";
    } else {
        // Detekcia MIME typu a návrat obsahu
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content) ?: 'application/octet-stream';
        header("Content-Type: $mimeType");
        echo $content;
    }

    curl_close($ch);
    exit;
} else {
    http_response_code(400);
    // echo "Missing required parameter: file";
}
if (isset($_GET['proxy_url'])) {
    $externalUrl = htmlspecialchars($_GET['proxy_url']);

    $options = [
        "http" => [
            "header" => "User-Agent: MRTG Proxy\r\n"
        ]
    ];
    $context = stream_context_create($options);

    $content = file_get_contents($externalUrl, false, $context);
    if ($content === false) {
        http_response_code(404);
        echo "Unable to fetch URL.";
        exit;
    }

    // Detect and serve the file with the correct MIME type
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->buffer($content) ?: 'application/octet-stream';
    header("Content-Type: $mimeType");
    echo $content;
    exit;
}
?>

<!DOCTYPE HTML>
<html>
<head>
    <title>MRTG - Unimus Integration</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
    <link rel="stylesheet" href="assets/css/main.css" />
    <script type="text/javascript">

async function filterDevices() {
    const searchInput = document.getElementById('search').value.trim();

    try {
        const response = await fetch(`?search=${encodeURIComponent(searchInput)}`);

        if (!response.ok) {
            console.error("Failed to fetch devices.", response.statusText);
            return;
        }

        let result = await response.json();
        console.log("Devices response:", result);

        // Handle single-object response for findByAddress
        let devices = Array.isArray(result) ? result : [result];

        const deviceList = document.getElementById('device-list');
        deviceList.innerHTML = '';

        if (devices.length === 0 || !devices[0].address) {
            const noResultItem = document.createElement('li');
            noResultItem.textContent = `No devices found for "${searchInput}".`;
            deviceList.appendChild(noResultItem);
            return;
        }

        devices.forEach(device => {
            const listItem = document.createElement('li');
            listItem.setAttribute('data-name', device.description);
            listItem.setAttribute('data-ip', device.address);

            const link = document.createElement('a');
            link.href = `#`;
            link.onclick = () => openGraphWindow(device.address);
            link.textContent = device.description || 'Unknown Device';

            listItem.appendChild(link);
            listItem.append(` - IP: ${device.address}`);
            deviceList.appendChild(listItem);
        });
    } catch (error) {
        console.error("Error fetching devices: ", error);
    }
}
        function openGraphWindow(address) {
            const url = `?proxy_address=${encodeURIComponent(address)}`;
            window.open(url, '_blank', 'width=800,height=600');
        }
    </script>
    <style>
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: Arial, sans-serif;
            text-align: center;
        }

        main {
            width: 50%;
        }

        input[type="text"] {
            width: 50%;
            padding: 10px;
            margin: 20px auto;
            display: block;
            font-size: 16px;
        }


        ul {
            padding: 0;
            list-style-type: none;
        }

        li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <header>
        <h1>MRTG Device List</h1>
    </header>
    <main>
        <section>
            <h2>Search Devices</h2>
            <input type="text" id="search" placeholder="Search by name or IP" onkeyup="filterDevices()" />
            <ul id="device-list">
                <?php 
                    $devices = fetch_devices_from_unimus();
                    display_devices_with_iframe($devices);
                ?>
            </ul>
        </section>
    </main>
    <footer>
        <p>&copy; 2025 - mhite s.r.o.</p>
    </footer>
</body>
</html>
