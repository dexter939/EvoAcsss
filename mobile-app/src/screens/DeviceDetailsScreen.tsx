import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useRoute, useNavigation } from '@react-navigation/native';
import deviceService from '../services/device.service';
import { Device } from '../types';

export default function DeviceDetailsScreen() {
  const [device, setDevice] = useState<Device | null>(null);
  const [loading, setLoading] = useState(true);
  const route = useRoute();
  const navigation = useNavigation();
  const { deviceId } = route.params as { deviceId: number };

  useEffect(() => {
    loadDevice();
  }, [deviceId]);

  async function loadDevice() {
    try {
      const data = await deviceService.getDevice(deviceId);
      setDevice(data);
    } catch (error) {
      console.error('Load device error:', error);
      Alert.alert('Error', 'Failed to load device details');
    } finally {
      setLoading(false);
    }
  }

  async function handleReboot() {
    Alert.alert(
      'Reboot Device',
      'Are you sure you want to reboot this device?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Reboot',
          style: 'destructive',
          onPress: async () => {
            try {
              await deviceService.rebootDevice(deviceId);
              Alert.alert('Success', 'Reboot command sent');
            } catch (error: any) {
              Alert.alert('Error', error.message || 'Failed to reboot device');
            }
          },
        },
      ]
    );
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#3b82f6" />
      </View>
    );
  }

  if (!device) {
    return (
      <View style={styles.centerContainer}>
        <Text style={styles.errorText}>Device not found</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container}>
      {/* Status Card */}
      <View style={styles.statusCard}>
        <View style={[styles.statusBadge, device.status === 'online' && styles.statusOnline]}>
          <Ionicons
            name={device.status === 'online' ? 'checkmark-circle' : 'close-circle'}
            size={24}
            color="#fff"
          />
          <Text style={styles.statusText}>{device.status.toUpperCase()}</Text>
        </View>
      </View>

      {/* Device Info */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Device Information</Text>
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Serial Number</Text>
          <Text style={styles.infoValue}>{device.serial_number}</Text>
        </View>
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Model</Text>
          <Text style={styles.infoValue}>{device.model_name}</Text>
        </View>
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Manufacturer</Text>
          <Text style={styles.infoValue}>{device.manufacturer}</Text>
        </View>
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Hardware Version</Text>
          <Text style={styles.infoValue}>{device.hardware_version}</Text>
        </View>
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Software Version</Text>
          <Text style={styles.infoValue}>{device.software_version}</Text>
        </View>
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>IP Address</Text>
          <Text style={styles.infoValue}>{device.ip_address}</Text>
        </View>
        
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Last Contact</Text>
          <Text style={styles.infoValue}>
            {device.last_contact ? new Date(device.last_contact).toLocaleString() : 'Never'}
          </Text>
        </View>
      </View>

      {/* Actions */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Actions</Text>
        
        <TouchableOpacity style={styles.actionButton} onPress={handleReboot}>
          <Ionicons name="reload" size={20} color="#fff" />
          <Text style={styles.actionButtonText}>Reboot Device</Text>
        </TouchableOpacity>
        
        <TouchableOpacity
          style={[styles.actionButton, styles.diagnosticsButton]}
          onPress={() => navigation.navigate('DiagnosticTest' as never, { deviceId } as never)}
        >
          <Ionicons name="pulse" size={20} color="#fff" />
          <Text style={styles.actionButtonText}>Run Diagnostics</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
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
  errorText: {
    fontSize: 16,
    color: '#ef4444',
  },
  statusCard: {
    backgroundColor: '#fff',
    padding: 20,
    margin: 15,
    borderRadius: 12,
    alignItems: 'center',
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ef4444',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 20,
  },
  statusOnline: {
    backgroundColor: '#10b981',
  },
  statusText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
    marginLeft: 8,
  },
  section: {
    backgroundColor: '#fff',
    padding: 15,
    margin: 15,
    marginTop: 0,
    borderRadius: 12,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 15,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  infoLabel: {
    fontSize: 14,
    color: '#6b7280',
    flex: 1,
  },
  infoValue: {
    fontSize: 14,
    color: '#111827',
    fontWeight: '500',
    flex: 1,
    textAlign: 'right',
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#ef4444',
    padding: 15,
    borderRadius: 8,
    marginBottom: 10,
  },
  diagnosticsButton: {
    backgroundColor: '#3b82f6',
  },
  actionButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
    marginLeft: 8,
  },
});
