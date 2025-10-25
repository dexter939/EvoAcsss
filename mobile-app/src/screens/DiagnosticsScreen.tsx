import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  TextInput,
  Modal,
  Alert,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import diagnosticService from '../services/diagnostic.service';
import deviceService from '../services/device.service';
import { DiagnosticTest, Device } from '../types';

/**
 * Diagnostics Screen
 * 
 * TR-143 diagnostic test execution
 */

export default function DiagnosticsScreen() {
  const [tests, setTests] = useState<DiagnosticTest[]>([]);
  const [devices, setDevices] = useState<Device[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [modalVisible, setModalVisible] = useState(false);
  const [testType, setTestType] = useState<'ping' | 'traceroute'>('ping');
  
  // Form state
  const [selectedDevice, setSelectedDevice] = useState<Device | null>(null);
  const [target, setTarget] = useState('8.8.8.8');
  const [running, setRunning] = useState(false);

  useEffect(() => {
    loadData();
  }, []);

  async function loadData() {
    try {
      const [testsData, devicesData] = await Promise.all([
        diagnosticService.getDiagnostics(),
        deviceService.getDevices(),
      ]);
      setTests(testsData.data);
      setDevices(devicesData.data);
    } catch (error) {
      console.error('Load diagnostics error:', error);
    } finally {
      setLoading(false);
    }
  }

  async function onRefresh() {
    setRefreshing(true);
    await loadData();
    setRefreshing(false);
  }

  async function runTest() {
    if (!selectedDevice) {
      Alert.alert('Error', 'Please select a device');
      return;
    }

    if (!target) {
      Alert.alert('Error', 'Please enter a target host');
      return;
    }

    setRunning(true);
    try {
      if (testType === 'ping') {
        await diagnosticService.runPingTest(selectedDevice.id, target);
      } else {
        await diagnosticService.runTracerouteTest(selectedDevice.id, target);
      }

      Alert.alert('Success', `${testType} test started successfully`);
      setModalVisible(false);
      await loadData();
    } catch (error: any) {
      Alert.alert('Error', error.message || `Failed to start ${testType} test`);
    } finally {
      setRunning(false);
    }
  }

  function getStatusColor(status: string) {
    switch (status) {
      case 'completed':
        return '#10b981';
      case 'running':
        return '#3b82f6';
      case 'failed':
        return '#ef4444';
      default:
        return '#6b7280';
    }
  }

  function getStatusIcon(status: string) {
    switch (status) {
      case 'completed':
        return 'checkmark-circle';
      case 'running':
        return 'reload-circle';
      case 'failed':
        return 'close-circle';
      default:
        return 'time';
    }
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#3b82f6" />
        <Text style={styles.loadingText}>Loading diagnostics...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Action Buttons */}
      <View style={styles.actionContainer}>
        <TouchableOpacity
          style={[styles.actionButton, styles.pingButton]}
          onPress={() => {
            setTestType('ping');
            setModalVisible(true);
          }}
        >
          <Ionicons name="pulse" size={24} color="#fff" />
          <Text style={styles.actionButtonText}>Run Ping</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.actionButton, styles.tracerouteButton]}
          onPress={() => {
            setTestType('traceroute');
            setModalVisible(true);
          }}
        >
          <Ionicons name="git-network" size={24} color="#fff" />
          <Text style={styles.actionButtonText}>Traceroute</Text>
        </TouchableOpacity>
      </View>

      {/* Test List */}
      <FlatList
        data={tests}
        keyExtractor={(item) => item.id.toString()}
        renderItem={({ item }) => (
          <View style={styles.testCard}>
            <View style={styles.testHeader}>
              <View style={styles.testTitleRow}>
                <Ionicons
                  name={getStatusIcon(item.status) as any}
                  size={24}
                  color={getStatusColor(item.status)}
                />
                <Text style={styles.testTitle}>{item.test_type}</Text>
              </View>
              <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status) }]}>
                <Text style={styles.statusText}>{item.status.toUpperCase()}</Text>
              </View>
            </View>

            {item.target && (
              <Text style={styles.testTarget}>Target: {item.target}</Text>
            )}

            {item.device && (
              <Text style={styles.testDevice}>
                <Ionicons name="hardware-chip-outline" size={14} color="#6b7280" />
                {' '}{item.device.serial_number}
              </Text>
            )}

            <Text style={styles.testTime}>
              <Ionicons name="time-outline" size={14} color="#6b7280" />
              {' '}{new Date(item.created_at).toLocaleString()}
            </Text>

            {item.result && (
              <View style={styles.resultContainer}>
                <Text style={styles.resultTitle}>Results:</Text>
                <Text style={styles.resultText} numberOfLines={3}>
                  {JSON.stringify(item.result, null, 2)}
                </Text>
              </View>
            )}
          </View>
        )}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Ionicons name="flask-outline" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>No diagnostic tests</Text>
            <Text style={styles.emptySubtext}>Run a test to get started</Text>
          </View>
        }
      />

      {/* Test Modal */}
      <Modal
        visible={modalVisible}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>
                Run {testType === 'ping' ? 'Ping' : 'Traceroute'} Test
              </Text>
              <TouchableOpacity onPress={() => setModalVisible(false)}>
                <Ionicons name="close" size={24} color="#6b7280" />
              </TouchableOpacity>
            </View>

            {/* Device Selection */}
            <Text style={styles.label}>Select Device</Text>
            <View style={styles.deviceList}>
              {devices.slice(0, 5).map((device) => (
                <TouchableOpacity
                  key={device.id}
                  style={[
                    styles.deviceItem,
                    selectedDevice?.id === device.id && styles.deviceItemSelected,
                  ]}
                  onPress={() => setSelectedDevice(device)}
                >
                  <Text style={styles.deviceSerial}>{device.serial_number}</Text>
                  <Text style={styles.deviceModel}>{device.model_name}</Text>
                </TouchableOpacity>
              ))}
            </View>

            {devices.length === 0 && (
              <Text style={styles.noDevicesText}>
                No devices available. Please add a device first.
              </Text>
            )}

            {/* Target Input */}
            <Text style={styles.label}>Target Host</Text>
            <TextInput
              style={styles.input}
              placeholder="e.g., 8.8.8.8 or google.com"
              value={target}
              onChangeText={setTarget}
              autoCapitalize="none"
            />

            {/* Action Buttons */}
            <View style={styles.modalActions}>
              <TouchableOpacity
                style={[styles.modalButton, styles.cancelButton]}
                onPress={() => setModalVisible(false)}
              >
                <Text style={styles.cancelButtonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalButton, styles.runButton]}
                onPress={runTest}
                disabled={running}
              >
                {running ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.runButtonText}>Run Test</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  centerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 10,
    color: '#6b7280',
    fontSize: 16,
  },
  actionContainer: {
    flexDirection: 'row',
    padding: 15,
    gap: 10,
  },
  actionButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 15,
    borderRadius: 12,
    gap: 8,
  },
  pingButton: {
    backgroundColor: '#3b82f6',
  },
  tracerouteButton: {
    backgroundColor: '#8b5cf6',
  },
  actionButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
  testCard: {
    backgroundColor: '#fff',
    padding: 15,
    marginHorizontal: 15,
    marginVertical: 8,
    borderRadius: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  testHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  testTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  testTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
    marginLeft: 8,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 10,
    fontWeight: 'bold',
    color: '#fff',
  },
  testTarget: {
    fontSize: 14,
    color: '#3b82f6',
    marginBottom: 6,
  },
  testDevice: {
    fontSize: 12,
    color: '#6b7280',
    marginBottom: 4,
  },
  testTime: {
    fontSize: 12,
    color: '#9ca3af',
  },
  resultContainer: {
    marginTop: 12,
    padding: 10,
    backgroundColor: '#f9fafb',
    borderRadius: 8,
  },
  resultTitle: {
    fontSize: 12,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 4,
  },
  resultText: {
    fontSize: 11,
    color: '#6b7280',
    fontFamily: 'monospace',
  },
  emptyContainer: {
    padding: 40,
    alignItems: 'center',
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#6b7280',
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#9ca3af',
    marginTop: 4,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 20,
    maxHeight: '80%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#111827',
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#374151',
    marginBottom: 8,
    marginTop: 12,
  },
  deviceList: {
    maxHeight: 200,
  },
  deviceItem: {
    padding: 12,
    borderRadius: 8,
    borderWidth: 2,
    borderColor: '#e5e7eb',
    marginBottom: 8,
  },
  deviceItemSelected: {
    borderColor: '#3b82f6',
    backgroundColor: '#eff6ff',
  },
  deviceSerial: {
    fontSize: 14,
    fontWeight: '600',
    color: '#111827',
  },
  deviceModel: {
    fontSize: 12,
    color: '#6b7280',
    marginTop: 2,
  },
  noDevicesText: {
    fontSize: 14,
    color: '#9ca3af',
    fontStyle: 'italic',
    marginBottom: 12,
  },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    marginBottom: 20,
  },
  modalActions: {
    flexDirection: 'row',
    gap: 10,
  },
  modalButton: {
    flex: 1,
    padding: 15,
    borderRadius: 8,
    alignItems: 'center',
  },
  cancelButton: {
    backgroundColor: '#f3f4f6',
  },
  cancelButtonText: {
    color: '#374151',
    fontSize: 16,
    fontWeight: '600',
  },
  runButton: {
    backgroundColor: '#3b82f6',
  },
  runButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
  },
});
