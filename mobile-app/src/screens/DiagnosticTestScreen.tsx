import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useRoute, useNavigation } from '@react-navigation/native';
import diagnosticService from '../services/diagnostic.service';

export default function DiagnosticTestScreen() {
  const [target, setTarget] = useState('8.8.8.8');
  const [testType, setTestType] = useState<'ping' | 'traceroute'>('ping');
  const [running, setRunning] = useState(false);
  const route = useRoute();
  const navigation = useNavigation();
  const { deviceId } = route.params as { deviceId: number };

  async function runTest() {
    if (!target) {
      Alert.alert('Error', 'Please enter a target host');
      return;
    }

    setRunning(true);
    try {
      if (testType === 'ping') {
        await diagnosticService.runPingTest(deviceId, target);
      } else {
        await diagnosticService.runTracerouteTest(deviceId, target);
      }

      Alert.alert('Success', `${testType} test started successfully`, [
        {
          text: 'OK',
          onPress: () => navigation.goBack(),
        },
      ]);
    } catch (error: any) {
      Alert.alert('Error', error.message || `Failed to start ${testType} test`);
    } finally {
      setRunning(false);
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.content}>
        {/* Test Type Selection */}
        <Text style={styles.label}>Test Type</Text>
        <View style={styles.typeContainer}>
          <TouchableOpacity
            style={[styles.typeButton, testType === 'ping' && styles.typeButtonActive]}
            onPress={() => setTestType('ping')}
          >
            <Ionicons name="pulse" size={24} color={testType === 'ping' ? '#fff' : '#3b82f6'} />
            <Text style={[styles.typeText, testType === 'ping' && styles.typeTextActive]}>
              Ping
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.typeButton, testType === 'traceroute' && styles.typeButtonActive]}
            onPress={() => setTestType('traceroute')}
          >
            <Ionicons name="git-network" size={24} color={testType === 'traceroute' ? '#fff' : '#8b5cf6'} />
            <Text style={[styles.typeText, testType === 'traceroute' && styles.typeTextActive]}>
              Traceroute
            </Text>
          </TouchableOpacity>
        </View>

        {/* Target Input */}
        <Text style={styles.label}>Target Host</Text>
        <TextInput
          style={styles.input}
          placeholder="e.g., 8.8.8.8 or google.com"
          value={target}
          onChangeText={setTarget}
          autoCapitalize="none"
        />

        {/* Run Button */}
        <TouchableOpacity
          style={styles.runButton}
          onPress={runTest}
          disabled={running}
        >
          {running ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <>
              <Ionicons name="play" size={20} color="#fff" />
              <Text style={styles.runButtonText}>Run {testType} Test</Text>
            </>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  content: {
    padding: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
    marginTop: 12,
  },
  typeContainer: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 20,
  },
  typeButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 15,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e5e7eb',
    backgroundColor: '#fff',
    gap: 8,
  },
  typeButtonActive: {
    backgroundColor: '#3b82f6',
    borderColor: '#3b82f6',
  },
  typeText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#6b7280',
  },
  typeTextActive: {
    color: '#fff',
  },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    backgroundColor: '#fff',
  },
  runButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#10b981',
    padding: 15,
    borderRadius: 8,
    marginTop: 20,
    gap: 8,
  },
  runButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
