import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  RefreshControl,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import alarmService from '../services/alarm.service';
import { Alarm } from '../types';

/**
 * Alarms Screen
 * 
 * Real-time alarm monitoring with auto-refresh
 */

export default function AlarmsScreen() {
  const [alarms, setAlarms] = useState<Alarm[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'active' | 'critical'>('all');
  const navigation = useNavigation();

  useEffect(() => {
    loadAlarms();

    // Auto-refresh every 30 seconds
    const interval = setInterval(loadAlarms, 30000);
    return () => clearInterval(interval);
  }, [filter]);

  async function loadAlarms() {
    try {
      const params: any = { per_page: 50 };

      if (filter === 'active') {
        params.status = 'active';
      } else if (filter === 'critical') {
        params.severity = 'critical';
      }

      const response = await alarmService.getAlarms(params);
      setAlarms(response.data);
    } catch (error) {
      console.error('Load alarms error:', error);
    } finally {
      setLoading(false);
    }
  }

  async function onRefresh() {
    setRefreshing(true);
    await loadAlarms();
    setRefreshing(false);
  }

  async function handleAcknowledge(alarm: Alarm) {
    Alert.alert(
      'Acknowledge Alarm',
      `Acknowledge ${alarm.title}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Acknowledge',
          onPress: async () => {
            try {
              await alarmService.acknowledgeAlarm(alarm.id);
              await loadAlarms();
            } catch (error: any) {
              Alert.alert('Error', error.message || 'Failed to acknowledge alarm');
            }
          },
        },
      ]
    );
  }

  async function handleClear(alarm: Alarm) {
    Alert.alert(
      'Clear Alarm',
      `Clear ${alarm.title}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Clear',
          style: 'destructive',
          onPress: async () => {
            try {
              await alarmService.clearAlarm(alarm.id);
              await loadAlarms();
            } catch (error: any) {
              Alert.alert('Error', error.message || 'Failed to clear alarm');
            }
          },
        },
      ]
    );
  }

  function getSeverityColor(severity: string) {
    switch (severity) {
      case 'critical':
        return '#dc2626';
      case 'major':
        return '#ea580c';
      case 'minor':
        return '#f59e0b';
      case 'warning':
        return '#eab308';
      case 'info':
        return '#3b82f6';
      default:
        return '#6b7280';
    }
  }

  function getStatusIcon(status: string) {
    switch (status) {
      case 'active':
        return 'alert-circle';
      case 'acknowledged':
        return 'checkmark-circle';
      case 'cleared':
        return 'checkmark-done-circle';
      default:
        return 'information-circle';
    }
  }

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#3b82f6" />
        <Text style={styles.loadingText}>Loading alarms...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Filter Tabs */}
      <View style={styles.filterContainer}>
        <TouchableOpacity
          style={[styles.filterTab, filter === 'all' && styles.filterTabActive]}
          onPress={() => setFilter('all')}
        >
          <Text style={[styles.filterText, filter === 'all' && styles.filterTextActive]}>
            All
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.filterTab, filter === 'active' && styles.filterTabActive]}
          onPress={() => setFilter('active')}
        >
          <Text style={[styles.filterText, filter === 'active' && styles.filterTextActive]}>
            Active
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.filterTab, filter === 'critical' && styles.filterTabActive]}
          onPress={() => setFilter('critical')}
        >
          <Text style={[styles.filterText, filter === 'critical' && styles.filterTextActive]}>
            Critical
          </Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={alarms}
        keyExtractor={(item) => item.id.toString()}
        renderItem={({ item }) => (
          <View style={styles.alarmCard}>
            <View style={styles.alarmHeader}>
              <View style={styles.alarmTitleRow}>
                <Ionicons
                  name={getStatusIcon(item.status) as any}
                  size={24}
                  color={getSeverityColor(item.severity)}
                />
                <Text style={styles.alarmTitle}>{item.title}</Text>
              </View>
              <View style={[styles.severityBadge, { backgroundColor: getSeverityColor(item.severity) }]}>
                <Text style={styles.severityText}>{item.severity.toUpperCase()}</Text>
              </View>
            </View>

            <Text style={styles.alarmDescription}>{item.description}</Text>

            <View style={styles.alarmMeta}>
              <Text style={styles.alarmMetaText}>
                <Ionicons name="time-outline" size={14} color="#6b7280" />
                {' '}{new Date(item.raised_at).toLocaleString()}
              </Text>
              {item.device && (
                <Text style={styles.alarmMetaText}>
                  <Ionicons name="hardware-chip-outline" size={14} color="#6b7280" />
                  {' '}{item.device.serial_number}
                </Text>
              )}
            </View>

            {item.status === 'active' && (
              <View style={styles.alarmActions}>
                <TouchableOpacity
                  style={[styles.actionButton, styles.acknowledgeButton]}
                  onPress={() => handleAcknowledge(item)}
                >
                  <Ionicons name="checkmark" size={16} color="#fff" />
                  <Text style={styles.actionButtonText}>Acknowledge</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.actionButton, styles.clearButton]}
                  onPress={() => handleClear(item)}
                >
                  <Ionicons name="close" size={16} color="#fff" />
                  <Text style={styles.actionButtonText}>Clear</Text>
                </TouchableOpacity>
              </View>
            )}

            {item.status === 'acknowledged' && (
              <View style={styles.statusContainer}>
                <Ionicons name="checkmark-circle" size={16} color="#f59e0b" />
                <Text style={styles.statusText}>
                  Acknowledged {item.acknowledged_at && `at ${new Date(item.acknowledged_at).toLocaleString()}`}
                </Text>
              </View>
            )}

            {item.status === 'cleared' && (
              <View style={styles.statusContainer}>
                <Ionicons name="checkmark-done-circle" size={16} color="#10b981" />
                <Text style={styles.statusText}>
                  Cleared {item.cleared_at && `at ${new Date(item.cleared_at).toLocaleString()}`}
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
            <Ionicons name="notifications-off-outline" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>No alarms found</Text>
            <Text style={styles.emptySubtext}>
              {filter === 'critical' ? 'No critical alarms' : filter === 'active' ? 'No active alarms' : 'All clear!'}
            </Text>
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
  filterContainer: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    padding: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  filterTab: {
    flex: 1,
    paddingVertical: 8,
    paddingHorizontal: 12,
    marginHorizontal: 4,
    borderRadius: 8,
    backgroundColor: '#f9fafb',
    alignItems: 'center',
  },
  filterTabActive: {
    backgroundColor: '#3b82f6',
  },
  filterText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6b7280',
  },
  filterTextActive: {
    color: '#fff',
  },
  alarmCard: {
    backgroundColor: '#fff',
    padding: 15,
    marginHorizontal: 15,
    marginVertical: 8,
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: '#3b82f6',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  alarmHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  alarmTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  alarmTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
    marginLeft: 8,
    flex: 1,
  },
  severityBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  severityText: {
    fontSize: 10,
    fontWeight: 'bold',
    color: '#fff',
  },
  alarmDescription: {
    fontSize: 14,
    color: '#6b7280',
    marginBottom: 12,
    lineHeight: 20,
  },
  alarmMeta: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  alarmMetaText: {
    fontSize: 12,
    color: '#9ca3af',
  },
  alarmActions: {
    flexDirection: 'row',
    marginTop: 8,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 8,
    marginRight: 8,
  },
  acknowledgeButton: {
    backgroundColor: '#f59e0b',
  },
  clearButton: {
    backgroundColor: '#10b981',
  },
  actionButtonText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '600',
    marginLeft: 6,
  },
  statusContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 8,
  },
  statusText: {
    fontSize: 12,
    color: '#6b7280',
    marginLeft: 6,
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
});
