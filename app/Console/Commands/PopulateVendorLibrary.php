<?php

namespace App\Console\Commands;

use App\Models\RouterManufacturer;
use App\Models\RouterProduct;
use App\Models\VendorQuirk;
use App\Models\ConfigurationTemplateLibrary;
use App\Models\FirmwareVersion;
use App\Models\FirmwareCompatibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateVendorLibrary extends Command
{
    protected $signature = 'vendor:populate {--fresh : Clear existing data}';
    protected $description = 'Populate vendor library with carrier-grade manufacturers and devices';

    public function handle()
    {
        if ($this->option('fresh')) {
            $this->warn('Clearing existing vendor data...');
            DB::table('configuration_templates_library')->truncate();
            DB::table('firmware_compatibility_matrix')->truncate();
            DB::table('vendor_quirks')->truncate();
            DB::table('router_products')->truncate();
            DB::table('router_manufacturers')->truncate();
        }

        $this->info('🚀 Populating Vendor Library with Carrier-Grade Data...');
        
        $this->populateManufacturers();
        $this->populateProducts();
        $this->populateQuirks();
        $this->populateTemplates();
        
        $this->info('✅ Vendor Library populated successfully!');
        $this->displayStats();
    }

    private function populateManufacturers()
    {
        $this->info('📦 Creating Manufacturers...');
        
        $manufacturers = [
            [
                'name' => 'Huawei',
                'oui_prefix' => 'E4:D3:32,00:46:4B,DC:D9:AE',
                'category' => 'telco',
                'country' => 'China',
                'product_lines' => 'HG series, EchoLife, SmartAX, OptiXstar',
                'tr069_support' => true,
                'tr369_support' => true,
                'notes' => 'Leading telecom equipment manufacturer with extensive CPE portfolio'
            ],
            [
                'name' => 'ZTE',
                'oui_prefix' => 'F4:28:53,34:6B:D3,B0:75:0E',
                'category' => 'telco',
                'country' => 'China',
                'product_lines' => 'ZXHN, ZXA10, F series',
                'tr069_support' => true,
                'tr369_support' => true,
                'notes' => 'Major telecom vendor with GPON/EPON expertise'
            ],
            [
                'name' => 'Nokia',
                'oui_prefix' => '3C:A8:2A,E0:DB:55,18:0F:76',
                'category' => 'telco',
                'country' => 'Finland',
                'product_lines' => 'G series, Beacon, FastMile',
                'tr069_support' => true,
                'tr369_support' => true,
                'notes' => 'Enterprise-grade equipment, former Alcatel-Lucent portfolio'
            ],
            [
                'name' => 'Cisco',
                'oui_prefix' => '00:1E:BD,A0:F8:49,F4:4E:05',
                'category' => 'enterprise',
                'country' => 'USA',
                'product_lines' => 'ISR, ASR, CBR series',
                'tr069_support' => true,
                'tr369_support' => false,
                'notes' => 'Premium enterprise networking equipment'
            ],
            [
                'name' => 'TP-Link',
                'oui_prefix' => '50:C7:BF,14:CF:92,C0:25:E9',
                'category' => 'mainstream',
                'country' => 'China',
                'product_lines' => 'Archer, Deco, Omada',
                'tr069_support' => true,
                'tr369_support' => false,
                'notes' => 'Popular consumer and SMB networking equipment'
            ],
            [
                'name' => 'D-Link',
                'oui_prefix' => '14:D6:4D,90:94:E4,B8:A3:86',
                'category' => 'mainstream',
                'country' => 'Taiwan',
                'product_lines' => 'DIR, DSL, COVR series',
                'tr069_support' => true,
                'tr369_support' => false,
                'notes' => 'Established networking hardware manufacturer'
            ],
            [
                'name' => 'ASUS',
                'oui_prefix' => '04:D4:C4,70:4D:7B,2C:FD:A1',
                'category' => 'premium',
                'country' => 'Taiwan',
                'product_lines' => 'RT-AX, RT-AC, ZenWiFi, ROG Rapture',
                'tr069_support' => false,
                'tr369_support' => false,
                'notes' => 'Premium gaming and prosumer routers'
            ],
            [
                'name' => 'Netgear',
                'oui_prefix' => 'A0:40:A0,E0:46:9A,44:94:FC',
                'category' => 'prosumer',
                'country' => 'USA',
                'product_lines' => 'Nighthawk, Orbi, RAX series',
                'tr069_support' => false,
                'tr369_support' => false,
                'notes' => 'High-performance consumer and business networking'
            ],
            [
                'name' => 'Zyxel',
                'oui_prefix' => '28:28:5D,CC:B2:55,F8:C0:01',
                'category' => 'telco',
                'country' => 'Taiwan',
                'product_lines' => 'VMG, PMG, NBG series',
                'tr069_support' => true,
                'tr369_support' => true,
                'notes' => 'Telecom CPE specialist with TR-069 focus'
            ],
            [
                'name' => 'Technicolor',
                'oui_prefix' => '00:14:7D,B4:A5:EF,A8:3F:A1',
                'category' => 'telco',
                'country' => 'France',
                'product_lines' => 'TG series, TC series',
                'tr069_support' => true,
                'tr369_support' => true,
                'notes' => 'ISP-grade equipment, widely deployed by carriers'
            ],
            [
                'name' => 'AVM',
                'oui_prefix' => '3C:A6:2F,7C:AE:CE,C0:25:06',
                'category' => 'prosumer',
                'country' => 'Germany',
                'product_lines' => 'FRITZ!Box series',
                'tr069_support' => true,
                'tr369_support' => false,
                'notes' => 'German engineering, popular in Europe, FRITZ!OS'
            ],
            [
                'name' => 'Ubiquiti',
                'oui_prefix' => 'FC:EC:DA,B4:FB:E4,68:D7:9A',
                'category' => 'enterprise',
                'country' => 'USA',
                'product_lines' => 'UniFi, EdgeRouter, AmpliFi',
                'tr069_support' => false,
                'tr369_support' => false,
                'notes' => 'Enterprise WiFi and routing, cloud management'
            ],
        ];

        foreach ($manufacturers as $data) {
            RouterManufacturer::firstOrCreate(['name' => $data['name']], $data);
        }

        $this->info('✓ Created ' . count($manufacturers) . ' manufacturers');
    }

    private function populateProducts()
    {
        $this->info('📦 Creating Products...');
        
        $products = [
            ['manufacturer' => 'Huawei', 'model_name' => 'HG8245H', 'wifi_standard' => 'WiFi 5', 'max_speed' => '1300 Mbps', 'release_year' => 2018],
            ['manufacturer' => 'Huawei', 'model_name' => 'HG8546M', 'wifi_standard' => 'WiFi 5', 'max_speed' => '1200 Mbps', 'release_year' => 2019],
            ['manufacturer' => 'Huawei', 'model_name' => 'EchoLife HG8145V5', 'wifi_standard' => 'WiFi 6', 'max_speed' => '2400 Mbps', 'release_year' => 2021],
            ['manufacturer' => 'ZTE', 'model_name' => 'ZXHN F670', 'wifi_standard' => 'WiFi 5', 'max_speed' => '1200 Mbps', 'release_year' => 2017],
            ['manufacturer' => 'ZTE', 'model_name' => 'ZXHN F680', 'wifi_standard' => 'WiFi 6', 'max_speed' => '2400 Mbps', 'release_year' => 2020],
            ['manufacturer' => 'Nokia', 'model_name' => 'G-240W-C', 'wifi_standard' => 'WiFi 5', 'max_speed' => '1200 Mbps', 'release_year' => 2019],
            ['manufacturer' => 'Nokia', 'model_name' => 'XS-250X-A', 'wifi_standard' => 'WiFi 6E', 'max_speed' => '6000 Mbps', 'release_year' => 2022],
            ['manufacturer' => 'Cisco', 'model_name' => 'ISR 1100', 'wifi_standard' => null, 'max_speed' => '10 Gbps', 'release_year' => 2020],
            ['manufacturer' => 'TP-Link', 'model_name' => 'Archer AX73', 'wifi_standard' => 'WiFi 6', 'max_speed' => '5400 Mbps', 'release_year' => 2021],
            ['manufacturer' => 'TP-Link', 'model_name' => 'Archer C80', 'wifi_standard' => 'WiFi 5', 'max_speed' => '1900 Mbps', 'release_year' => 2019],
            ['manufacturer' => 'D-Link', 'model_name' => 'DIR-X5460', 'wifi_standard' => 'WiFi 6', 'max_speed' => '5400 Mbps', 'release_year' => 2021],
            ['manufacturer' => 'ASUS', 'model_name' => 'RT-AX88U', 'wifi_standard' => 'WiFi 6', 'max_speed' => '6000 Mbps', 'release_year' => 2018],
            ['manufacturer' => 'Netgear', 'model_name' => 'Nighthawk RAX200', 'wifi_standard' => 'WiFi 6', 'max_speed' => '11000 Mbps', 'release_year' => 2019],
            ['manufacturer' => 'Zyxel', 'model_name' => 'VMG8825-T50', 'wifi_standard' => 'WiFi 6', 'max_speed' => '2400 Mbps', 'release_year' => 2020],
            ['manufacturer' => 'Technicolor', 'model_name' => 'TG789vac v2', 'wifi_standard' => 'WiFi 5', 'max_speed' => '1600 Mbps', 'release_year' => 2016],
            ['manufacturer' => 'AVM', 'model_name' => 'FRITZ!Box 7590', 'wifi_standard' => 'WiFi 5', 'max_speed' => '1733 Mbps', 'release_year' => 2017],
            ['manufacturer' => 'Ubiquiti', 'model_name' => 'UniFi Dream Machine Pro', 'wifi_standard' => 'WiFi 6', 'max_speed' => '4800 Mbps', 'release_year' => 2020],
        ];

        foreach ($products as $data) {
            $manufacturer = RouterManufacturer::where('name', $data['manufacturer'])->first();
            if ($manufacturer) {
                unset($data['manufacturer']);
                $data['manufacturer_id'] = $manufacturer->id;
                RouterProduct::firstOrCreate(
                    ['manufacturer_id' => $manufacturer->id, 'model_name' => $data['model_name']],
                    $data
                );
            }
        }

        $this->info('✓ Created ' . count($products) . ' products');
    }

    private function populateQuirks()
    {
        $this->info('📦 Creating Vendor Quirks...');
        
        $huawei = RouterManufacturer::where('name', 'Huawei')->first();
        $zte = RouterManufacturer::where('name', 'ZTE')->first();

        $quirks = [
            [
                'manufacturer_id' => $huawei?->id,
                'quirk_type' => 'parameter_naming',
                'quirk_name' => 'Custom TR-069 Namespace',
                'description' => 'Uses custom X_HW_ prefix for vendor-specific parameters instead of standard InternetGatewayDevice',
                'affects_protocol' => 'TR-069',
                'severity' => 'medium',
                'workaround_notes' => 'Query both standard and X_HW_ namespaces when accessing parameters'
            ],
            [
                'manufacturer_id' => $zte?->id,
                'quirk_type' => 'tr069_compliance',
                'quirk_name' => 'Inform Interval Limitation',
                'description' => 'Does not respect PeriodicInformInterval below 300 seconds, defaults to 300',
                'affects_protocol' => 'TR-069',
                'severity' => 'low',
                'workaround_config' => ['min_inform_interval' => 300],
                'workaround_notes' => 'Set minimum interval to 300s in ACS configuration'
            ],
            [
                'manufacturer_id' => $huawei?->id,
                'quirk_type' => 'performance',
                'quirk_name' => 'Slow GPV Response on HG8245',
                'description' => 'GetParameterValues requests with >50 parameters timeout on older firmware',
                'affects_protocol' => 'TR-069',
                'firmware_versions_affected' => 'V3R016C00, V3R017C00',
                'severity' => 'high',
                'workaround_config' => ['max_parameters_per_request' => 30],
                'auto_apply' => true
            ],
        ];

        foreach ($quirks as $data) {
            if ($data['manufacturer_id']) {
                VendorQuirk::firstOrCreate(
                    [
                        'manufacturer_id' => $data['manufacturer_id'],
                        'quirk_name' => $data['quirk_name']
                    ],
                    $data
                );
            }
        }

        $this->info('✓ Created ' . count(array_filter($quirks, fn($q) => $q['manufacturer_id'])) . ' quirks');
    }

    private function populateTemplates()
    {
        $this->info('📦 Creating Configuration Templates...');
        
        $huawei = RouterManufacturer::where('name', 'Huawei')->first();
        $zte = RouterManufacturer::where('name', 'ZTE')->first();

        $templates = [
            [
                'manufacturer_id' => $huawei?->id,
                'template_name' => 'Basic WiFi Setup',
                'template_category' => 'wifi',
                'description' => 'Standard WiFi configuration for Huawei ONT devices',
                'protocol' => 'TR-069',
                'parameter_values' => [
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable' => '1',
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID' => 'MyNetwork',
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BeaconType' => 'WPA2-PSK',
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.IEEE11iEncryptionModes' => 'AESEncryption'
                ],
                'is_official' => true,
                'is_tested' => true
            ],
            [
                'manufacturer_id' => $zte?->id,
                'template_name' => 'VoIP Service Configuration',
                'template_category' => 'voip',
                'description' => 'SIP VoIP profile for ZTE CPE devices',
                'protocol' => 'TR-104',
                'parameter_values' => [
                    'InternetGatewayDevice.Services.VoiceService.1.VoiceProfile.1.Enable' => 'Enabled',
                    'InternetGatewayDevice.Services.VoiceService.1.VoiceProfile.1.SIP.ProxyServer' => 'sip.example.com',
                    'InternetGatewayDevice.Services.VoiceService.1.VoiceProfile.1.SIP.ProxyServerPort' => '5060'
                ],
                'is_official' => true,
                'is_tested' => true
            ],
        ];

        foreach ($templates as $data) {
            if ($data['manufacturer_id']) {
                ConfigurationTemplateLibrary::firstOrCreate(
                    [
                        'manufacturer_id' => $data['manufacturer_id'],
                        'template_name' => $data['template_name']
                    ],
                    $data
                );
            }
        }

        $this->info('✓ Created ' . count(array_filter($templates, fn($t) => $t['manufacturer_id'])) . ' templates');
    }

    private function displayStats()
    {
        $this->newLine();
        $this->info('📊 Vendor Library Statistics:');
        $this->table(
            ['Category', 'Count'],
            [
                ['Manufacturers', RouterManufacturer::count()],
                ['Products', RouterProduct::count()],
                ['Vendor Quirks', VendorQuirk::count()],
                ['Configuration Templates', ConfigurationTemplateLibrary::count()],
                ['Firmware Versions', FirmwareVersion::count()],
                ['Compatibility Entries', FirmwareCompatibility::count()],
            ]
        );
    }
}
