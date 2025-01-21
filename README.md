# Documentation for MRTG and Unimus Integration

## Purpose of the Program
This program provides integration between the MRTG (Multi Router Traffic Grapher) system and Unimus. Its functionalities include:
- Retrieving a list of devices from the Unimus API.
- Generating a device list on a web page.
- Providing access to MRTG graphs either via iframe or by opening graph links in a new window.
- Proxy functionality for MRTG resources (images, HTML).

## Requirements
The program is implemented in PHP and runs as a Docker container with an Apache web server. It is optimized for PHP 8.2 and requires the Unimus API for device communication.

## Technologies Used
- **PHP**: 8.2
- **Apache**: with `mod_rewrite` and SSL enabled.
- **Docker**: The program is packaged as a containerized application.

## Program Features
1. **Device Retrieval**:
   - Devices are retrieved from the Unimus API using the `fetch_devices_from_unimus` method.
   - Devices can be filtered by IP address or description.

2. **Device List Generation**:
   - The `display_devices_with_iframe` function generates an HTML list of devices.
   - Graph display is optional, either via iframe or by opening links in a new window (controlled by the `USE_IFRAME` variable).

3. **Proxy for MRTG Resources**:
   - The proxy mechanism handles requests for HTML, images, and other MRTG resources.
   - Adjusts URLs to match the server structure.

4. **Secure Communication via HTTPS**:
   - The Docker image includes an automatically generated self-signed SSL certificate.

## Docker Configuration

### Dockerfile
The Dockerfile sets up the environment for the application with the following features:
- Installs Apache, PHP 8.2, and necessary modules.
- Enables `mod_rewrite` and SSL for Apache.
- Configures a self-signed SSL certificate.
- Copies application source files into `/var/www/html`.

### Environment Variables for Container
The following environment variables need to be defined for the container to start:

1. **`MRTG_SERVER_IP`**
   - **Description**: Address of the MRTG server, including the port.
   - **Default Value**: `default-mrtg-ip:88`.

2. **`UNIMUS_ENDPOINT`**
   - **Description**: URL endpoint for the Unimus API.
   - **Default Value**: `http://default-unimus-endpoint/api/v2`.

3. **`UNIMUS_TOKEN`**
   - **Description**: Token for authenticating with the Unimus API.
   - **Default Value**: `default-unimus-token`.

4. **`USE_IFRAME`**
   - **Description**: Determines whether MRTG graphs are displayed via iframe.
   - **Values**: `true` (display via iframe), `false` (open links in a new window).
   - **Default Value**: `true`.

### Example Container Startup
Run the container with defined environment variables:

```bash
docker run -d \
  -e MRTG_SERVER_IP="192.168.1.100:88" \
  -e UNIMUS_ENDPOINT="http://unimus.example.com/api/v2" \
  -e UNIMUS_TOKEN="your-unimus-token" \
  -e USE_IFRAME="true" \
  -p 8080:80 \
  -p 8443:443 \
  your-mrtg-unimus-image
```

## Program Functions

### `fetch_devices_from_unimus($search = "")`
- **Description**: Calls the Unimus API and retrieves a list of devices based on the provided filter.
- **Parameters**:
  - `$search`: String to filter devices (e.g., IP address or description).
- **Return Value**: Array of devices in JSON format.

### `display_devices_with_iframe($devices)`
- **Description**: Generates an HTML list of devices with links to MRTG graphs.
- **Parameters**:
  - `$devices`: Array of devices retrieved by `fetch_devices_from_unimus`.

### Proxy for MRTG Content
- **Description**: Processes requests for MRTG HTML and resources (images, CSS, JS) through PHP.
- **Logic**:
  - Uses `file_get_contents` or `cURL` to fetch content from the MRTG server.
  - Adjusts resource URLs to match the client application.

### JavaScript: `filterDevices()`
- **Description**: Dynamically filters the device list based on user input.
- **Flow**:
  - Calls the backend with the `search` parameter.
  - Updates the device list on the page.

## Example URLs

1. **View Device List**:
   ```
   http://localhost:8080
   ```

2. **Filter Devices by IP**:
   ```
   http://localhost:8080/?search=192.168.1.1
   ```

3. **Proxy for MRTG Graph**:
   ```
   http://localhost:8080/?proxy_address=192.168.1.1
   ```

## Logging

- **cURL Errors**: Logs errors when calling APIs or proxying.
- **Debugging URLs**: Logs generated URLs for requests.

Example log entry:
```
[INFO] Fetching devices from Unimus API: http://unimus.example.com/api/v2/devices
[DEBUG] Generated MRTG URL: http://192.168.1.100:88/mrtg/192.168.1.1.html
```

## Conclusion
This program provides a simple and flexible integration between MRTG and Unimus. Using a Docker container ensures consistent environment and easy deployment.

![obrazek](https://github.com/user-attachments/assets/4385e1be-b2b1-4ae6-bb5e-ad82402b2a5c)
