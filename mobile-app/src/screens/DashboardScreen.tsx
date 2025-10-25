import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  RefreshControl,
  TouchableOpacity,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import deviceService from '../services/device.service';
import alarmService from '../services/alarm.service';
import { DeviceStats, AlarmStats } from '../types';

/**
 * Dashboard Screen
 * 
 * Main overview screen with device and alarm statistics
 */

export default function DashboardScreen() {
  const [deviceStats, setDeviceStats] = useState<DeviceStats | null>(null);
  const [alarmStats, setAlarmStats] = useState<AlarmStats | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadDashboardData();
  }, []);

  async function loadDashboardData() {
    try {
      const [devices, alarms] = await Promise.all([
        deviceService.getDeviceStats(),
        alarmService.getAlarmStats(),
      ]);
      setDeviceStats(devices);
      setAlarmStats(alarms);
    } catch (error) {
      console.error('Dashboard load error:', error);
    } finally {
      setLoading(false);
    }
  }

  async function onRefresh() {
    setRefreshing(true);
    await loadDashboardData();
    setRefreshing(false);
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <Text>Loading...</Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <View style={styles.header}>
        <Text style={styles.title}>Dashboard</Text>
        <Text style={styles.subtitle}>Real-time system overview</Text>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Devices</Text>
        <View style={styles.statsGrid}>
          <StatCard
            icon="hardware-chip"
            label="Total"
            value={deviceStats?.total || 0}
            color="#3b82f6"
          />
          <StatCard
            icon="checkmark-circle"
            label="Online"
            value={deviceStats?.online || 0}
            color="#10b981"
          />
          <StatCard
            icon="close-circle"
            label="Offline"
            value={deviceStats?.offline || 0}
            color="#ef4444"
          />
          <StatCard
            icon="construct"
            label="Maintenance"
            value={deviceStats?.maintenance || 0}
            color="#f59e0b"
          />
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Alarms</Text>
        <View style={styles.statsGrid}>
          <StatCard
            icon="alert-circle"
            label="Critical"
            value={alarmStats?.critical || 0}
            color="#dc2626"
          />
          <StatCard
            icon="warning"
            label="Major"
            value={alarmStats?.major || 0}
            color="#ea580c"
          />
          <StatCard
            icon="information-circle"
            label="Minor"
            value={alarmStats?.minor || 0}
            color="#f59e0b"
          />
          <StatCard
            icon="notifications"
            label="Unacked"
            value={alarmStats?.unacknowledged || 0}
            color="#8b5cf6"
          />
        </View>
      </View>
    </ScrollView>
  );
}

interface StatCardProps {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value: number;
  color: string;
}

function StatCard({ icon, label, value, color }: StatCardProps) {
  return (
    <View style={styles.statCard}>
      <Ionicons name={icon} size={32} color={color} />
      <Text style={styles.statValue}>{value.toLocaleString()}</Text>
      <Text style={styles.statLabel}>{label}</Text>
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
  header: {
    padding: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  title: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#111827',
  },
  subtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
  section: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 15,
    color: '#374151',
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginHorizontal: -5,
  },
  statCard: {
    width: '48%',
    backgroundColor: '#fff',
    padding: 20,
    borderRadius: 12,
    margin: '1%',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  statValue: {
    fontSize: 32,
    fontWeight: 'bold',
    marginTop: 10,
    color: '#111827',
  },
  statLabel: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 5,
  },
});
