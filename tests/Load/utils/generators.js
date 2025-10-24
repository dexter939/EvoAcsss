/**
 * K6 Load Testing - Data Generators
 * 
 * Generate realistic test data for devices, parameters, and configurations
 */

import { randomInt, randomElement } from './config.js';

/**
 * Generate unique device serial number
 */
export function generateSerialNumber(index) {
    const oui = '00259E';  // Test OUI
    const suffix = String(index).padStart(10, '0');
    return `${oui}${suffix}`;
}

/**
 * Generate device MAC address
 */
export function generateMacAddress(index) {
    const oui = '00:25:9E';
    const suffix = index.toString(16).padStart(6, '0');
    const parts = suffix.match(/.{1,2}/g);
    return `${oui}:${parts.join(':')}`.toUpperCase();
}

/**
 * Generate realistic device model
 */
export function generateDeviceModel() {
    const manufacturers = [
        'Huawei', 'ZTE', 'Nokia', 'Alcatel-Lucent', 
        'Technicolor', 'AVM', 'TP-Link', 'Netgear'
    ];
    const models = [
        'HG8245H', 'HG8245Q', 'F660', 'F680', 
        '7330', '7360', '7390', '7530',
        'Archer', 'C7', 'C9', 'AX50'
    ];
    
    return `${randomElement(manufacturers)} ${randomElement(models)}`;
}

/**
 * Generate device type
 */
export function generateDeviceType() {
    const types = ['ONT', 'ONU', 'Router', 'Gateway', 'Modem'];
    return randomElement(types);
}

/**
 * Generate firmware version
 */
export function generateFirmwareVersion() {
    const major = randomInt(1, 5);
    const minor = randomInt(0, 9);
    const patch = randomInt(0, 99);
    return `${major}.${minor}.${patch}`;
}

/**
 * Generate IP address (private range)
 */
export function generateIpAddress() {
    return `192.168.${randomInt(1, 255)}.${randomInt(1, 254)}`;
}

/**
 * Generate TR-069 Inform message (SOAP XML)
 */
export function generateTr069Inform(deviceSerial, deviceMac) {
    const currentTime = new Date().toISOString();
    
    return `<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
               xmlns:cwmp="urn:dslforum-org:cwmp-1-0">
    <soap:Header>
        <cwmp:ID soap:mustUnderstand="1">1</cwmp:ID>
    </soap:Header>
    <soap:Body>
        <cwmp:Inform>
            <DeviceId>
                <Manufacturer>LoadTest</Manufacturer>
                <OUI>00259E</OUI>
                <ProductClass>CPE</ProductClass>
                <SerialNumber>${deviceSerial}</SerialNumber>
            </DeviceId>
            <Event soap:arrayType="cwmp:EventStruct[1]">
                <EventStruct>
                    <EventCode>2 PERIODIC</EventCode>
                    <CommandKey></CommandKey>
                </EventStruct>
            </Event>
            <MaxEnvelopes>1</MaxEnvelopes>
            <CurrentTime>${currentTime}</CurrentTime>
            <RetryCount>0</RetryCount>
            <ParameterList soap:arrayType="cwmp:ParameterValueStruct[3]">
                <ParameterValueStruct>
                    <Name>Device.DeviceInfo.SoftwareVersion</Name>
                    <Value>${generateFirmwareVersion()}</Value>
                </ParameterValueStruct>
                <ParameterValueStruct>
                    <Name>Device.ManagementServer.ConnectionRequestURL</Name>
                    <Value>http://${generateIpAddress()}:7547/</Value>
                </ParameterValueStruct>
                <ParameterValueStruct>
                    <Name>Device.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceIPAddress</Name>
                    <Value>${generateIpAddress()}</Value>
                </ParameterValueStruct>
            </ParameterList>
        </cwmp:Inform>
    </soap:Body>
</soap:Envelope>`;
}

/**
 * Generate TR-069 GetParameterValues request
 */
