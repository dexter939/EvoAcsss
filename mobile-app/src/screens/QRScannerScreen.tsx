import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export default function QRScannerScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.text}>QR Scanner - Coming Soon</Text>
      <Text style={styles.subtext}>Requires expo-camera setup</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  text: { fontSize: 18, color: '#6b7280' },
  subtext: { fontSize: 14, color: '#9ca3af', marginTop: 10 },
});
