import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export default function DiagnosticsScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.text}>Diagnostics Screen - Coming Soon</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  text: { fontSize: 18, color: '#6b7280' },
});
