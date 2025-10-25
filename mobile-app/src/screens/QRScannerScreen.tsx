import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

/**
 * QR Scanner Screen
 * 
 * NOTE: Requires expo-camera package to be fully implemented
 * 
 * To enable:
 * 1. Request camera permissions in app.json (already configured)
 * 2. Install expo-camera: npm install expo-camera
 * 3. Rebuild app: expo prebuild
 * 4. Uncomment camera code below
 */

export default function QRScannerScreen() {
  // TODO: Uncomment when expo-camera is properly installed
  // import { Camera } from 'expo-camera';
  // const [hasPermission, setHasPermission] = useState(null);
  // const [scanned, setScanned] = useState(false);

  /*
  useEffect(() => {
    (async () => {
      const { status } = await Camera.requestCameraPermissionsAsync();
      setHasPermission(status === 'granted');
    })();
  }, []);

  const handleBarCodeScanned = ({ type, data }) => {
    setScanned(true);
    // Parse QR code data (device serial number or registration token)
    Alert.alert('QR Code Scanned', `Serial Number: ${data}`, [
      { text: 'OK', onPress: () => setScanned(false) }
    ]);
    
    // Navigate to device registration or details
    // navigation.navigate('DeviceDetails', { serialNumber: data });
  };

  if (hasPermission === null) {
    return <View style={styles.container}><Text>Requesting camera permission...</Text></View>;
  }
  if (hasPermission === false) {
    return <View style={styles.container}><Text>No access to camera</Text></View>;
  }

  return (
    <View style={styles.container}>
      <Camera
        style={StyleSheet.absoluteFillObject}
        onBarCodeScanned={scanned ? undefined : handleBarCodeScanned}
        barCodeScannerSettings={{
          barCodeTypes: [BarCodeScanner.Constants.BarCodeType.qr],
        }}
      />
      {scanned && (
        <TouchableOpacity
          style={styles.scanAgainButton}
          onPress={() => setScanned(false)}
        >
          <Text style={styles.scanAgainText}>Tap to Scan Again</Text>
        </TouchableOpacity>
      )}
    </View>
  );
  */

  // Placeholder UI
  return (
    <View style={styles.container}>
      <View style={styles.placeholderContainer}>
        <Ionicons name="qr-code-outline" size={100} color="#d1d5db" />
        <Text style={styles.title}>QR Scanner</Text>
        <Text style={styles.subtitle}>Camera integration required</Text>
        
        <View style={styles.instructionsCard}>
          <Text style={styles.instructionsTitle}>Setup Instructions:</Text>
          <Text style={styles.instructionStep}>1. Install expo-camera package</Text>
          <Text style={styles.instructionStep}>2. Rebuild the app</Text>
          <Text style={styles.instructionStep}>3. Grant camera permissions</Text>
          <Text style={styles.instructionStep}>4. Uncomment camera code in QRScannerScreen.tsx</Text>
        </View>

        <View style={styles.featureCard}>
          <Ionicons name="checkmark-circle" size={20} color="#10b981" />
          <Text style={styles.featureText}>Camera permissions configured</Text>
        </View>
        
        <View style={styles.featureCard}>
          <Ionicons name="checkmark-circle" size={20} color="#10b981" />
          <Text style={styles.featureText}>QR code parsing ready</Text>
        </View>
        
        <View style={styles.featureCard}>
          <Ionicons name="time" size={20} color="#f59e0b" />
          <Text style={styles.featureText}>expo-camera installation pending</Text>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  placeholderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#111827',
    marginTop: 20,
  },
  subtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 8,
    marginBottom: 30,
  },
  instructionsCard: {
    backgroundColor: '#eff6ff',
    padding: 20,
    borderRadius: 12,
    width: '100%',
    marginBottom: 20,
  },
  instructionsTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1e40af',
    marginBottom: 12,
  },
  instructionStep: {
    fontSize: 14,
    color: '#1e3a8a',
    marginBottom: 8,
    paddingLeft: 10,
  },
  featureCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
    padding: 12,
    borderRadius: 8,
    width: '100%',
    marginBottom: 8,
  },
  featureText: {
    fontSize: 14,
    color: '#374151',
    marginLeft: 10,
  },
  scanAgainButton: {
    position: 'absolute',
    bottom: 30,
    left: 20,
    right: 20,
    backgroundColor: '#3b82f6',
    padding: 15,
    borderRadius: 8,
    alignItems: 'center',
  },
  scanAgainText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