export function generateTr069GetParameterValues(parameters) {
    const paramList = parameters.map(param => 
        `<string>${param}</string>`
    ).join('\n                ');
    
    return `<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" 
               xmlns:cwmp="urn:dslforum-org:cwmp-1-0">
    <soap:Header>
        <cwmp:ID soap:mustUnderstand="1">2</cwmp:ID>
    </soap:Header>
    <soap:Body>
        <cwmp:GetParameterValues>
            <ParameterNames soap:arrayType="xsd:string[${parameters.length}]">
                ${paramList}
            </ParameterNames>
        </cwmp:GetParameterValues>
    </soap:Body>
</soap:Envelope>`;
}

/**
 * Generate TR-369 USP Get request (simplified JSON for now)
 */
export function generateUspGetRequest(deviceId, paths) {
    return {
        header: {
            msg_id: `get-${Date.now()}-${randomInt(1000, 9999)}`,
            msg_type: 'GET',
        },
        body: {
            request: {
                get: {
                    param_paths: paths,
                },
            },
        },
        from_id: `controller::1`,
        to_id: `device::${deviceId}`,
    };
}

/**
 * Generate TR-369 USP Set request
 */
export function generateUspSetRequest(deviceId, parameters) {
    return {
        header: {
            msg_id: `set-${Date.now()}-${randomInt(1000, 9999)}`,
            msg_type: 'SET',
        },
        body: {
            request: {
                set: {
                    update_objs: [{
                        obj_path: 'Device.DeviceInfo.',
                        param_settings: parameters,
                    }],
                },
            },
        },
        from_id: `controller::1`,
        to_id: `device::${deviceId}`,
    };
}

/**
 * Generate realistic device data for API creation
 */
export function generateDeviceData(index) {
    return {
        serial_number: generateSerialNumber(index),
        mac_address: generateMacAddress(index),
        manufacturer: randomElement(['Huawei', 'ZTE', 'Nokia', 'Technicolor']),
        model: generateDeviceModel(),
        device_type: generateDeviceType(),
        firmware_version: generateFirmwareVersion(),
        ip_address: generateIpAddress(),
        connection_url: `http://${generateIpAddress()}:7547/`,
        status: randomElement(['online', 'offline', 'pending']),
        last_contact: new Date().toISOString(),
    };
}

/**
 * Generate search query for device filtering
 */
export function generateSearchQuery() {
    const queries = [
        'Huawei',
        'ONT',
        '192.168',
        'HG8245',
        'online',
        generateSerialNumber(randomInt(1, 1000)),
    ];
    return randomElement(queries);
}

/**
 * Generate TR-181 parameter path
 */
export function generateTr181ParameterPath() {
    const paths = [
        'Device.DeviceInfo.SoftwareVersion',
        'Device.DeviceInfo.HardwareVersion',
        'Device.DeviceInfo.SerialNumber',
        'Device.ManagementServer.URL',
        'Device.ManagementServer.PeriodicInformInterval',
        'Device.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress',
        'Device.LAN.IPAddress',
        'Device.WiFi.SSID.1.SSID',
        'Device.WiFi.SSID.1.Enable',
        'Device.Time.NTPServer1',
    ];
    return randomElement(paths);
}

/**
 * Generate batch of TR-181 parameters
 */
export function generateParameterBatch(count = 10) {
    const parameters = [];
    for (let i = 0; i < count; i++) {
        parameters.push(generateTr181ParameterPath());
    }
    return parameters;
}

export default {
    generateSerialNumber,
    generateMacAddress,
    generateDeviceModel,
    generateDeviceType,
    generateFirmwareVersion,
    generateIpAddress,
    generateTr069Inform,
    generateTr069GetParameterValues,
    generateUspGetRequest,
    generateUspSetRequest,
    generateDeviceData,
    generateSearchQuery,
    generateTr181ParameterPath,
    generateParameterBatch,
};
