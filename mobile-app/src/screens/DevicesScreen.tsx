import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  TextInput,
  RefreshControl,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import deviceService from '../services/device.service';
import { Device } from '../types';

export default function DevicesScreen() {
  const [devices, setDevices] = useState<Device[]>([]);
  const [search, setSearch] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const navigation = useNavigation();

  useEffect(() => {
    loadDevices();
  }, []);

  async function loadDevices() {
    try {
      const response = await deviceService.getDevices();
      setDevices(response.data);
    } catch (error) {
      console.error('Load devices error:', error);
    }
  }

  async function onRefresh() {
    setRefreshing(true);
    await loadDevices();
    setRefreshing(false);
  }

  const filteredDevices = devices.filter(d =>
    d.serial_number.toLowerCase().includes(search.toLowerCase()) ||
    d.model_name.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <View style={styles.container}>
      <View style={styles.searchContainer}>
        <Ionicons name="search" size={20} color="#9ca3af" />
        <TextInput
          style={styles.searchInput}
          placeholder="Search devices..."
          value={search}
          onChangeText={setSearch}
        />
      </View>

      <FlatList
        data={filteredDevices}
        keyExtractor={(item) => item.id.toString()}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={styles.deviceCard}
            onPress={() => navigation.navigate('DeviceDetails' as never, { deviceId: item.id } as never)}
          >
            <View style={styles.deviceHeader}>
              <Text style={styles.deviceName}>{item.serial_number}</Text>
              <View style={[styles.statusBadge, item.status === 'online' && styles.statusOnline]}>
                <Text style={styles.statusText}>{item.status}</Text>
              </View>
            </View>
            <Text style={styles.deviceModel}>{item.model_name}</Text>
            <Text style={styles.deviceInfo}>IP: {item.ip_address}</Text>
          </TouchableOpacity>
        )}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Text style={styles.emptyText}>No devices found</Text>
          </View>
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    padding: 15,
    borderRadius: 10,
    margin: 15,
  },
  searchInput: {
    flex: 1,
    marginLeft: 10,
    fontSize: 16,
  },
  deviceCard: {
    backgroundColor: '#fff',
    padding: 15,
    marginHorizontal: 15,
    marginBottom: 10,
    borderRadius: 10,
  },
  deviceHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 5,
  },
  deviceName: {
    fontSize: 16,
    fontWeight: '600',
  },
  deviceModel: {
    color: '#6b7280',
    fontSize: 14,
    marginTop: 5,
  },
  deviceInfo: {
    color: '#9ca3af',
    fontSize: 12,
    marginTop: 5,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    backgroundColor: '#fef2f2',
  },
  statusOnline: {
    backgroundColor: '#dcfce7',
  },
  statusText: {
    fontSize: 12,
    color: '#dc2626',
  },
  emptyContainer: {
    padding: 40,
    alignItems: 'center',
  },
  emptyText: {
    color: '#9ca3af',
    fontSize: 16,
  },
});
